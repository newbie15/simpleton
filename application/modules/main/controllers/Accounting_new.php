<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounting_new extends CI_Controller
{
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
		if (!$this->page_access->can_access('accounting')) {
			show_error('Anda tidak memiliki akses ke ruang kerja akuntansi baru.', 403);
		}
	}

	public function index()
	{
		$today = date('Y-m-d');
		$start_date = trim((string) $this->input->get('start_date', TRUE));
		$end_date = trim((string) $this->input->get('end_date', TRUE));
		if (!$this->_is_date($start_date)) {
			$start_date = date('Y-m-01');
		}
		if (!$this->_is_date($end_date) || $end_date > $today) {
			$end_date = $today;
		}
		if ($start_date > $end_date) {
			show_error('Tanggal awal tidak boleh setelah tanggal akhir.', 400);
		}

		$tab = trim((string) $this->input->get('tab', TRUE));
		if (!in_array($tab, array('overview', 'accounts', 'register', 'journals', 'new'), TRUE)) {
			$tab = 'overview';
		}

		$accounts = $this->db->query("
			SELECT account.id, account.code, account.name, account.type,
				account.normal_balance, account.parent_id,
				COALESCE(SUM(CASE WHEN journal.date <= ? THEN item.debit ELSE 0 END), 0) AS debit_to_date,
				COALESCE(SUM(CASE WHEN journal.date <= ? THEN item.credit ELSE 0 END), 0) AS credit_to_date,
				COALESCE(SUM(CASE WHEN journal.date BETWEEN ? AND ? THEN item.debit ELSE 0 END), 0) AS period_debit,
				COALESCE(SUM(CASE WHEN journal.date BETWEEN ? AND ? THEN item.credit ELSE 0 END), 0) AS period_credit
			FROM acc_accounts account
			LEFT JOIN acc_journal_items item ON item.account_id = account.id
			LEFT JOIN acc_journals journal
				ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE account.is_active = 1
			GROUP BY account.id, account.code, account.name, account.type,
				account.normal_balance, account.parent_id
			ORDER BY account.code ASC
		", array($end_date, $end_date, $start_date, $end_date, $start_date, $end_date))->result();

		$totals = array(
			'assets' => 0,
			'liabilities' => 0,
			'equity' => 0,
			'income' => 0,
			'expenses' => 0,
			'journal_count' => 0
		);
		$ledger_accounts = array();
		foreach ($accounts as $account) {
			$account->debit_to_date = (float) $account->debit_to_date;
			$account->credit_to_date = (float) $account->credit_to_date;
			$account->period_debit = (float) $account->period_debit;
			$account->period_credit = (float) $account->period_credit;
			$account->balance = $account->normal_balance === 'debit'
				? $account->debit_to_date - $account->credit_to_date
				: $account->credit_to_date - $account->debit_to_date;
			if (in_array($account->type, array('asset', 'liability', 'equity'), TRUE)) {
				$totals[$account->type === 'asset' ? 'assets' : ($account->type === 'liability' ? 'liabilities' : 'equity')] += $account->balance;
			}
			if ($account->type === 'income') {
				$totals['income'] += $account->period_credit - $account->period_debit;
			} elseif (in_array($account->type, array('cogs', 'expense'), TRUE)) {
				$totals['expenses'] += $account->period_debit - $account->period_credit;
			}
			$ledger_accounts[] = $account;
		}

		$journal_query = $this->db->query("
			SELECT journal.id, journal.journal_number, journal.date,
				journal.reference_type, journal.reference_id, journal.description,
				journal.created_by, COALESCE(SUM(item.debit), 0) AS total_debit,
				COALESCE(SUM(item.credit), 0) AS total_credit
			FROM acc_journals journal
			LEFT JOIN acc_journal_items item ON item.journal_id = journal.id
			WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
			GROUP BY journal.id, journal.journal_number, journal.date,
				journal.reference_type, journal.reference_id, journal.description,
				journal.created_by
			ORDER BY journal.date DESC, journal.id DESC
			LIMIT 100
		", array($start_date, $end_date));
		$journals = $journal_query->result();
		$journal_count = $this->db->query("
			SELECT COUNT(*) AS total
			FROM acc_journals
			WHERE is_posted = 1 AND date BETWEEN ? AND ?
		", array($start_date, $end_date))->row();
		$totals['journal_count'] = $journal_count ? (int) $journal_count->total : 0;
		$journal_items = array();
		if (!empty($journals)) {
			$journal_ids = array();
			foreach ($journals as $journal) {
				$journal_ids[] = (int) $journal->id;
			}
			$items = $this->db->select('item.journal_id, account.code, account.name, item.debit, item.credit, item.memo')
				->from('acc_journal_items item')
				->join('acc_accounts account', 'account.id = item.account_id')
				->where_in('item.journal_id', $journal_ids)
				->order_by('item.id', 'ASC')
				->get()->result();
			foreach ($items as $item) {
				$id = (int) $item->journal_id;
				if (!isset($journal_items[$id])) {
					$journal_items[$id] = array();
				}
				$journal_items[$id][] = $item;
			}
		}

		$account_id = $this->_positive_id($this->input->get('account_id', TRUE));
		$selected_account = NULL;
		foreach ($ledger_accounts as $account) {
			if ($account_id && (int) $account->id === $account_id) {
				$selected_account = $account;
				break;
			}
		}
		if (!$selected_account && !empty($ledger_accounts)) {
			$selected_account = $ledger_accounts[0];
		}

		$register_opening = 0;
		$register_closing = 0;
		$register_rows = array();
		if ($selected_account) {
			$opening = $this->db->query("
				SELECT COALESCE(SUM(item.debit), 0) AS debit,
					COALESCE(SUM(item.credit), 0) AS credit
				FROM acc_journals journal
				JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date < ? AND item.account_id = ?
			", array($start_date, (int) $selected_account->id))->row();
			$register_opening = $selected_account->normal_balance === 'debit'
				? (float) $opening->debit - (float) $opening->credit
				: (float) $opening->credit - (float) $opening->debit;
			$register_closing = $register_opening;
			$register_rows = $this->db->query("
				SELECT journal.id AS journal_id, journal.date, journal.journal_number,
					journal.reference_type, journal.reference_id, journal.description,
					item.memo, item.debit, item.credit
				FROM acc_journals journal
				JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
					AND item.account_id = ?
				ORDER BY journal.date ASC, journal.id ASC, item.id ASC
				LIMIT 1000
			", array($start_date, $end_date, (int) $selected_account->id))->result();
			foreach ($register_rows as $row) {
				$movement = $selected_account->normal_balance === 'debit'
					? (float) $row->debit - (float) $row->credit
					: (float) $row->credit - (float) $row->debit;
				$register_closing += $movement;
				$row->running_balance = $register_closing;
			}
		}

		$data = array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'tab' => $tab,
			'accounts' => $ledger_accounts,
			'totals' => $totals,
			'journals' => $journals,
			'journal_items' => $journal_items,
			'selected_account' => $selected_account,
			'register_opening' => $register_opening,
			'register_closing' => $register_closing,
			'register_rows' => $register_rows,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('accounting_new_notice'),
			'error' => $this->session->flashdata('accounting_new_error'),
			'judul_besar' => 'Accounting — Buku Besar',
			'judul_kecil' => 'Ruang kerja baru · pencatatan berpasangan (double-entry)',
			'm_accounting_new' => TRUE
		);
		$data['output'] = $this->load->view('accounting_new', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function post_journal()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$expected_token = $this->session->userdata('accounting_new_form_token');
		$posted_token = $this->input->post('form_token', TRUE);
		if (!is_string($expected_token) || $expected_token === '' ||
			!is_string($posted_token) || $posted_token !== $expected_token) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}
		$date = trim((string) $this->input->post('date', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$account_ids = $this->input->post('account_id');
		$debits = $this->input->post('debit');
		$credits = $this->input->post('credit');
		$memos = $this->input->post('memo');
		if (!$this->_is_date($date) || $date > date('Y-m-d') ||
			$description === '' || strlen($description) > 2000 ||
			!is_array($account_ids) || !is_array($debits) ||
			!is_array($credits) || !is_array($memos) ||
			count($account_ids) < 2 || count($account_ids) > 20 ||
			count($debits) !== count($account_ids) ||
			count($credits) !== count($account_ids) ||
			count($memos) !== count($account_ids)) {
			$this->_post_message('Tanggal, keterangan, atau jumlah baris jurnal tidak valid.', FALSE);
			return;
		}

		$accounts = $this->db->where('is_active', 1)->get('acc_accounts')->result();
		$account_by_id = array();
		foreach ($accounts as $account) {
			$account_by_id[(int) $account->id] = $account;
		}
		$lines = array();
		$total_debit_cents = '0';
		$total_credit_cents = '0';
		foreach ($account_ids as $index => $raw_id) {
			if (!isset($debits[$index], $credits[$index], $memos[$index]) ||
				is_array($debits[$index]) || is_array($credits[$index]) || is_array($memos[$index])) {
				$this->_post_message('Data salah satu baris jurnal tidak lengkap.', FALSE);
				return;
			}
			$id = $this->_positive_id($raw_id);
			$debit_cents = $this->_amount_cents($debits[$index]);
			$credit_cents = $this->_amount_cents($credits[$index]);
			$memo = trim((string) $memos[$index]);
			if (!$id || !isset($account_by_id[$id]) ||
				$debit_cents === FALSE || $credit_cents === FALSE ||
				($debit_cents !== '0' && $credit_cents !== '0') ||
				($debit_cents === '0' && $credit_cents === '0') ||
				strlen($memo) > 255) {
				$this->_post_message('Setiap baris harus memakai akun aktif dan berisi debit atau kredit yang valid (tidak keduanya).', FALSE);
				return;
			}
			$total_debit_cents = $this->_add_cents($total_debit_cents, $debit_cents);
			$total_credit_cents = $this->_add_cents($total_credit_cents, $credit_cents);
			$maximum_cents = '999999999999999';
			if ($this->_compare_cents($total_debit_cents, $maximum_cents) > 0 ||
				$this->_compare_cents($total_credit_cents, $maximum_cents) > 0) {
				$this->_post_message('Total jurnal melebihi batas nominal yang dapat disimpan.', FALSE);
				return;
			}
			$lines[] = array(
				'account' => $account_by_id[$id]->code,
				'debit' => $this->_cents_amount($debit_cents),
				'credit' => $this->_cents_amount($credit_cents),
				'memo' => $memo
			);
		}
		if ($total_debit_cents === '0' || $total_debit_cents !== $total_credit_cents) {
			$this->_post_message('Total debit harus sama persis dengan total kredit sebelum jurnal dapat disimpan.', FALSE);
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_post_message('Database tidak dapat memulai pencatatan jurnal.', FALSE);
			return;
		}
		$reference_id = strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 32));
		$this->load->library('accounting');
		$posted = $this->accounting->post_journal(
			'MANUAL_NEW',
			$reference_id,
			$date,
			$description,
			(string) $this->ion_auth->get_user_id(),
			$lines
		);
		if (!$posted || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $posted ? 'Database menolak penyimpanan jurnal.' : $this->accounting->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Jurnal manual baru gagal disimpan: '.$error);
			$this->_post_message('Jurnal tidak tersimpan: '.$error, FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_post_message('Jurnal berhasil diposting ke buku besar bersama.', TRUE);
	}

	private function _post_message($message, $success)
	{
		$this->session->set_flashdata(
			$success ? 'accounting_new_notice' : 'accounting_new_error',
			$message
		);
		redirect('main/accounting_new?tab=new');
	}

	private function _form_token()
	{
		$token = $this->session->userdata('accounting_new_form_token');
		if (!is_string($token) || $token === '') {
			$this->load->helper('string');
			$token = random_string('alnum', 32);
			$this->session->set_userdata('accounting_new_form_token', $token);
		}
		return $token;
	}

	private function _amount_cents($value)
	{
		if (!is_string($value) && !is_numeric($value)) {
			return FALSE;
		}
		$value = trim((string) $value);
		if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value)) {
			return FALSE;
		}
		$parts = explode('.', $value, 2);
		$whole = ltrim($parts[0], '0');
		$fraction = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
		$cents = ltrim($whole.$fraction, '0');
		return $cents === '' ? '0' : $cents;
	}

	private function _add_cents($left, $right)
	{
		$left = strrev($left);
		$right = strrev($right);
		$length = max(strlen($left), strlen($right));
		$carry = 0;
		$sum = '';
		for ($index = 0; $index < $length; $index++) {
			$left_digit = $index < strlen($left) ? (int) $left[$index] : 0;
			$right_digit = $index < strlen($right) ? (int) $right[$index] : 0;
			$value = $left_digit + $right_digit + $carry;
			$sum .= (string) ($value % 10);
			$carry = (int) floor($value / 10);
		}
		if ($carry > 0) {
			$sum .= (string) $carry;
		}
		return strrev($sum);
	}

	private function _compare_cents($left, $right)
	{
		if (strlen($left) !== strlen($right)) {
			return strlen($left) < strlen($right) ? -1 : 1;
		}
		return strcmp($left, $right);
	}

	private function _cents_amount($cents)
	{
		$cents = str_pad($cents, 3, '0', STR_PAD_LEFT);
		return substr($cents, 0, -2).'.'.substr($cents, -2);
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
