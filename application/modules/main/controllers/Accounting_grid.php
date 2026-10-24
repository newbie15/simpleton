<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounting_grid extends CI_Controller
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
		if (!$this->page_access->can_access('accounting')) {
			show_error('Anda tidak memiliki akses ke Accounting V3.', 403);
		}
	}

	public function index()
	{
		$accounts = $this->db->where('is_active', 1)
			->order_by('code', 'ASC')->get('acc_accounts')->result();
		$account_options = array();
		foreach ($accounts as $account) {
			$account_options[] = array(
				'id' => (string) $account->id,
				'name' => $account->code.' — '.$account->name
			);
		}
		$data = array(
			'accounts' => $account_options,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('accounting_grid_notice'),
			'error' => $this->session->flashdata('accounting_grid_error'),
			'judul_besar' => 'Accounting V3 — Input Spreadsheet',
			'judul_kecil' => 'Jurnal berpasangan · ketik, pilih akun, lalu simpan',
			'm_accounting_grid' => TRUE,
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
		$data['output'] = $this->load->view('accounting_grid', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function post_rows()
	{
		$this->_require_post();
		$this->_check_token('accounting_grid_form_token');
		$raw_rows = $this->input->post('rows');
		if (!is_string($raw_rows) || strlen($raw_rows) > 150000) {
			$this->_message('Data spreadsheet tidak valid atau terlalu besar.', FALSE);
			return;
		}
		$rows = json_decode($raw_rows, TRUE);
		if (!is_array($rows) || count($rows) > 50) {
			$this->_message('Spreadsheet harus berisi paling banyak 50 baris.', FALSE);
			return;
		}

		$active_accounts = $this->db->where('is_active', 1)->get('acc_accounts')->result();
		$accounts = array();
		foreach ($active_accounts as $account) {
			$accounts[(string) $account->id] = $account;
		}
		$clean_rows = array();
		foreach ($rows as $index => $row) {
			if (!is_array($row) || count($row) < 5 ||
				!isset($row[0], $row[1], $row[2], $row[3], $row[4]) ||
				!is_scalar($row[0]) || !is_scalar($row[1]) ||
				!is_scalar($row[2]) || !is_scalar($row[3]) || !is_scalar($row[4])) {
				$this->_message('Format baris '.($index + 1).' tidak valid.', FALSE);
				return;
			}
			$date = trim((string) $row[0]);
			$description = trim((string) $row[1]);
			$debit_id = trim((string) $row[2]);
			$credit_id = trim((string) $row[3]);
			$amount_cents = $this->_amount_cents($row[4]);
			if ($date === '' && $description === '' && $debit_id === '' &&
				$credit_id === '' && ($amount_cents === '0' || $amount_cents === FALSE)) {
				continue;
			}
			if (!$this->_is_date($date) || $date > date('Y-m-d') ||
				$description === '' || strlen($description) > 2000 ||
				!isset($accounts[$debit_id], $accounts[$credit_id]) ||
				$debit_id === $credit_id || $amount_cents === FALSE ||
				$amount_cents === '0' ||
				$this->_compare_cents($amount_cents, '999999999999999') > 0) {
				$this->_message('Lengkapi tanggal, uraian, akun debit/kredit yang berbeda, dan nominal valid pada baris '.($index + 1).'.', FALSE);
				return;
			}
			$clean_rows[] = array(
				'date' => $date,
				'description' => $description,
				'debit' => $accounts[$debit_id],
				'credit' => $accounts[$credit_id],
				'amount' => $this->_cents_amount($amount_cents)
			);
		}
		if (empty($clean_rows)) {
			$this->_message('Isi sedikitnya satu baris jurnal sebelum menyimpan.', FALSE);
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_message('Database tidak dapat memulai pencatatan jurnal.', FALSE);
			return;
		}
		$this->load->library('accounting');
		$failure = '';
		foreach ($clean_rows as $row) {
			$reference_id = strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 32));
			$lines = array(
				array('account' => $row['debit']->code, 'debit' => $row['amount'], 'credit' => 0, 'memo' => $row['description']),
				array('account' => $row['credit']->code, 'debit' => 0, 'credit' => $row['amount'], 'memo' => $row['description'])
			);
			if (!$this->accounting->post_journal(
				'MANUAL_GRID',
				$reference_id,
				$row['date'],
				$row['description'],
				(string) $this->ion_auth->get_user_id(),
				$lines
			)) {
				$failure = $this->accounting->error();
				break;
			}
		}
		if ($failure !== '' || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Accounting V3 gagal menyimpan batch jurnal: '.($failure !== '' ? $failure : 'database transaction failed'));
			$this->_message('Tidak ada baris yang disimpan. '.($failure !== '' ? $failure : 'Database menolak batch jurnal.'), FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_message(count($clean_rows).' jurnal berhasil diposting ke buku besar.', TRUE);
	}

	private function _form_token()
	{
		$token = $this->session->userdata('accounting_grid_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('accounting_grid_form_token', $token);
		}
		return $token;
	}

	private function _check_token($key)
	{
		$expected = $this->session->userdata($key);
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

	private function _message($message, $success)
	{
		$this->session->set_flashdata($success ? 'accounting_grid_notice' : 'accounting_grid_error', $message);
		redirect('main/accounting_grid');
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
