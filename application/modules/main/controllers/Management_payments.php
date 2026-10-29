<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Management_payments extends CI_Controller
{
	private $approver_groups = array('admin', 'management', 'pengurus');

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper('url');
		$this->load->library(array('session', 'ion_auth', 'page_access'));
		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('petty_cash') ||
			!$this->ion_auth->in_group($this->approver_groups)) {
			show_error('Halaman pembayaran hanya dapat diakses admin, management, atau pengurus.', 403);
		}
		foreach (array('acc_cash_transactions', 'acc_cash_funds', 'acc_cash_closings', 'acc_accounts', 'goods_receipts', 'purchase_orders', 'suppliers', 'retur_supplier', 'acc_journals', 'acc_journal_items') as $table) {
			if (!$this->db->table_exists($table)) {
				show_error('Skema pembayaran supplier belum tersedia.', 500);
			}
		}
		if (!$this->db->field_exists('status', 'purchase_orders')) {
			show_error('Skema status PO belum tersedia. Jalankan application/sql/purchasing_workflow.sql.', 500);
		}
	}

	public function index()
	{
		$status = trim((string) $this->input->get('status', TRUE));
		if (!in_array($status, array('', 'pending', 'posted', 'rejected'), TRUE)) {
			$status = '';
		}
		$supplier = trim((string) $this->input->get('supplier', TRUE));
		$receipt_number = trim((string) $this->input->get('receipt', TRUE));
		$start = trim((string) $this->input->get('start_date', TRUE));
		$end = trim((string) $this->input->get('end_date', TRUE));
		if (!$this->_is_date($start)) { $start = ''; }
		if (!$this->_is_date($end)) { $end = ''; }
		if ($start !== '' && $end !== '' && $start > $end) {
			show_error('Tanggal awal tidak boleh setelah tanggal akhir.', 400);
		}

		$data = array(
			'payments' => array(),
			'closed_po_receipts' => array(),
			'filters' => array('status' => $status, 'supplier' => $supplier, 'receipt' => $receipt_number,
				'start_date' => $start, 'end_date' => $end),
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('cash_notice'),
			'error' => $this->session->flashdata('cash_error'),
			'judul_besar' => 'Pembayaran Supplier',
			'judul_kecil' => 'Antrean pengurus · persetujuan berdasarkan nota terima',
			'm_management_payments' => TRUE
		);
		$data['css_file'] = array(
			base_url('assets/adminlte/plugins/datatables/dataTables.bootstrap.css')
		);
		$data['js_file'] = array(
			base_url('assets/adminlte/plugins/datatables/jquery.dataTables.min.js'),
			base_url('assets/adminlte/plugins/datatables/dataTables.bootstrap.min.js'),
			base_url('assets/purchasing/management_payments.js?v=20261029-server-side')
		);
		$data['output'] = $this->load->view('management_payments', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function payments_data()
	{
		$request = $this->_datatable_request();
		$filters = $this->_payment_filters();
		$base_from = "
			FROM acc_cash_transactions cash_tx
			JOIN goods_receipts receipt ON receipt.id = cash_tx.goods_receipt_id
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			JOIN users creator ON creator.id = cash_tx.created_by
			LEFT JOIN acc_accounts payment_source ON payment_source.id = cash_tx.funding_source_account_id
			LEFT JOIN users approver ON approver.id = cash_tx.approved_by
		";
		$total_row = $this->db->query(
			"SELECT COUNT(*) AS total ".$base_from." WHERE cash_tx.transaction_type = 'supplier_payment'"
		)->row();
		$where = $this->_payment_filter_sql($filters, $request['search'], $filter_params);
		$filtered_row = $this->db->query(
			"SELECT COUNT(*) AS total ".$base_from." WHERE cash_tx.transaction_type = 'supplier_payment'".$where,
			$filter_params
		)->row();

		$order_columns = array(
			0 => 'cash_tx.status',
			1 => 'cash_tx.transaction_date',
			2 => 'supplier.name',
			3 => 'cash_tx.evidence_reference',
			4 => 'cash_tx.description',
			5 => 'cash_tx.amount',
			6 => 'outstanding_after_requests',
			7 => 'creator.username'
		);
		$order_column = isset($order_columns[$request['order_column']])
			? $order_columns[$request['order_column']]
			: 'cash_tx.transaction_date';
		$sort_order = $request['order_direction'];
		$rows_sql = "
			SELECT cash_tx.id, cash_tx.transaction_number, cash_tx.transaction_date,
				cash_tx.amount, cash_tx.description, cash_tx.evidence_reference, cash_tx.proof_filename,
				cash_tx.status, cash_tx.created_by, cash_tx.approved_at, cash_tx.rejection_reason,
				receipt.purchase_order_id, receipt.receipt_number, receipt.supplier_document,
				supplier.name AS supplier_name, payment_source.code AS payment_source_code,
				payment_source.name AS payment_source_name, creator.username AS creator_name,
				approver.username AS approver_name,
				COALESCE((
					SELECT SUM(item.credit - item.debit)
					FROM acc_journals journal
					JOIN acc_journal_items item ON item.journal_id = journal.id
					JOIN acc_accounts account ON account.id = item.account_id
					WHERE journal.is_posted = 1 AND journal.reference_type = 'PURCHASING'
						AND journal.reference_id = CAST(receipt.id AS CHAR) AND account.code = '2101'
				), 0)
				- COALESCE((
					SELECT SUM(item.debit - item.credit)
					FROM retur_supplier supplier_return
					JOIN acc_journals journal ON journal.reference_type = 'SUPPLIER_RETURN'
						AND journal.reference_id = CAST(supplier_return.id AS CHAR) AND journal.is_posted = 1
					JOIN acc_journal_items item ON item.journal_id = journal.id
					JOIN acc_accounts account ON account.id = item.account_id
					WHERE supplier_return.status = 'final' AND supplier_return.goods_receipt_id = receipt.id
						AND account.code = '2101'
				), 0)
				- COALESCE((
					SELECT SUM(payment.amount) FROM acc_cash_transactions payment
					WHERE payment.goods_receipt_id = receipt.id AND payment.transaction_type = 'supplier_payment'
						AND payment.status IN ('pending','posted')
				), 0) AS outstanding_after_requests
			".$base_from."
			WHERE cash_tx.transaction_type = 'supplier_payment'".$where."
			ORDER BY ".$order_column.' '.$sort_order.', cash_tx.id DESC'.
			' LIMIT '.$request['length'].' OFFSET '.$request['start'];
		$rows = $this->db->query($rows_sql, $filter_params)->result();
		$data = array();
		foreach ($rows as $payment) {
			$data[] = array(
				'id' => (int) $payment->id,
				'status' => $payment->status,
				'transaction_date' => $payment->transaction_date,
				'supplier_name' => $payment->supplier_name,
				'receipt_number' => $payment->receipt_number,
				'purchase_order_id' => (int) $payment->purchase_order_id,
				'supplier_document' => $payment->supplier_document,
				'evidence_reference' => $payment->evidence_reference,
				'payment_source_code' => $payment->payment_source_code,
				'payment_source_name' => $payment->payment_source_name,
				'proof_filename' => $payment->proof_filename,
				'description' => $payment->description,
				'amount' => (float) $payment->amount,
				'outstanding_after_requests' => (float) $payment->outstanding_after_requests,
				'created_by' => (string) $payment->created_by,
				'creator_name' => $payment->creator_name,
				'approver_name' => $payment->approver_name,
				'approved_at' => $payment->approved_at,
				'rejection_reason' => $payment->rejection_reason,
				'action_html' => $this->_payment_action_html($payment)
			);
		}
		$this->_datatable_response($request['draw'], (int) $total_row->total, (int) $filtered_row->total, $data);
	}

	public function closed_po_data()
	{
		$request = $this->_datatable_request();
		$base_from = $this->_closed_po_balance_from();
		$balance_condition = ' AND (COALESCE(debt.amount, 0) - COALESCE(supplier_returns.amount, 0) - COALESCE(payments.amount, 0)) > 0';
		$total_row = $this->db->query(
			'SELECT COUNT(*) AS total '.$base_from.' WHERE purchase_order.status = ?'.$balance_condition,
			array('closed')
		)->row();
		$search_sql = '';
		$search_params = array('closed');
		if ($request['search'] !== '') {
			$search_sql = " AND (purchase_order.order_number LIKE ? OR receipt.receipt_number LIKE ?
				OR receipt.supplier_document LIKE ? OR supplier.name LIKE ? OR receipt.received_at LIKE ?)";
			$like = '%'.$request['search'].'%';
			$search_params = array_merge($search_params, array($like, $like, $like, $like, $like));
		}
		$filtered_row = $this->db->query(
			'SELECT COUNT(*) AS total '.$base_from.' WHERE purchase_order.status = ?'.$balance_condition.$search_sql,
			$search_params
		)->row();

		$order_columns = array(
			0 => 'purchase_order.order_number',
			1 => 'supplier.name',
			2 => 'receipt.received_at',
			3 => 'outstanding'
		);
		$order_column = isset($order_columns[$request['order_column']])
			? $order_columns[$request['order_column']]
			: 'receipt.received_at';
		$rows = $this->db->query("
			SELECT purchase_order.id AS purchase_order_id, purchase_order.order_number,
				receipt.id AS receipt_id, receipt.receipt_number, receipt.supplier_document,
				receipt.received_at, supplier.name AS supplier_name,
				COALESCE(debt.amount, 0) - COALESCE(supplier_returns.amount, 0) - COALESCE(payments.amount, 0) AS outstanding
			".$base_from."
			WHERE purchase_order.status = ?".$balance_condition.$search_sql."
			ORDER BY ".$order_column.' '.$request['order_direction'].', receipt.id DESC'.
			' LIMIT '.$request['length'].' OFFSET '.$request['start'],
			$search_params
		)->result();
		$data = array();
		foreach ($rows as $receipt) {
			$data[] = array(
				'purchase_order_id' => (int) $receipt->purchase_order_id,
				'order_number' => $receipt->order_number,
				'receipt_id' => (int) $receipt->receipt_id,
				'receipt_number' => $receipt->receipt_number,
				'supplier_document' => $receipt->supplier_document,
				'received_at' => $receipt->received_at,
				'supplier_name' => $receipt->supplier_name,
				'outstanding' => (float) $receipt->outstanding
			);
		}
		$this->_datatable_response($request['draw'], (int) $total_row->total, (int) $filtered_row->total, $data);
	}

	public function request()
	{
		$receipts = $this->_get_outstanding_receipts();
		$requested_receipt_id = $this->_positive_id($this->input->get('goods_receipt_id', TRUE));
		$selected_receipt_id = NULL;
		foreach ($receipts as $receipt) {
			if ((int) $receipt->id === $requested_receipt_id) {
				$selected_receipt_id = $requested_receipt_id;
				break;
			}
		}
		$accounts = $this->_get_payment_source_accounts();
		$data = array(
			'receipts' => $receipts,
			'accounts' => $accounts,
			'selected_receipt_id' => $selected_receipt_id,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('cash_notice'),
			'error' => $this->session->flashdata('cash_error'),
			'judul_besar' => 'Pembayaran Supplier',
			'judul_kecil' => 'Pengajuan pembayaran baru',
			'm_management_payments' => TRUE
		);
		$data['js_file'] = array(
			base_url('assets/purchasing/payment_request.js')
		);
		$data['output'] = $this->load->view('management_payment_request', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function submit_request()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$expected_token = $this->session->userdata('petty_cash_form_token');
		$posted_token = $this->input->post('form_token', TRUE);
		if (!is_string($expected_token) || $expected_token === '' ||
			!is_string($posted_token) || $posted_token !== $expected_token) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}

		$receipt_id = $this->_positive_id($this->input->post('goods_receipt_id', TRUE));
		$source_account_id = $this->_positive_id($this->input->post('source_account_id', TRUE));
		$date = trim((string) $this->input->post('transaction_date', TRUE));
		$amount = $this->_parse_amount($this->input->post('amount', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$evidence = trim((string) $this->input->post('evidence_reference', TRUE));
		$receipt = $receipt_id ? $this->db->get_where('goods_receipts', array('id' => $receipt_id))->row() : NULL;
		$source_account = $source_account_id ? $this->db->get_where('acc_accounts', array(
			'id' => $source_account_id,
			'is_active' => 1,
			'type' => 'asset',
			'normal_balance' => 'debit'
		))->row() : NULL;
		$allowed_source_codes = array('1101', '1102', '1106', '1107');
		$fund = $source_account && in_array($source_account->code, $allowed_source_codes, TRUE)
			? $this->_fund_for_payment_source($source_account->code)
			: NULL;
		if (!$receipt || !$source_account || !$fund ||
			!in_array($source_account->code, $allowed_source_codes, TRUE) ||
			!$this->_is_date($date) || $date > date('Y-m-d') ||
			$amount === FALSE || $amount <= 0 ||
			$description === '' || strlen($description) > 500 ||
			$evidence === '' || strlen($evidence) > 128) {
			$this->_payment_message('Nota terima, akun sumber, tanggal, nominal, keterangan, atau referensi bukti tidak valid.', FALSE);
			return;
		}
		if ($this->_fund_is_closed((int) $fund->id, $date)) {
			$this->_payment_message('Tanggal pembayaran berada pada atau sebelum tanggal tutup dana yang dipilih.', FALSE);
			return;
		}

		$proof_filename = $this->_save_payment_proof();
		if ($proof_filename === FALSE) {
			return;
		}

		$transaction_number = 'PAY-'.date('Ymd-His').'-'.strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 8));
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_discard_payment_proof($proof_filename);
			$this->_payment_message('Database tidak dapat memulai pengajuan pembayaran.', FALSE);
			return;
		}
		$locked_receipt = $this->db->query(
			'SELECT id FROM goods_receipts WHERE id = ? FOR UPDATE',
			array($receipt_id)
		)->row();
		$locked_source = $this->db->query(
			'SELECT id, code FROM acc_accounts WHERE id = ? AND is_active = 1 AND type = ? AND normal_balance = ? FOR UPDATE',
			array($source_account_id, 'asset', 'debit')
		)->row();
		$locked_fund = $this->db->query(
			'SELECT id FROM acc_cash_funds WHERE id = ? AND is_active = 1 FOR UPDATE',
			array((int) $fund->id)
		)->row();
		$outstanding = $locked_receipt ? $this->_receipt_outstanding($receipt_id) : FALSE;
		if (!$locked_source || !in_array($locked_source->code, $allowed_source_codes, TRUE) ||
			!$locked_fund || !$locked_receipt || $outstanding === FALSE ||
			$outstanding + 0.0001 < $amount || $this->_fund_is_closed((int) $fund->id, $date)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_discard_payment_proof($proof_filename);
			$this->_payment_message('Sisa hutang berubah, akun sumber tidak aktif, atau tanggal pembayaran sudah ditutup. Periksa kembali sebelum mengajukan.', FALSE);
			return;
		}

		$supplier = $this->db->select('supplier.name')
			->from('goods_receipts receipt')
			->join('purchase_orders purchase_order', 'purchase_order.id = receipt.purchase_order_id')
			->join('suppliers supplier', 'supplier.id = purchase_order.supplier_id')
			->where('receipt.id', $receipt_id)
			->get()->row();
		$inserted = $supplier && $this->db->insert('acc_cash_transactions', array(
			'transaction_number' => $transaction_number,
			'fund_id' => (int) $fund->id,
			'transaction_type' => 'supplier_payment',
			'transaction_date' => $date,
			'amount' => $amount,
			'funding_source_account_id' => $source_account_id,
			'goods_receipt_id' => $receipt_id,
			'counterparty' => $supplier->name,
			'description' => $description,
			'evidence_reference' => $evidence,
			'proof_filename' => $proof_filename,
			'status' => 'pending',
			'created_by' => (string) $this->ion_auth->get_user_id(),
			'created_at' => date('Y-m-d H:i:s')
		));
		if (!$inserted || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_discard_payment_proof($proof_filename);
			log_message('error', 'Gagal menyimpan pengajuan pembayaran supplier '.$transaction_number.': '.$error['code'].' '.$error['message']);
			$this->_payment_message('Pengajuan pembayaran gagal disimpan.', FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_payment_message('Pengajuan pembayaran tersimpan dan menunggu persetujuan. Jurnal akan mendebit hutang supplier 2101 dan mengkredit akun sumber yang dipilih.', TRUE);
	}

	private function _payment_filters()
	{
		$status = $this->input->get('status', TRUE);
		$supplier = $this->input->get('supplier', TRUE);
		$receipt = $this->input->get('receipt', TRUE);
		$start = $this->input->get('start_date', TRUE);
		$end = $this->input->get('end_date', TRUE);
		return array(
			'status' => is_string($status) && in_array($status, array('pending', 'posted', 'rejected'), TRUE) ? $status : '',
			'supplier' => is_string($supplier) ? substr(trim($supplier), 0, 64) : '',
			'receipt' => is_string($receipt) ? substr(trim($receipt), 0, 32) : '',
			'start_date' => $this->_is_date($start) ? $start : '',
			'end_date' => $this->_is_date($end) ? $end : ''
		);
	}

	private function _payment_filter_sql($filters, $search, &$params)
	{
		$sql = '';
		if ($filters['status'] !== '') {
			$sql .= ' AND cash_tx.status = ?';
			$params[] = $filters['status'];
		}
		if ($filters['supplier'] !== '') {
			$sql .= ' AND supplier.name LIKE ?';
			$params[] = '%'.$filters['supplier'].'%';
		}
		if ($filters['receipt'] !== '') {
			$sql .= ' AND receipt.receipt_number LIKE ?';
			$params[] = '%'.$filters['receipt'].'%';
		}
		if ($filters['start_date'] !== '') {
			$sql .= ' AND cash_tx.transaction_date >= ?';
			$params[] = $filters['start_date'];
		}
		if ($filters['end_date'] !== '') {
			$sql .= ' AND cash_tx.transaction_date <= ?';
			$params[] = $filters['end_date'];
		}
		if ($search !== '') {
			$search_columns = array(
				'cash_tx.transaction_number', 'cash_tx.status', 'cash_tx.transaction_date',
				'supplier.name', 'receipt.receipt_number', 'cash_tx.evidence_reference',
				'payment_source.code', 'payment_source.name', 'cash_tx.description',
				'creator.username', 'approver.username', 'cash_tx.rejection_reason'
			);
			$sql .= ' AND ('.implode(' LIKE ? OR ', $search_columns).' LIKE ?)';
			foreach ($search_columns as $column) {
				$params[] = '%'.$search.'%';
			}
		}
		return $sql;
	}

	private function _datatable_request()
	{
		if ($this->input->method(TRUE) !== 'GET') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$order = $this->input->get('order');
		$search_data = $this->input->get('search');
		$search = is_array($search_data) && isset($search_data['value']) && is_string($search_data['value'])
			? substr(trim($search_data['value']), 0, 100)
			: '';
		$order_column = is_array($order) && isset($order[0]) && is_array($order[0]) &&
			isset($order[0]['column'])
			? $this->_datatable_integer($order[0]['column'], 0, 0, 20)
			: 0;
		$order_direction = is_array($order) && isset($order[0]) && is_array($order[0]) &&
			isset($order[0]['dir']) && is_string($order[0]['dir']) &&
			strtolower($order[0]['dir']) === 'asc' ? 'ASC' : 'DESC';
		return array(
			'draw' => $this->_datatable_integer($this->input->get('draw'), 0, 0, 2147483647),
			'start' => $this->_datatable_integer($this->input->get('start'), 0, 0, 10000000),
			'length' => $this->_datatable_integer($this->input->get('length'), 25, 1, 100),
			'search' => $search,
			'order_column' => $order_column,
			'order_direction' => $order_direction
		);
	}

	private function _datatable_integer($value, $default, $minimum, $maximum)
	{
		if (!is_string($value) && !is_int($value)) {
			return $default;
		}
		$value = (string) $value;
		if (!ctype_digit($value)) {
			return $default;
		}
		return max($minimum, min($maximum, (int) $value));
	}

	private function _datatable_response($draw, $total, $filtered, $rows)
	{
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: no-store, private')
			->set_output(json_encode(array(
				'draw' => $draw,
				'recordsTotal' => $total,
				'recordsFiltered' => $filtered,
				'data' => $rows
			)));
	}

	private function _payment_action_html($payment)
	{
		if ($payment->status === 'pending' &&
			(string) $payment->created_by !== (string) $this->ion_auth->get_user_id()) {
			$token = html_escape($this->_form_token());
			$id = (int) $payment->id;
			$approve_url = site_url('main/petty_cash/approve/'.$id);
			$reject_url = site_url('main/petty_cash/reject/'.$id);
			return '<form method="post" action="'.html_escape($approve_url).'" style="margin-bottom:5px;" onsubmit="return window.confirm(\'Setujui pembayaran hutang supplier ini? Jurnal akan diposting dan saldo hutang berkurang.\');">'.
				'<input type="hidden" name="return_to" value="main/management_payments"><input type="hidden" name="form_token" value="'.$token.'">'.
				'<button class="btn btn-xs btn-success" type="submit">Setujui &amp; posting</button></form>'.
				'<form method="post" action="'.html_escape($reject_url).'">'.
				'<input type="hidden" name="return_to" value="main/management_payments"><input type="hidden" name="form_token" value="'.$token.'">'.
				'<div class="input-group input-group-sm"><input class="form-control" name="rejection_reason" maxlength="500" placeholder="Alasan penolakan" required><span class="input-group-btn"><button class="btn btn-danger" type="submit">Tolak</button></span></div></form>';
		}
		if ($payment->status === 'pending') {
			return '<span class="text-muted">Pengaju tidak dapat menyetujui sendiri.</span>';
		}
		if ($payment->status === 'rejected' && $payment->rejection_reason) {
			return '<span class="text-danger">'.html_escape($payment->rejection_reason).'</span>';
		}
		return '<span class="text-muted">—</span>';
	}

	private function _closed_po_balance_from()
	{
		return "
			FROM purchase_orders purchase_order
			JOIN goods_receipts receipt ON receipt.purchase_order_id = purchase_order.id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			LEFT JOIN (
				SELECT journal.reference_id, SUM(item.credit - item.debit) AS amount
				FROM acc_journals journal
				JOIN acc_journal_items item ON item.journal_id = journal.id
				JOIN acc_accounts account ON account.id = item.account_id
				JOIN goods_receipts eligible_receipt ON journal.reference_id = CAST(eligible_receipt.id AS CHAR)
				JOIN purchase_orders eligible_order ON eligible_order.id = eligible_receipt.purchase_order_id
				WHERE journal.is_posted = 1 AND journal.reference_type = 'PURCHASING' AND account.code = '2101'
					AND eligible_order.status = 'closed'
				GROUP BY journal.reference_id
			) debt ON debt.reference_id = CAST(receipt.id AS CHAR)
			LEFT JOIN (
				SELECT supplier_return.goods_receipt_id, SUM(item.debit - item.credit) AS amount
				FROM retur_supplier supplier_return
				JOIN acc_journals journal ON journal.reference_type = 'SUPPLIER_RETURN' AND
					journal.reference_id = CAST(supplier_return.id AS CHAR) AND journal.is_posted = 1
				JOIN acc_journal_items item ON item.journal_id = journal.id
				JOIN acc_accounts account ON account.id = item.account_id
				JOIN goods_receipts eligible_receipt ON eligible_receipt.id = supplier_return.goods_receipt_id
				JOIN purchase_orders eligible_order ON eligible_order.id = eligible_receipt.purchase_order_id
				WHERE supplier_return.status = 'final' AND account.code = '2101'
					AND eligible_order.status = 'closed'
				GROUP BY supplier_return.goods_receipt_id
			) supplier_returns ON supplier_returns.goods_receipt_id = receipt.id
			LEFT JOIN (
				SELECT payment.goods_receipt_id, SUM(payment.amount) AS amount
				FROM acc_cash_transactions payment
				JOIN goods_receipts eligible_receipt ON eligible_receipt.id = payment.goods_receipt_id
				JOIN purchase_orders eligible_order ON eligible_order.id = eligible_receipt.purchase_order_id
				WHERE payment.transaction_type = 'supplier_payment' AND payment.status IN ('pending', 'posted')
					AND eligible_order.status = 'closed'
				GROUP BY payment.goods_receipt_id
			) payments ON payments.goods_receipt_id = receipt.id
		";
	}

	private function _get_outstanding_receipts()
	{
		return $this->db->query("
			SELECT receipt.id, receipt.receipt_number, receipt.supplier_document,
				receipt.received_at, purchase_order.order_number,
				supplier.name AS supplier_name, purchase_order.status AS purchase_order_status,
				COALESCE((
					SELECT SUM(item.credit - item.debit)
					FROM acc_journals journal
					JOIN acc_journal_items item ON item.journal_id = journal.id
					JOIN acc_accounts account ON account.id = item.account_id
					WHERE journal.is_posted = 1 AND journal.reference_type = 'PURCHASING'
						AND journal.reference_id = CAST(receipt.id AS CHAR)
						AND account.code = '2101'
				), 0)
				- COALESCE((
					SELECT SUM(item.debit - item.credit)
					FROM retur_supplier supplier_return
					JOIN acc_journals journal ON journal.reference_type = 'SUPPLIER_RETURN'
						AND journal.reference_id = CAST(supplier_return.id AS CHAR)
						AND journal.is_posted = 1
					JOIN acc_journal_items item ON item.journal_id = journal.id
					JOIN acc_accounts account ON account.id = item.account_id
					WHERE supplier_return.status = 'final'
						AND supplier_return.goods_receipt_id = receipt.id
						AND account.code = '2101'
				), 0)
				- COALESCE((
					SELECT SUM(payment.amount)
					FROM acc_cash_transactions payment
					WHERE payment.goods_receipt_id = receipt.id
						AND payment.transaction_type = 'supplier_payment'
						AND payment.status IN ('pending', 'posted')
				), 0) AS outstanding
			FROM goods_receipts receipt
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			HAVING outstanding > 0
			ORDER BY receipt.received_at DESC, receipt.id DESC
			LIMIT 500
		")->result();
	}

	private function _get_payment_source_accounts()
	{
		return $this->db->where_in('code', array('1101', '1102', '1106', '1107'))
			->where('is_active', 1)
			->where('type', 'asset')
			->where('normal_balance', 'debit')
			->order_by('code', 'ASC')
			->get('acc_accounts')->result();
	}

	private function _fund_for_payment_source($account_code)
	{
		$fund_type = $account_code === '1101' ? 'cashier' :
			($account_code === '1107' ? 'petty' : 'main');
		$this->db->where('fund_type', $fund_type)->where('is_active', 1);
		if ($fund_type === 'cashier') {
			$this->db->order_by('fund_slot', 'DESC');
		}
		return $this->db->order_by('id', 'ASC')->limit(1)->get('acc_cash_funds')->row();
	}

	private function _receipt_outstanding($receipt_id)
	{
		$receipt = $this->db->query("
			SELECT COALESCE(SUM(item.credit - item.debit), 0) AS amount
			FROM acc_journals journal
			JOIN acc_journal_items item ON item.journal_id = journal.id
			JOIN acc_accounts account ON account.id = item.account_id
			WHERE journal.is_posted = 1 AND journal.reference_type = 'PURCHASING'
				AND journal.reference_id = CAST(? AS CHAR) AND account.code = '2101'
		", array((int) $receipt_id))->row();
		$returns = $this->db->query("
			SELECT COALESCE(SUM(item.debit - item.credit), 0) AS amount
			FROM retur_supplier supplier_return
			JOIN acc_journals journal ON journal.reference_type = 'SUPPLIER_RETURN'
				AND journal.reference_id = CAST(supplier_return.id AS CHAR)
				AND journal.is_posted = 1
			JOIN acc_journal_items item ON item.journal_id = journal.id
			JOIN acc_accounts account ON account.id = item.account_id
			WHERE supplier_return.status = 'final'
				AND supplier_return.goods_receipt_id = ? AND account.code = '2101'
		", array((int) $receipt_id))->row();
		$payments = $this->db->query("
			SELECT COALESCE(SUM(amount), 0) AS amount
			FROM acc_cash_transactions
			WHERE goods_receipt_id = ? AND transaction_type = 'supplier_payment'
				AND status IN ('pending', 'posted')
		", array((int) $receipt_id))->row();
		if (!$receipt || !$returns || !$payments) {
			return FALSE;
		}
		return round((float) $receipt->amount - (float) $returns->amount - (float) $payments->amount, 2);
	}

	private function _fund_is_closed($fund_id, $date)
	{
		$closing = $this->db->where('fund_id', (int) $fund_id)
			->order_by('closing_date', 'DESC')
			->limit(1)
			->get('acc_cash_closings')->row();
		return $closing && $date <= $closing->closing_date;
	}

	private function _save_payment_proof()
	{
		if (!isset($_FILES['proof_file']) || !is_array($_FILES['proof_file']) ||
			!isset($_FILES['proof_file']['error']) ||
			(int) $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
			$this->_payment_message('Lampirkan bukti berupa PDF, JPG, JPEG, atau PNG (maksimal 5 MB).', FALSE);
			return FALSE;
		}
		$directory = APPPATH.'cache'.DIRECTORY_SEPARATOR.'petty_cash_proofs'.DIRECTORY_SEPARATOR;
		if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
			log_message('error', 'Direktori bukti pembayaran tidak dapat dibuat: '.$directory);
			$this->_payment_message('Penyimpanan bukti pembayaran tidak tersedia. Hubungi administrator.', FALSE);
			return FALSE;
		}
		$this->load->library('upload');
		$this->upload->initialize(array(
			'upload_path' => $directory,
			'allowed_types' => 'pdf|jpg|jpeg|png',
			'max_size' => 5120,
			'encrypt_name' => TRUE,
			'remove_spaces' => TRUE,
			'detect_mime' => TRUE,
			'mod_mime_fix' => TRUE
		), TRUE);
		if (!$this->upload->do_upload('proof_file')) {
			$error = strip_tags($this->upload->display_errors('', ''));
			$this->_payment_message($error, FALSE);
			return FALSE;
		}
		$file = $this->upload->data();
		return basename($file['file_name']);
	}

	private function _discard_payment_proof($filename)
	{
		if (!$filename) {
			return;
		}
		$path = APPPATH.'cache'.DIRECTORY_SEPARATOR.'petty_cash_proofs'.DIRECTORY_SEPARATOR.basename($filename);
		if (is_file($path) && !unlink($path)) {
			log_message('error', 'Bukti pembayaran sementara gagal dihapus: '.$path);
		}
	}

	private function _payment_message($message, $success)
	{
		$this->session->set_flashdata($success ? 'cash_notice' : 'cash_error', $message);
		redirect('main/management_payments');
	}

	private function _positive_id($value)
	{
		return is_string($value) && ctype_digit($value) && (int) $value > 0
			? (int) $value
			: FALSE;
	}

	private function _parse_amount($value)
	{
		if (!is_string($value) || !preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value)) {
			return FALSE;
		}
		$amount = round((float) $value, 2);
		return $amount <= 9999999999999.99 ? $amount : FALSE;
	}

	private function _is_date($value)
	{
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return FALSE;
		}
		$date = DateTime::createFromFormat('!Y-m-d', $value);
		return $date && $date->format('Y-m-d') === $value;
	}

	private function _form_token()
	{
		$token = $this->session->userdata('petty_cash_form_token');
		if (!is_string($token) || $token === '') {
			$this->load->helper('string');
			$token = random_string('alnum', 32);
			$this->session->set_userdata('petty_cash_form_token', $token);
		}
		return $token;
	}

}