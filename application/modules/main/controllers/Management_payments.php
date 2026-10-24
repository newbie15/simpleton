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
		foreach (array('acc_cash_transactions', 'goods_receipts', 'suppliers', 'retur_supplier', 'acc_journals', 'acc_journal_items') as $table) {
			if (!$this->db->table_exists($table)) {
				show_error('Skema pembayaran supplier belum tersedia.', 500);
			}
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

		$sql = "
			SELECT cash_tx.id, cash_tx.transaction_number, cash_tx.transaction_date,
				cash_tx.amount, cash_tx.description, cash_tx.evidence_reference, cash_tx.proof_filename,
				cash_tx.status, cash_tx.created_by, cash_tx.created_at,
				cash_tx.approved_by, cash_tx.approved_at, cash_tx.rejection_reason,
				cash_tx.counterparty, fund.fund_code, fund.name AS fund_name,
				receipt.id AS receipt_id, receipt.purchase_order_id, receipt.receipt_number, receipt.supplier_document,
				supplier.name AS supplier_name, creator.username AS creator_name,
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
			FROM acc_cash_transactions cash_tx
			JOIN acc_cash_funds fund ON fund.id = cash_tx.fund_id
			JOIN goods_receipts receipt ON receipt.id = cash_tx.goods_receipt_id
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			JOIN users creator ON creator.id = cash_tx.created_by
			LEFT JOIN users approver ON approver.id = cash_tx.approved_by
			WHERE cash_tx.transaction_type = 'supplier_payment'
		";
		$params = array();
		if ($status !== '') {
			$sql .= ' AND cash_tx.status = ?';
			$params[] = $status;
		}
		if ($supplier !== '' && strlen($supplier) <= 64) {
			$sql .= ' AND supplier.name LIKE ?';
			$params[] = '%'.$supplier.'%';
		}
		if ($receipt_number !== '' && strlen($receipt_number) <= 32) {
			$sql .= ' AND receipt.receipt_number LIKE ?';
			$params[] = '%'.$receipt_number.'%';
		}
		if ($start !== '') {
			$sql .= ' AND cash_tx.transaction_date >= ?';
			$params[] = $start;
		}
		if ($end !== '') {
			$sql .= ' AND cash_tx.transaction_date <= ?';
			$params[] = $end;
		}
		$sql .= ' ORDER BY cash_tx.transaction_date DESC, cash_tx.id DESC LIMIT 500';
		$data = array(
			'payments' => $this->db->query($sql, $params)->result(),
			'filters' => array('status' => $status, 'supplier' => $supplier, 'receipt' => $receipt_number,
				'start_date' => $start, 'end_date' => $end),
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('cash_notice'),
			'error' => $this->session->flashdata('cash_error'),
			'judul_besar' => 'Pembayaran Supplier',
			'judul_kecil' => 'Antrean pengurus · persetujuan berdasarkan nota terima',
			'm_management_payments' => TRUE
		);
		$data['output'] = $this->load->view('management_payments', $data, TRUE);
		$this->load->view('welcome_message', $data);
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

	private function _is_date($value)
	{
		$date = DateTime::createFromFormat('Y-m-d', (string) $value);
		return $date && $date->format('Y-m-d') === $value;
	}
}