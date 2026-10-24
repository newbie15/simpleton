<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Petty_cash_grid extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form', 'string'));
		$this->load->library(array('session', 'ion_auth', 'page_access'));

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('petty_cash')) {
			show_error('Anda tidak memiliki akses ke Petty Cash V3.', 403);
		}
		foreach (array('acc_cash_funds', 'acc_cash_transactions', 'acc_cash_closings', 'acc_accounts') as $table) {
			if (!$this->db->table_exists($table)) {
				show_error('Skema kas belum tersedia. Jalankan application/sql/phase3_foundation.sql dan application/sql/petty_cash.sql.', 500);
			}
		}
	}

	public function index()
	{
		$funds = $this->db->query("
			SELECT fund.*, custodian.username AS custodian_username
			FROM acc_cash_funds fund
			LEFT JOIN users custodian ON custodian.id = fund.custodian_user_id
			WHERE fund.fund_type = 'petty' AND fund.is_active = 1
			ORDER BY fund.name ASC
		")->result();
		foreach ($funds as $fund) {
			$row = $this->db->query("
				SELECT COALESCE(SUM(CASE WHEN transaction_type = 'funding'
					THEN amount ELSE -amount END), 0) AS balance
				FROM acc_cash_transactions
				WHERE fund_id = ? AND status = 'posted' AND transaction_date <= ?
			", array((int) $fund->id, date('Y-m-d')))->row();
			$fund->balance = $row ? (float) $row->balance : 0;
		}
		$requested_id = $this->_positive_id($this->input->get('fund_id', TRUE));
		$selected_fund = NULL;
		foreach ($funds as $fund) {
			if ($requested_id && (int) $fund->id === $requested_id) {
				$selected_fund = $fund;
				break;
			}
		}
		if (!$selected_fund && !empty($funds)) {
			$selected_fund = $funds[0];
		}
		$account_options = array();
		$accounts = $this->db->where('is_active', 1)->order_by('code', 'ASC')->get('acc_accounts')->result();
		foreach ($accounts as $account) {
			if ($account->type === 'expense') {
				$account_options[] = array(
					'id' => (string) $account->id,
					'name' => 'Beban · '.$account->code.' — '.$account->name
				);
			} elseif ($account->code === '1106' && $account->type === 'asset' &&
				$account->normal_balance === 'debit') {
				$account_options[] = array(
					'id' => (string) $account->id,
					'name' => 'Isi dana dari kas utama · '.$account->code.' — '.$account->name
				);
			}
		}
		$pending = $this->db->query("
			SELECT cash_tx.transaction_number, cash_tx.transaction_date,
				cash_tx.transaction_type, cash_tx.description, cash_tx.amount,
				fund.fund_code
			FROM acc_cash_transactions cash_tx
			JOIN acc_cash_funds fund ON fund.id = cash_tx.fund_id
			WHERE cash_tx.status = 'pending' AND fund.fund_type = 'petty'
			ORDER BY cash_tx.id DESC LIMIT 8
		")->result();
		$data = array(
			'funds' => $funds,
			'selected_fund' => $selected_fund,
			'accounts' => $account_options,
			'pending' => $pending,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('petty_cash_grid_notice'),
			'error' => $this->session->flashdata('petty_cash_grid_error'),
			'judul_besar' => 'Petty Cash V3 — Input Spreadsheet',
			'judul_kecil' => 'Pemasukan dan pengeluaran · input ringkas seperti spreadsheet',
			'm_petty_cash_grid' => TRUE,
			'css_files' => array(
				base_url('assets/jexcel/css/jexcel.css'),
				base_url('assets/jexcel/css/jsuites.css')
			),
			'js_files' => array(
				base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
				base_url('assets/jexcel/js/jsuites.js'),
				base_url('assets/jexcel/js/jexcel.js')
			)
		);
		$data['output'] = $this->load->view('petty_cash_grid', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function submit_rows()
	{
		$this->_require_post();
		$this->_check_token();
		$fund_id = $this->_positive_id($this->input->post('fund_id', TRUE));
		$fund = $fund_id ? $this->db->get_where('acc_cash_funds', array(
			'id' => $fund_id,
			'fund_type' => 'petty',
			'is_active' => 1
		))->row() : NULL;
		if (!$fund) {
			$this->_message('Pilih dana petty cash yang aktif.', FALSE);
			return;
		}
		$raw_rows = $this->input->post('rows');
		if (!is_string($raw_rows) || strlen($raw_rows) > 150000) {
			$this->_message('Data spreadsheet tidak valid atau terlalu besar.', FALSE, $fund_id);
			return;
		}
		$rows = json_decode($raw_rows, TRUE);
		if (!is_array($rows) || count($rows) > 50) {
			$this->_message('Spreadsheet harus berisi paling banyak 50 baris.', FALSE, $fund_id);
			return;
		}
		$accounts = $this->db->where('is_active', 1)->get('acc_accounts')->result();
		$account_by_id = array();
		foreach ($accounts as $account) {
			$account_by_id[(string) $account->id] = $account;
		}
		$clean_rows = array();
		foreach ($rows as $index => $row) {
			if (!is_array($row) || count($row) < 6 ||
				!isset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5]) ||
				!is_scalar($row[0]) || !is_scalar($row[1]) ||
				!is_scalar($row[2]) || !is_scalar($row[3]) ||
				!is_scalar($row[4]) || !is_scalar($row[5])) {
				$this->_message('Format baris '.($index + 1).' tidak valid.', FALSE, $fund_id);
				return;
			}
			$date = trim((string) $row[0]);
			$type = trim((string) $row[1]);
			$account_id = trim((string) $row[2]);
			$description = trim((string) $row[3]);
			$amount_cents = $this->_amount_cents($row[4]);
			$evidence = trim((string) $row[5]);
			if ($date === '' && $type === '' && $account_id === '' && $description === '' &&
				($amount_cents === '0' || $amount_cents === FALSE) && $evidence === '') {
				continue;
			}
			$account = isset($account_by_id[$account_id]) ? $account_by_id[$account_id] : NULL;
			$valid_account = $type === 'expense'
				? ($account && $account->type === 'expense')
				: ($type === 'funding' && $account && $account->code === '1106' &&
					$account->type === 'asset' && $account->normal_balance === 'debit');
			if (!$this->_is_date($date) || $date > date('Y-m-d') ||
				!in_array($type, array('funding', 'expense'), TRUE) ||
				!$valid_account || $description === '' || strlen($description) > 500 ||
				$evidence === '' || strlen($evidence) > 128 ||
				$amount_cents === FALSE || $amount_cents === '0' ||
				$this->_compare_cents($amount_cents, '999999999999999') > 0) {
				$this->_message('Lengkapi tanggal, jenis, akun sesuai jenis transaksi, keterangan, nominal, dan referensi pada baris '.($index + 1).'.', FALSE, $fund_id);
				return;
			}
			if ($type === 'expense' && $fund->custodian_user_id !== NULL &&
				(string) $fund->custodian_user_id !== (string) $this->ion_auth->get_user_id()) {
				$this->_message('Pengeluaran hanya dapat diajukan oleh pemegang petty cash yang ditetapkan.', FALSE, $fund_id);
				return;
			}
			$clean_rows[] = array(
				'date' => $date,
				'type' => $type,
				'account_id' => (int) $account->id,
				'description' => $description,
				'amount' => $this->_cents_amount($amount_cents),
				'evidence' => $evidence
			);
		}
		if (empty($clean_rows)) {
			$this->_message('Isi sedikitnya satu baris transaksi sebelum mengirim.', FALSE, $fund_id);
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Database tidak dapat memulai penyimpanan transaksi.', FALSE, $fund_id);
			return;
		}
		$locked_fund = $this->db->query(
			'SELECT id, is_active FROM acc_cash_funds WHERE id = ? FOR UPDATE',
			array($fund_id)
		)->row();
		if (!$locked_fund || (int) $locked_fund->is_active !== 1) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Dana tidak aktif.', FALSE, $fund_id);
			return;
		}
		$failure = '';
		foreach ($clean_rows as $index => $row) {
			$closing = $this->db->where('fund_id', $fund_id)
				->order_by('closing_date', 'DESC')->limit(1)
				->get('acc_cash_closings')->row();
			if ($closing && $row['date'] <= $closing->closing_date) {
				$failure = 'Tanggal pada baris '.($index + 1).' berada pada atau sebelum tanggal kas yang sudah ditutup.';
				break;
			}
			$transaction_number = 'KAS-'.date('Ymd-His').'-'.strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 8));
			$inserted = $this->db->insert('acc_cash_transactions', array(
				'transaction_number' => $transaction_number,
				'fund_id' => $fund_id,
				'transaction_type' => $row['type'],
				'transaction_date' => $row['date'],
				'amount' => $row['amount'],
				'funding_source_account_id' => $row['type'] === 'funding' ? $row['account_id'] : NULL,
				'expense_account_id' => $row['type'] === 'expense' ? $row['account_id'] : NULL,
				'goods_receipt_id' => NULL,
				'counterparty' => NULL,
				'description' => $row['description'],
				'evidence_reference' => $row['evidence'],
				'proof_filename' => NULL,
				'status' => 'pending',
				'created_by' => (string) $this->ion_auth->get_user_id(),
				'created_at' => date('Y-m-d H:i:s')
			));
			if (!$inserted) {
				$db_error = $this->db->error();
				$failure = 'Gagal menyimpan baris '.($index + 1).': '.$db_error['code'].' '.$db_error['message'];
				break;
			}
		}
		if ($failure !== '' || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Petty Cash V3 gagal menyimpan batch: '.($failure !== '' ? $failure : 'database transaction failed'));
			$this->_message('Tidak ada baris yang disimpan. '.($failure !== '' ? $failure : 'Database menolak batch transaksi.'), FALSE, $fund_id);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_message(count($clean_rows).' transaksi tersimpan dan menunggu persetujuan; saldo belum berubah.', TRUE, $fund_id);
	}

	private function _form_token()
	{
		$token = $this->session->userdata('petty_cash_grid_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('petty_cash_grid_form_token', $token);
		}
		return $token;
	}

	private function _check_token()
	{
		$expected = $this->session->userdata('petty_cash_grid_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' ||
			!is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}
	}

	private function _require_post()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
	}

	private function _message($message, $success, $fund_id = NULL)
	{
		$this->session->set_flashdata($success ? 'petty_cash_grid_notice' : 'petty_cash_grid_error', $message);
		redirect('main/petty_cash_grid'.($fund_id ? '?fund_id='.(int) $fund_id : ''));
	}

	private function _positive_id($value)
	{
		return (is_string($value) || is_int($value)) &&
			ctype_digit((string) $value) && (int) $value > 0
			? (int) $value
			: FALSE;
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

	private function _is_date($value)
	{
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return FALSE;
		}
		$date = DateTime::createFromFormat('!Y-m-d', $value);
		return $date && $date->format('Y-m-d') === $value;
	}
}
