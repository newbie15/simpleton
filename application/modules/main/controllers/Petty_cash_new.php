<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Petty_cash_new extends CI_Controller
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
			show_error('Anda tidak memiliki akses ke ruang kerja kas baru.', 403);
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
		$tab = trim((string) $this->input->get('tab', TRUE));
		if (!in_array($tab, array('overview', 'register', 'requests', 'reconcile'), TRUE)) {
			$tab = 'overview';
		}
		$funds = $this->db->query("
			SELECT fund.id, fund.fund_code, fund.name, fund.fund_type,
				fund.custodian_user_id, fund.imprest_limit, fund.is_active,
				custodian.username AS custodian_username
			FROM acc_cash_funds fund
			LEFT JOIN users custodian ON custodian.id = fund.custodian_user_id
			ORDER BY fund.is_active DESC, fund.fund_type ASC, fund.name ASC
		")->result();
		foreach ($funds as $fund) {
			$fund->balance = $this->_fund_balance((int) $fund->id, date('Y-m-d'));
		}

		$report_date = trim((string) $this->input->get('report_date', TRUE));
		if (!$this->_is_date($report_date) || $report_date > date('Y-m-d')) {
			$report_date = date('Y-m-d');
		}
		$requested_fund_id = $this->_positive_id($this->input->get('fund_id', TRUE));
		$daily_fund = NULL;
		foreach ($funds as $fund) {
			if ((int) $fund->is_active === 1 &&
				(!$requested_fund_id || (int) $fund->id === $requested_fund_id)) {
				$daily_fund = $fund;
				break;
			}
		}
		if (!$daily_fund) {
			foreach ($funds as $fund) {
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

		$transactions = $this->db->query("
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
		$closings = $this->db->query("
			SELECT closing.*, fund.fund_code, fund.name AS fund_name,
				close_user.username AS closed_by_name
			FROM acc_cash_closings closing
			JOIN acc_cash_funds fund ON fund.id = closing.fund_id
			JOIN users close_user ON close_user.id = closing.closed_by
			ORDER BY closing.closing_date DESC, closing.id DESC
			LIMIT 60
		")->result();

		$active_funds = array();
		foreach ($funds as $fund) {
			if ((int) $fund->is_active === 1) {
				$active_funds[] = $fund;
			}
		}
		$data = array(
			'tab' => $tab,
			'funds' => $funds,
			'active_funds' => $active_funds,
			'daily_fund' => $daily_fund,
			'report_date' => $report_date,
			'daily_opening' => $daily_opening,
			'daily_balance' => $daily_balance,
			'daily_rows' => $daily_rows,
			'transactions' => $transactions,
			'closings' => $closings,
			'expense_accounts' => $this->db->where('type', 'expense')
				->where('is_active', 1)->order_by('code', 'ASC')->get('acc_accounts')->result(),
			'funding_accounts' => $this->db->where_in('code', array('1101', '1102', '1106'))
				->where('is_active', 1)->order_by('code', 'ASC')->get('acc_accounts')->result(),
			'supplier_receipts' => $this->_get_outstanding_receipts(),
			'users' => $this->db->select('id, username, first_name, last_name')
				->where('active', 1)->order_by('username', 'ASC')->get('users')->result(),
			'is_approver' => $this->_is_approver(),
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('cash_notice'),
			'error' => $this->session->flashdata('cash_error'),
			'judul_besar' => 'Kas & Petty Cash — Ruang Kerja',
			'judul_kecil' => 'Kas per dana · pencatatan, persetujuan, dan rekonsiliasi',
			'm_petty_cash_new' => TRUE
		);
		$data['output'] = $this->load->view('petty_cash_new', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	private function _is_approver()
	{
		return $this->ion_auth->in_group($this->approver_groups);
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

	private function _fund_balance($fund_id, $date)
	{
		$fund = $this->db->get_where('acc_cash_funds', array('id' => (int) $fund_id))->row();
		if (!$fund) {
			return FALSE;
		}
		if ($fund->fund_type === 'petty') {
			$row = $this->db->query("
				SELECT COALESCE(SUM(CASE WHEN transaction_type = 'funding'
					THEN amount ELSE -amount END), 0) AS balance
				FROM acc_cash_transactions
				WHERE fund_id = ? AND status = 'posted' AND transaction_date <= ?
			", array((int) $fund_id, $date))->row();
			return $row ? (float) $row->balance : FALSE;
		}
		$account_code = $this->_fund_account_code($fund->fund_type);
		$sql = "
			SELECT COALESCE(SUM(item.debit - item.credit), 0) AS balance
			FROM acc_journals journal
			JOIN acc_journal_items item ON item.journal_id = journal.id
			JOIN acc_accounts account ON account.id = item.account_id
			WHERE journal.is_posted = 1 AND journal.date <= ? AND account.code = ?
		";
		$row = $this->db->query($sql, array($date, $account_code))->row();
		return $row ? (float) $row->balance : FALSE;
	}

	private function _fund_account_code($fund_type)
	{
		if ($fund_type === 'cashier') {
			return '1101';
		}
		if ($fund_type === 'main') {
			return '1106';
		}
		return $fund_type === 'petty' ? '1107' : '';
	}

	private function _positive_id($value)
	{
		if ((!is_string($value) && !is_int($value)) ||
			!ctype_digit((string) $value) || (int) $value < 1) {
			return FALSE;
		}
		return (int) $value;
	}

	private function _is_date($value)
	{
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return FALSE;
		}
		$date = DateTime::createFromFormat('!Y-m-d', $value);
		return $date && $date->format('Y-m-d') === $value;
	}
}
