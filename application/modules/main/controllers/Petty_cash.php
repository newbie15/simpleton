<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Petty_cash extends CI_Controller
{
	private $approver_groups = array('admin', 'management', 'pengurus');

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form'));
		$this->load->library(array('session', 'ion_auth', 'page_access'));

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('petty_cash')) {
			show_error('Anda tidak memiliki akses ke pengelolaan kas.', 403);
		}
		if (!$this->db->table_exists('acc_cash_funds') ||
			!$this->db->table_exists('acc_cash_transactions') ||
			!$this->db->table_exists('acc_cash_closings') ||
			!$this->db->table_exists('goods_receipts') ||
			!$this->db->table_exists('suppliers')) {
			show_error('Skema kas atau purchasing belum tersedia. Jalankan application/sql/petty_cash.sql setelah phase3_foundation.sql dan purchasing.sql.', 500);
		}
	}

	public function index()
	{
		$data['funds'] = $this->db->query("
			SELECT fund.id, fund.fund_code, fund.name, fund.fund_type,
				fund.custodian_user_id, fund.imprest_limit, fund.is_active,
				custodian.username AS custodian_username
			FROM acc_cash_funds fund
			LEFT JOIN users custodian ON custodian.id = fund.custodian_user_id
			ORDER BY fund.is_active DESC, fund.fund_type ASC, fund.name ASC
		")->result();
		foreach ($data['funds'] as $fund) {
			$fund->balance = $this->_fund_balance((int) $fund->id, date('Y-m-d'));
		}
		$report_date = trim((string) $this->input->get('report_date', TRUE));
		if (!$this->_is_date($report_date) || $report_date > date('Y-m-d')) {
			$report_date = date('Y-m-d');
		}
		$requested_fund_id = $this->_positive_id($this->input->get('fund_id', TRUE));
		$daily_fund = NULL;
		foreach ($data['funds'] as $fund) {
			if ((int) $fund->is_active === 1 &&
				($requested_fund_id === FALSE || (int) $fund->id === $requested_fund_id)) {
				$daily_fund = $fund;
				break;
			}
		}
		if (!$daily_fund) {
			foreach ($data['funds'] as $fund) {
				if ((int) $fund->is_active === 1) {
					$daily_fund = $fund;
					break;
				}
			}
		}
		$daily_opening = 0;
		$daily_balance = 0;
		$daily_rows = array();
		if ($daily_fund) {
			$account_code = $this->_fund_account_code($daily_fund->fund_type);
			$opening_sql = "
				SELECT COALESCE(SUM(item.debit - item.credit), 0) AS balance
				FROM acc_journal_items item
				JOIN acc_journals journal ON journal.id = item.journal_id
				JOIN acc_accounts account ON account.id = item.account_id
				LEFT JOIN acc_cash_transactions cash_tx
					ON journal.reference_type = 'PETTY_CASH'
					AND journal.reference_id = CAST(cash_tx.id AS CHAR)
				WHERE journal.is_posted = 1 AND journal.date < ? AND account.code = ?
			";
			$daily_sql = "
				SELECT journal.date, journal.journal_number, journal.reference_type,
					journal.reference_id, journal.description, item.memo, item.debit, item.credit,
					cash_tx.transaction_number, cash_tx.transaction_type, cash_tx.counterparty
				FROM acc_journal_items item
				JOIN acc_journals journal ON journal.id = item.journal_id
				JOIN acc_accounts account ON account.id = item.account_id
				LEFT JOIN acc_cash_transactions cash_tx
					ON journal.reference_type = 'PETTY_CASH'
					AND journal.reference_id = CAST(cash_tx.id AS CHAR)
				WHERE journal.is_posted = 1 AND journal.date = ? AND account.code = ?
			";
			$opening_params = array($report_date, $account_code);
			$daily_params = array($report_date, $account_code);
			if ($daily_fund->fund_type === 'main' || $daily_fund->fund_type === 'petty') {
				$opening_sql .= " AND cash_tx.fund_id = ? AND journal.reference_type = 'PETTY_CASH'";
				$daily_sql .= " AND cash_tx.fund_id = ? AND journal.reference_type = 'PETTY_CASH'";
				$opening_params[] = (int) $daily_fund->id;
				$daily_params[] = (int) $daily_fund->id;
			}
			$daily_sql .= ' ORDER BY journal.id ASC, item.id ASC';
			$opening = $this->db->query($opening_sql, $opening_params)->row();
			$daily_opening = $opening ? (float) $opening->balance : 0;
			$daily_balance = $daily_opening;
			$daily_rows = $this->db->query($daily_sql, $daily_params)->result();
			foreach ($daily_rows as $row) {
				$daily_balance += (float) $row->debit - (float) $row->credit;
				$row->running_balance = $daily_balance;
			}
		}
		$data['transactions'] = $this->db->query("
			SELECT cash_tx.id, cash_tx.transaction_number, cash_tx.transaction_date,
				cash_tx.transaction_type, cash_tx.amount, cash_tx.counterparty,
				cash_tx.description, cash_tx.evidence_reference,
				cash_tx.proof_filename, cash_tx.status, cash_tx.created_by,
				cash_tx.created_at, cash_tx.approved_by, cash_tx.approved_at,
				cash_tx.rejection_reason, fund.fund_code, fund.name AS fund_name,
				creator.username AS creator_name, approver.username AS approver_name,
				source.code AS source_code, source.name AS source_name,
				expense.code AS expense_code, expense.name AS expense_name,
				receipt.receipt_number
			FROM acc_cash_transactions cash_tx
			JOIN acc_cash_funds fund ON fund.id = cash_tx.fund_id
			JOIN users creator ON creator.id = cash_tx.created_by
			LEFT JOIN users approver ON approver.id = cash_tx.approved_by
			LEFT JOIN acc_accounts source ON source.id = cash_tx.funding_source_account_id
			LEFT JOIN acc_accounts expense ON expense.id = cash_tx.expense_account_id
			LEFT JOIN goods_receipts receipt ON receipt.id = cash_tx.goods_receipt_id
			ORDER BY cash_tx.id DESC
			LIMIT 100
		")->result();
		$data['closings'] = $this->db->query("
			SELECT closing.*, fund.fund_code, fund.name AS fund_name,
				close_user.username AS closed_by_name
			FROM acc_cash_closings closing
			JOIN acc_cash_funds fund ON fund.id = closing.fund_id
			JOIN users close_user ON close_user.id = closing.closed_by
			ORDER BY closing.closing_date DESC, closing.id DESC
			LIMIT 60
		")->result();
		$data['petty_funds'] = $this->db->where('fund_type', 'petty')
			->where('is_active', 1)->order_by('name', 'ASC')->get('acc_cash_funds')->result();
		$data['report_date'] = $report_date;
		$data['daily_fund'] = $daily_fund;
		$data['daily_opening'] = $daily_opening;
		$data['daily_balance'] = $daily_balance;
		$data['daily_rows'] = $daily_rows;
		$data['expense_accounts'] = $this->db->where('type', 'expense')
			->where('is_active', 1)->order_by('code', 'ASC')->get('acc_accounts')->result();
		$data['funding_accounts'] = $this->db->where_in('code', array('1101', '1102', '1106'))
			->where('is_active', 1)->order_by('code', 'ASC')->get('acc_accounts')->result();
		$data['supplier_receipts'] = $this->_get_outstanding_receipts();
		$data['users'] = $this->db->select('id, username, first_name, last_name')
			->where('active', 1)->order_by('username', 'ASC')->get('users')->result();
		$data['is_approver'] = $this->_is_approver();
		$data['form_token'] = $this->_form_token();
		$data['notice'] = $this->session->flashdata('cash_notice');
		$data['error'] = $this->session->flashdata('cash_error');
		$data['judul_besar'] = 'Kas & Petty Cash';
		$data['judul_kecil'] = 'Dana tetap, bukti transaksi, persetujuan, dan tutup kas harian';
		$data['m_petty_cash'] = TRUE;
		$data['output'] = $this->load->view('petty_cash', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function create_fund()
	{
		$this->_require_post();
		if (!$this->_is_approver()) {
			show_error('Hanya admin, management, atau pengurus yang dapat membuat dana kas.', 403);
		}
		$code = strtoupper(trim((string) $this->input->post('fund_code', TRUE)));
		$name = trim((string) $this->input->post('name', TRUE));
		$type = trim((string) $this->input->post('fund_type', TRUE));
		$custodian = trim((string) $this->input->post('custodian_user_id', TRUE));
		$limit = $this->_parse_amount($this->input->post('imprest_limit', TRUE));
		if (!preg_match('/^[A-Z0-9_-]{2,32}$/', $code) || $name === '' ||
			strlen($name) > 100 || !in_array($type, array('main', 'petty'), TRUE) ||
			$limit === FALSE || ($type === 'petty' && $limit <= 0) ||
			($type === 'main' && $limit < 0)) {
			$this->_message('Kode, nama, jenis dana, atau batas imprest tidak valid.', FALSE);
			return;
		}
		if ($type === 'main') {
			$limit = 0;
		}
		if ($custodian !== '' && !$this->db->where('id', $custodian)
			->where('active', 1)->count_all_results('users')) {
			$this->_message('Pemegang dana tidak ditemukan atau tidak aktif.', FALSE);
			return;
		}
		if ($type === 'main' && $this->db->where('fund_slot', 'main')
			->count_all_results('acc_cash_funds') > 0) {
			$this->_message('Dana kas utama hanya boleh dibuat satu kali.', FALSE);
			return;
		}

		$inserted = $this->db->insert('acc_cash_funds', array(
			'fund_code' => $code,
			'name' => $name,
			'fund_type' => $type,
			'fund_slot' => $type === 'main' ? 'main' : NULL,
			'custodian_user_id' => $custodian === '' ? NULL : $custodian,
			'imprest_limit' => $type === 'petty' ? $limit : 0,
			'is_active' => 1,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => (string) $this->ion_auth->get_user_id()
		));
		if (!$inserted) {
			$error = $this->db->error();
			log_message('error', 'Gagal membuat dana kas '.$code.': '.$error['code'].' '.$error['message']);
			$this->_message((int) $error['code'] === 1062
				? ($type === 'main' ? 'Dana kas utama sudah dibuat.' : 'Kode dana sudah digunakan.')
				: 'Dana kas gagal disimpan.', FALSE);
			return;
		}
		$this->_message('Dana kas berhasil dibuat. Catat setoran awal sebagai permintaan transfer agar saldo memiliki jurnal.', TRUE);
	}

	public function submit_transaction()
	{
		$this->_require_post();
		$fund_id = $this->_positive_id($this->input->post('fund_id', TRUE));
		$type = trim((string) $this->input->post('transaction_type', TRUE));
		$date = trim((string) $this->input->post('transaction_date', TRUE));
		$amount = $this->_parse_amount($this->input->post('amount', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$counterparty = trim((string) $this->input->post('counterparty', TRUE));
		$evidence = trim((string) $this->input->post('evidence_reference', TRUE));
		$fund = $fund_id ? $this->_get_fund($fund_id) : NULL;
		if (!$fund || (int) $fund->is_active !== 1 ||
			!in_array($type, array('funding', 'expense', 'supplier_payment'), TRUE) ||
			!$this->_is_date($date) || $date > date('Y-m-d') ||
			$amount === FALSE || $amount <= 0 || $description === '' ||
			strlen($description) > 500 || strlen($counterparty) > 128 ||
			$evidence === '' || strlen($evidence) > 128) {
			$this->_message('Tanggal, dana, nominal, keterangan, atau referensi bukti tidak valid.', FALSE);
			return;
		}
		if ($fund->fund_type === 'petty' &&
			in_array($type, array('expense', 'supplier_payment'), TRUE) &&
			$fund->custodian_user_id !== NULL &&
			(string) $fund->custodian_user_id !== (string) $this->ion_auth->get_user_id()) {
			$this->_message('Pengeluaran dana ini hanya dapat diajukan oleh pemegang petty cash yang ditetapkan.', FALSE);
			return;
		}
		if ($this->_is_after_closing($fund_id, $date)) {
			$this->_message('Tanggal transaksi berada pada atau sebelum tanggal kas yang sudah ditutup.', FALSE);
			return;
		}

		$source_account_id = NULL;
		$expense_account_id = NULL;
		$goods_receipt_id = NULL;
		if ($type === 'funding') {
			if (!in_array($fund->fund_type, array('main', 'petty'), TRUE)) {
				$this->_message('Kasir POS tidak menerima penambahan dana melalui formulir kas.', FALSE);
				return;
			}
			$source_account_id = $this->_positive_id($this->input->post('funding_source_account_id', TRUE));
			$source = $source_account_id ? $this->db->get_where('acc_accounts', array(
				'id' => $source_account_id,
				'is_active' => 1,
				'type' => 'asset',
				'normal_balance' => 'debit'
			))->row() : NULL;
			$allowed_sources = $fund->fund_type === 'main'
				? array('1101', '1102')
				: array('1106');
			if (!$source || !in_array($source->code, $allowed_sources, TRUE)) {
				$this->_message('Sumber dana tidak sesuai dengan jenis kas yang akan diisi.', FALSE);
				return;
			}
		} elseif ($type === 'expense') {
			$expense_account_id = $this->_positive_id($this->input->post('expense_account_id', TRUE));
			$expense_account = $expense_account_id ? $this->db->get_where('acc_accounts', array(
				'id' => $expense_account_id,
				'type' => 'expense',
				'is_active' => 1
			))->row() : NULL;
			if ($fund->fund_type !== 'petty' || !$expense_account) {
				$this->_message('Pengeluaran hanya dapat dicatat dari petty cash dengan akun beban aktif.', FALSE);
				return;
			}
		} else {
			$goods_receipt_id = $this->_positive_id($this->input->post('goods_receipt_id', TRUE));
			$receipt = $goods_receipt_id ? $this->_get_receipt($goods_receipt_id) : NULL;
			if (!$receipt || $this->_receipt_outstanding($goods_receipt_id, TRUE) + 0.0001 < $amount) {
				$this->_message('Penerimaan supplier tidak ditemukan atau saldo hutangnya tidak mencukupi.', FALSE);
				return;
			}
			$counterparty = $receipt->supplier_name;
		}

		$proof_filename = $this->_save_proof();
		if ($proof_filename === FALSE) {
			return;
		}
		$transaction_number = 'KAS-'.date('Ymd-His').'-'.strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 8));
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_discard_proof($proof_filename);
			$this->_message('Database tidak dapat menyimpan permintaan transaksi kas.', FALSE);
			return;
		}
		$locked_fund = $this->db->query(
			'SELECT id, is_active FROM acc_cash_funds WHERE id = ? FOR UPDATE',
			array($fund_id)
		)->row();
		if (!$locked_fund || (int) $locked_fund->is_active !== 1) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_discard_proof($proof_filename);
			$this->_message('Dana tidak aktif.', FALSE);
			return;
		}
		if ($type === 'supplier_payment') {
			$locked_receipt = $this->db->query(
				'SELECT id FROM goods_receipts WHERE id = ? FOR UPDATE',
				array($goods_receipt_id)
			)->row();
			$outstanding = $locked_receipt
				? $this->_receipt_outstanding($goods_receipt_id, TRUE)
				: FALSE;
			if ($outstanding === FALSE || $outstanding + 0.0001 < $amount) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_discard_proof($proof_filename);
				$this->_message('Sisa hutang penerimaan berubah dan tidak mencukupi jumlah pembayaran.', FALSE);
				return;
			}
		}
		if ($this->_is_after_closing($fund_id, $date)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_discard_proof($proof_filename);
			$this->_message('Tanggal transaksi sudah ditutup.', FALSE);
			return;
		}
		$inserted = $this->db->insert('acc_cash_transactions', array(
			'transaction_number' => $transaction_number,
			'fund_id' => $fund_id,
			'transaction_type' => $type,
			'transaction_date' => $date,
			'amount' => $amount,
			'funding_source_account_id' => $source_account_id,
			'expense_account_id' => $expense_account_id,
			'goods_receipt_id' => $goods_receipt_id,
			'counterparty' => $counterparty === '' ? NULL : $counterparty,
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
			log_message('error', 'Gagal menyimpan transaksi kas '.$transaction_number.': '.$error['code'].' '.$error['message']);
			$this->_discard_proof($proof_filename);
			$this->_message('Permintaan transaksi kas gagal disimpan.', FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_message('Permintaan tersimpan dan menunggu persetujuan. Jurnal hanya dibuat setelah disetujui.', TRUE);
	}

	public function approve($transaction_id = NULL)
	{
		$this->_require_post();
		if (!$this->_is_approver()) {
			show_error('Anda tidak memiliki wewenang untuk menyetujui transaksi kas.', 403);
		}
		$id = $this->_positive_id($transaction_id);
		if (!$id) {
			show_404();
		}
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Database tidak dapat memulai persetujuan transaksi.', FALSE);
			return;
		}
		$transaction = $this->db->query("
			SELECT * FROM acc_cash_transactions WHERE id = ? FOR UPDATE
		", array($id))->row();
		if (!$transaction || $transaction->status !== 'pending' ||
			(string) $transaction->created_by === (string) $this->ion_auth->get_user_id()) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Transaksi tidak ditemukan, sudah diproses, atau tidak boleh disetujui oleh penginputnya sendiri.', FALSE);
			return;
		}
		$fund = $this->db->query("
			SELECT * FROM acc_cash_funds WHERE id = ? AND is_active = 1 FOR UPDATE
		", array((int) $transaction->fund_id))->row();
		if (!$fund) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Dana tidak aktif.', FALSE);
			return;
		}
		$supplier_receipt = NULL;
		if ($transaction->transaction_type === 'supplier_payment') {
			$supplier_receipt = $this->db->query(
				'SELECT id, receipt_number FROM goods_receipts WHERE id = ? FOR UPDATE',
				array((int) $transaction->goods_receipt_id)
			)->row();
			if (!$supplier_receipt) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_message('Penerimaan supplier tidak ditemukan.', FALSE);
				return;
			}
		}
		if ($this->_is_after_closing((int) $fund->id, $transaction->transaction_date)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Tanggal transaksi sudah ditutup.', FALSE);
			return;
		}
		$fund_account_code = $this->_fund_account_code($fund->fund_type);
		$fund_account = $this->db->get_where('acc_accounts', array(
			'code' => $fund_account_code,
			'is_active' => 1,
			'type' => 'asset',
			'normal_balance' => 'debit'
		))->row();
		if (!$fund_account) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Akun buku besar kas tidak aktif atau belum tersedia.', FALSE);
			return;
		}
		$lines = array();
		if ($transaction->transaction_type === 'funding') {
			$source = $this->db->query(
				'SELECT * FROM acc_accounts WHERE id = ? AND is_active = 1 FOR UPDATE',
				array((int) $transaction->funding_source_account_id)
			)->row();
			if ($source && $source->code === '1101') {
				$cashier_fund = $this->db->query("
					SELECT id FROM acc_cash_funds
					WHERE fund_slot = 'cashier' AND is_active = 1
					FOR UPDATE
				")->row();
				if (!$cashier_fund) {
					$this->db->trans_rollback();
					$this->db->db_debug = $previous_db_debug;
					$this->_message('Dana laci kasir belum disiapkan untuk rekonsiliasi transfer.', FALSE);
					return;
				}
			}
			$balance = $source ? $this->_account_balance((int) $source->id, $transaction->transaction_date) : FALSE;
			if (!$source || $balance === FALSE || $balance + 0.0001 < (float) $transaction->amount) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_message('Saldo akun sumber kas/bank tidak mencukupi pada tanggal transaksi.', FALSE);
				return;
			}
			if ($fund->fund_type === 'petty') {
				$fund_balance = $this->_fund_balance((int) $fund->id, $transaction->transaction_date);
				if ($fund_balance === FALSE ||
					$fund_balance + (float) $transaction->amount > (float) $fund->imprest_limit + 0.0001) {
					$this->db->trans_rollback();
					$this->db->db_debug = $previous_db_debug;
					$this->_message('Setoran akan melebihi saldo dana tetap petty cash.', FALSE);
					return;
				}
				$unreplenished_expenses = $this->_unreplenished_expenses(
					(int) $fund->id,
					$transaction->transaction_date,
					(int) $transaction->id
				);
				if ($unreplenished_expenses === FALSE ||
					(float) $transaction->amount > $unreplenished_expenses + 0.0001) {
					$this->db->trans_rollback();
					$this->db->db_debug = $previous_db_debug;
					$this->_message('Refill tidak boleh melebihi pengeluaran petty cash yang sudah diposting sejak refill sebelumnya.', FALSE);
					return;
				}
			}
			$lines = array(
				array('account' => $fund_account_code, 'debit' => $transaction->amount, 'credit' => 0,
					'memo' => 'Penambahan dana '.$fund->fund_code),
				array('account' => $source->code, 'debit' => 0, 'credit' => $transaction->amount,
					'memo' => 'Sumber penambahan dana '.$fund->fund_code)
			);
		} elseif ($transaction->transaction_type === 'expense') {
			$fund_balance = $this->_fund_balance((int) $fund->id, $transaction->transaction_date);
			$expense = $this->db->get_where('acc_accounts', array(
				'id' => (int) $transaction->expense_account_id,
				'type' => 'expense',
				'is_active' => 1,
				'normal_balance' => 'debit'
			))->row();
			if (!$expense || $fund_balance === FALSE ||
				$fund_balance + 0.0001 < (float) $transaction->amount) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_message('Akun beban tidak aktif atau saldo petty cash tidak mencukupi.', FALSE);
				return;
			}
			$lines = array(
				array('account' => $expense->code, 'debit' => $transaction->amount, 'credit' => 0,
					'memo' => 'Pengeluaran petty cash '.$fund->fund_code),
				array('account' => $fund_account_code, 'debit' => 0, 'credit' => $transaction->amount,
					'memo' => 'Pengeluaran petty cash '.$fund->fund_code)
			);
		} else {
			$outstanding = $supplier_receipt
				? $this->_receipt_outstanding((int) $supplier_receipt->id, TRUE, (int) $transaction->id)
				: FALSE;
			$fund_balance = $this->_fund_balance((int) $fund->id, $transaction->transaction_date);
			$payable = $this->db->where('code', '2101')
				->where('is_active', 1)
				->where('type', 'liability')
				->where('normal_balance', 'credit')
				->get('acc_accounts')->row();
			if (!$supplier_receipt || $outstanding === FALSE ||
				$outstanding + 0.0001 < (float) $transaction->amount ||
				$fund_balance === FALSE ||
				$fund_balance + 0.0001 < (float) $transaction->amount || !$payable) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_message('Hutang supplier atau saldo dana tidak mencukupi untuk pembayaran ini.', FALSE);
				return;
			}
			$lines = array(
				array('account' => '2101', 'debit' => $transaction->amount, 'credit' => 0,
					'memo' => 'Pembayaran supplier '.$transaction->counterparty.' '.$supplier_receipt->receipt_number),
				array('account' => $fund_account_code, 'debit' => 0, 'credit' => $transaction->amount,
					'memo' => 'Kas dibayarkan ke supplier '.$transaction->counterparty)
			);
		}
		$this->load->library('accounting');
		$posted = $this->accounting->post_journal(
			'PETTY_CASH',
			(string) $transaction->id,
			$transaction->transaction_date,
			$transaction->description,
			(string) $this->ion_auth->get_user_id(),
			$lines
		);
		if (!$posted) {
			$error = $this->accounting->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal mem-posting transaksi kas '.$transaction->transaction_number.': '.$error);
			$this->_message('Jurnal gagal diposting: '.$error, FALSE);
			return;
		}
		$journal = $this->db->get_where('acc_journals', array(
			'reference_type' => 'PETTY_CASH',
			'reference_id' => (string) $transaction->id
		))->row();
		$updated = $journal && $this->db->where('id', $id)->where('status', 'pending')
			->update('acc_cash_transactions', array(
				'status' => 'posted',
				'journal_id' => (int) $journal->id,
				'approved_by' => (string) $this->ion_auth->get_user_id(),
				'approved_at' => date('Y-m-d H:i:s')
			));
		if (!$updated || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menyelesaikan persetujuan transaksi kas '.$transaction->transaction_number.': '.$error['code'].' '.$error['message']);
			$this->_message('Transaksi gagal diposting. Tidak ada perubahan yang disimpan.', FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_message('Transaksi disetujui dan jurnal '.$journal->journal_number.' berhasil diposting.', TRUE);
	}

	public function reject($transaction_id = NULL)
	{
		$this->_require_post();
		if (!$this->_is_approver()) {
			show_error('Anda tidak memiliki wewenang untuk menolak transaksi kas.', 403);
		}
		$id = $this->_positive_id($transaction_id);
		$reason = trim((string) $this->input->post('rejection_reason', TRUE));
		if (!$id || $reason === '' || strlen($reason) > 500) {
			$this->_message('Alasan penolakan wajib diisi, maksimal 500 karakter.', FALSE);
			return;
		}
		$transaction = $this->db->get_where('acc_cash_transactions', array(
			'id' => $id,
			'status' => 'pending'
		))->row();
		if (!$transaction || (string) $transaction->created_by === (string) $this->ion_auth->get_user_id()) {
			$this->_message('Transaksi tidak ditemukan atau tidak boleh ditolak oleh penginputnya sendiri.', FALSE);
			return;
		}
		$updated = $this->db->where('id', $id)->where('status', 'pending')
			->update('acc_cash_transactions', array(
				'status' => 'rejected',
				'approved_by' => (string) $this->ion_auth->get_user_id(),
				'approved_at' => date('Y-m-d H:i:s'),
				'rejection_reason' => $reason
			));
		if (!$updated) {
			$error = $this->db->error();
			log_message('error', 'Gagal menolak transaksi kas '.$id.': '.$error['code'].' '.$error['message']);
			$this->_message('Penolakan transaksi gagal disimpan.', FALSE);
			return;
		}
		$this->_message('Transaksi ditolak dan tidak membentuk jurnal.', TRUE);
	}

	public function close_day()
	{
		$this->_require_post();
		if (!$this->_is_approver()) {
			show_error('Hanya admin, management, atau pengurus yang dapat mengesahkan tutup kas harian.', 403);
		}
		$fund_id = $this->_positive_id($this->input->post('fund_id', TRUE));
		$date = trim((string) $this->input->post('closing_date', TRUE));
		$counted = $this->_parse_amount($this->input->post('counted_balance', TRUE));
		$notes = trim((string) $this->input->post('notes', TRUE));
		$fund = $fund_id ? $this->_get_fund($fund_id) : NULL;
		if (!$fund || (int) $fund->is_active !== 1 || !$this->_is_date($date) ||
			$date > date('Y-m-d') || $counted === FALSE || strlen($notes) > 500) {
			$this->_message('Dana, tanggal tutup, saldo fisik, atau catatan tidak valid.', FALSE);
			return;
		}
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Database tidak dapat memulai tutup kas.', FALSE);
			return;
		}
		$fund = $this->db->query(
			'SELECT * FROM acc_cash_funds WHERE id = ? AND is_active = 1 FOR UPDATE',
			array($fund_id)
		)->row();
		if (!$fund || $this->_is_after_closing($fund_id, $date)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Dana tidak aktif atau tutup kas harus dicatat berurutan setelah tanggal tutup terakhir.', FALSE);
			return;
		}
		$pending = $this->db->where('fund_id', $fund_id)
			->where('transaction_date <=', $date)
			->where('status', 'pending')
			->count_all_results('acc_cash_transactions');
		if ($pending > 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Selesaikan atau tolak seluruh permintaan transaksi sampai tanggal tutup sebelum merekonsiliasi kas.', FALSE);
			return;
		}
		$balance = $this->_fund_balance($fund_id, $date);
		if ($balance === FALSE) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Saldo sistem tidak dapat dihitung.', FALSE);
			return;
		}
		if (abs($counted - $balance) > 0.009 && $notes === '') {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Catatan wajib diisi jika saldo fisik berbeda dari saldo sistem.', FALSE);
			return;
		}
		$inserted = $this->db->insert('acc_cash_closings', array(
			'fund_id' => $fund_id,
			'closing_date' => $date,
			'system_balance' => $balance,
			'counted_balance' => $counted,
			'variance' => round($counted - $balance, 2),
			'notes' => $notes === '' ? NULL : $notes,
			'closed_by' => (string) $this->ion_auth->get_user_id(),
			'closed_at' => date('Y-m-d H:i:s')
		));
		if (!$inserted || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menutup kas '.$fund->fund_code.' tanggal '.$date.': '.$error['code'].' '.$error['message']);
			$this->_message((int) $error['code'] === 1062 ? 'Kas pada tanggal ini sudah ditutup.' : 'Data tutup kas gagal disimpan.', FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_message('Kas ditutup. Selisih tercatat untuk ditindaklanjuti dan tidak dijurnal otomatis.', TRUE);
	}

	public function proof($transaction_id = NULL)
	{
		$id = $this->_positive_id($transaction_id);
		$transaction = $id ? $this->db->get_where('acc_cash_transactions', array('id' => $id))->row() : NULL;
		if (!$transaction || !$transaction->proof_filename) {
			show_404();
		}
		$path = APPPATH.'cache'.DIRECTORY_SEPARATOR.'petty_cash_proofs'.DIRECTORY_SEPARATOR.
			basename($transaction->proof_filename);
		if (!is_file($path) || !is_readable($path)) {
			show_error('File bukti transaksi kas tidak ditemukan.', 404);
		}
		$this->load->helper('download');
		$file_data = file_get_contents($path);
		if ($file_data === FALSE) {
			show_error('File bukti transaksi kas tidak dapat dibaca.', 500);
		}
		force_download('bukti-'.$transaction->transaction_number.'-'.basename($path), $file_data);
	}

	private function _save_proof()
	{
		if (!isset($_FILES['proof_file']) || !is_array($_FILES['proof_file']) ||
			!isset($_FILES['proof_file']['error']) ||
			(int) $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
			$this->_message('Lampirkan bukti berupa PDF, JPG, JPEG, atau PNG (maksimal 5 MB).', FALSE);
			return FALSE;
		}
		$directory = APPPATH.'cache'.DIRECTORY_SEPARATOR.'petty_cash_proofs'.DIRECTORY_SEPARATOR;
		if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
			log_message('error', 'Direktori bukti kas tidak dapat dibuat: '.$directory);
			$this->_message('Penyimpanan bukti kas tidak tersedia. Hubungi administrator.', FALSE);
			return FALSE;
		}
		$this->load->library('upload');
		$config = array(
			'upload_path' => $directory,
			'allowed_types' => 'pdf|jpg|jpeg|png',
			'max_size' => 5120,
			'encrypt_name' => TRUE,
			'remove_spaces' => TRUE,
			'detect_mime' => TRUE,
			'mod_mime_fix' => TRUE
		);
		$this->upload->initialize($config, TRUE);
		if (!$this->upload->do_upload('proof_file')) {
			$error = strip_tags($this->upload->display_errors('', ''));
			$this->_message('Bukti tidak dapat diunggah: '.$error, FALSE);
			return FALSE;
		}
		$file = $this->upload->data();
		return basename($file['file_name']);
	}

	private function _discard_proof($proof_filename)
	{
		$path = APPPATH.'cache'.DIRECTORY_SEPARATOR.'petty_cash_proofs'.DIRECTORY_SEPARATOR.
			basename($proof_filename);
		if (is_file($path) && !unlink($path)) {
			log_message('error', 'Bukti kas sementara tidak dapat dibersihkan: '.$path);
		}
	}

	private function _get_fund($fund_id)
	{
		return $this->db->get_where('acc_cash_funds', array('id' => (int) $fund_id))->row();
	}

	private function _get_receipt($receipt_id)
	{
		return $this->db->query("
			SELECT receipt.id, receipt.receipt_number, receipt.supplier_document,
				supplier.name AS supplier_name
			FROM goods_receipts receipt
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			WHERE receipt.id = ?
		", array((int) $receipt_id))->row();
	}

	private function _get_outstanding_receipts()
	{
		return $this->db->query("
			SELECT receipt.id, receipt.receipt_number, receipt.supplier_document,
				supplier.name AS supplier_name,
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
					JOIN acc_journals journal
						ON journal.reference_type = 'SUPPLIER_RETURN'
						AND journal.reference_id = CAST(supplier_return.id AS CHAR)
						AND journal.is_posted = 1
					JOIN acc_journal_items item ON item.journal_id = journal.id
					JOIN acc_accounts account ON account.id = item.account_id
					WHERE supplier_return.status = 'final'
						AND supplier_return.goods_receipt_id = receipt.id
						AND account.code = '2101'
				), 0)
				- COALESCE((
					SELECT SUM(cash_tx.amount)
					FROM acc_cash_transactions cash_tx
					WHERE cash_tx.goods_receipt_id = receipt.id
						AND cash_tx.transaction_type = 'supplier_payment'
						AND cash_tx.status IN ('pending', 'posted')
				), 0) AS outstanding
			FROM goods_receipts receipt
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			HAVING outstanding > 0
			ORDER BY receipt.received_at DESC, receipt.id DESC
			LIMIT 500
		")->result();
	}

	private function _receipt_outstanding($receipt_id, $include_pending, $exclude_transaction_id = 0)
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
			JOIN acc_journals journal
				ON journal.reference_type = 'SUPPLIER_RETURN'
				AND journal.reference_id = CAST(supplier_return.id AS CHAR)
				AND journal.is_posted = 1
			JOIN acc_journal_items item ON item.journal_id = journal.id
			JOIN acc_accounts account ON account.id = item.account_id
			WHERE supplier_return.status = 'final'
				AND supplier_return.goods_receipt_id = ? AND account.code = '2101'
		", array((int) $receipt_id))->row();
		$this->db->where('goods_receipt_id', (int) $receipt_id)
			->where('transaction_type', 'supplier_payment');
		if ($include_pending) {
			$this->db->where_in('status', array('pending', 'posted'));
		} else {
			$this->db->where('status', 'posted');
		}
		if ($exclude_transaction_id > 0) {
			$this->db->where('id !=', (int) $exclude_transaction_id);
		}
		$payments = $this->db->select_sum('amount')->get('acc_cash_transactions')->row();
		if (!$receipt || !$returns || !$payments) {
			return FALSE;
		}
		return round((float) $receipt->amount - (float) $returns->amount - (float) $payments->amount, 2);
	}

	private function _fund_balance($fund_id, $date)
	{
		$fund = $this->_get_fund($fund_id);
		if (!$fund) {
			return FALSE;
		}
		if ($fund->fund_type === 'cashier' || $fund->fund_type === 'main') {
			$account_code = $this->_fund_account_code($fund->fund_type);
			$account = $this->db->get_where('acc_accounts', array('code' => $account_code))->row();
			return $account ? $this->_account_balance((int) $account->id, $date) : FALSE;
		}
		$row = $this->db->query("
			SELECT COALESCE(SUM(CASE WHEN transaction_type = 'funding'
				THEN amount ELSE -amount END), 0) AS balance
			FROM acc_cash_transactions
			WHERE fund_id = ? AND status = 'posted' AND transaction_date <= ?
		", array((int) $fund_id, $date))->row();
		return $row ? (float) $row->balance : FALSE;
	}

	private function _unreplenished_expenses($fund_id, $date, $exclude_transaction_id)
	{
		$last_funding = $this->db->query("
			SELECT id, transaction_date
			FROM acc_cash_transactions
			WHERE fund_id = ? AND transaction_type = 'funding'
				AND status = 'posted' AND transaction_date <= ?
			ORDER BY transaction_date DESC, id DESC
			LIMIT 1
		", array((int) $fund_id, $date))->row();
		if (!$last_funding) {
		$fund = $this->_get_fund($fund_id);
		return $fund ? (float) $fund->imprest_limit : FALSE;
		}
		$row = $this->db->query("
			SELECT COALESCE(SUM(amount), 0) AS amount
			FROM acc_cash_transactions
			WHERE fund_id = ? AND transaction_type = 'expense'
				AND status = 'posted' AND transaction_date <= ?
				AND (transaction_date > ? OR (transaction_date = ? AND id > ?))
				AND id != ?
		", array(
			(int) $fund_id,
			$date,
			$last_funding->transaction_date,
			$last_funding->transaction_date,
			(int) $last_funding->id,
			(int) $exclude_transaction_id
		))->row();
		return $row ? (float) $row->amount : FALSE;
	}

	private function _fund_account_code($fund_type)
	{
		$account_codes = array(
			'cashier' => '1101',
			'main' => '1106',
			'petty' => '1107'
		);
		return isset($account_codes[$fund_type]) ? $account_codes[$fund_type] : '';
	}

	private function _account_balance($account_id, $date)
	{
		$row = $this->db->query("
			SELECT COALESCE(SUM(item.debit - item.credit), 0) AS balance
			FROM acc_journal_items item
			JOIN acc_journals journal ON journal.id = item.journal_id
			WHERE journal.is_posted = 1 AND journal.date <= ? AND item.account_id = ?
		", array($date, (int) $account_id))->row();
		return $row ? (float) $row->balance : FALSE;
	}

	private function _is_after_closing($fund_id, $date)
	{
		$fund = $this->_get_fund($fund_id);
		if (!$fund || !in_array($fund->fund_type, array('cashier', 'main', 'petty'), TRUE)) {
			return TRUE;
		}
		$last = $this->db->where('fund_id', (int) $fund_id)
			->order_by('closing_date', 'DESC')->limit(1)->get('acc_cash_closings')->row();
		if (!$last) {
			return FALSE;
		}
		return $date <= $last->closing_date;
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

	private function _is_approver()
	{
		foreach ($this->approver_groups as $group) {
			if ($this->ion_auth->in_group($group)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	private function _require_post()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$expected_token = $this->session->userdata('petty_cash_form_token');
		$posted_token = $this->input->post('form_token', TRUE);
		if (!is_string($expected_token) || $expected_token === '' ||
			!is_string($posted_token) || $posted_token !== $expected_token) {
			show_error('Sesi formulir kas tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}
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

	private function _message($message, $success)
	{
		$this->session->set_flashdata($success ? 'cash_notice' : 'cash_error', $message);
		$return_to = $this->input->post('return_to', TRUE);
		if ($return_to === 'main/management_payments') {
			redirect('main/management_payments');
		}
		redirect($return_to === 'main/petty_cash_new' ? 'main/petty_cash_new' : 'main/petty_cash');
	}
}
