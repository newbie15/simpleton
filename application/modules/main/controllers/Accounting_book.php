<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Tampilan ala GnuCash: Income, Expense, Asset, Liability, Equity di atas buku besar acc_*.
class Accounting_book extends CI_Controller
{
	private $groups = array(
		'income' => array('Pendapatan (Income)', array('income')),
		'expense' => array('Beban (Expense)', array('cogs', 'expense')),
		'asset' => array('Aset (Asset)', array('asset')),
		'liability' => array('Kewajiban (Liability)', array('liability')),
		'equity' => array('Modal (Equity)', array('equity'))
	);

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
			show_error('Anda tidak memiliki akses ke Buku Akun.', 403);
		}
	}

	public function index()
	{
		$tab = trim((string) $this->input->get('tab', TRUE));
		if (!in_array($tab, array('dashboard', 'reporting', 'input', 'accounts'), TRUE)) {
			$tab = 'dashboard';
		}
		$month = trim((string) $this->input->get('month', TRUE));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
			$month = date('Y-m');
		}
		$start = $month.'-01';
		$end = date('Y-m-t', strtotime($start));

		$rows = $this->db->query("
			SELECT account.id, account.code, account.name, account.type,
				account.normal_balance, account.parent_id,
				COALESCE(SUM(CASE WHEN journal.date <= ? THEN item.debit - item.credit ELSE 0 END), 0) AS net_to_date,
				COALESCE(SUM(CASE WHEN journal.date BETWEEN ? AND ? THEN item.debit - item.credit ELSE 0 END), 0) AS net_period
			FROM acc_accounts account
			LEFT JOIN acc_journal_items item ON item.account_id = account.id
			LEFT JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE account.is_active = 1
			GROUP BY account.id, account.code, account.name, account.type,
				account.normal_balance, account.parent_id
			ORDER BY account.code ASC
		", array($end, $start, $end))->result();

		$by_id = array();
		foreach ($rows as $row) {
			$sign = $row->normal_balance === 'debit' ? 1 : -1;
			$row->balance = $sign * (float) $row->net_to_date;
			$row->movement = $sign * (float) $row->net_period;
			$row->total = $row->balance;
			$row->total_movement = $row->movement;
			$row->children = array();
			$by_id[(int) $row->id] = $row;
		}
		$roots = array();
		foreach ($by_id as $row) {
			$parent = (int) $row->parent_id;
			if ($parent && isset($by_id[$parent]) && $by_id[$parent]->type === $row->type) {
				$by_id[$parent]->children[] = $row;
			} else {
				$roots[] = $row;
			}
		}
		foreach ($roots as $root) {
			$this->_roll_up($root);
		}

		$tree = array();
		$sums = array();
		foreach ($this->groups as $key => $group) {
			$tree[$key] = array();
			$sums[$key] = array('balance' => 0, 'movement' => 0);
			foreach ($roots as $root) {
				if (in_array($root->type, $group[1], TRUE)) {
					$tree[$key][] = $root;
					$sums[$key]['balance'] += $root->total;
					$sums[$key]['movement'] += $root->total_movement;
				}
			}
		}

		$accounts = array();
		foreach ($by_id as $row) {
			$accounts[] = array(
				'id' => (int) $row->id,
				'code' => $row->code,
				'name' => $row->name,
				'type' => $row->type,
				'normal_balance' => $row->normal_balance,
				'parent_id' => (int) $row->parent_id
			);
		}

		$data = array(
			'tab' => $tab,
			'month' => $month,
			'start' => $start,
			'end' => $end,
			'groups' => $this->groups,
			'tree' => $tree,
			'sums' => $sums,
			'accounts' => $accounts,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('accounting_book_notice'),
			'error' => $this->session->flashdata('accounting_book_error'),
			'judul_besar' => 'Buku Akun',
			'judul_kecil' => 'Income · Expense · Asset · Liability · Equity',
			'm_accounting_book' => TRUE
		);
		$data['output'] = $this->load->view('accounting_book', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function register($account_id = 0)
	{
		$account = $this->db->get_where('acc_accounts', array('id' => (int) $account_id))->row();
		if (!$account) {
			show_404();
		}
		$month = trim((string) $this->input->get('month', TRUE));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
			$month = date('Y-m');
		}
		$start = $month.'-01';
		$end = date('Y-m-t', strtotime($start));
		$sign = $account->normal_balance === 'debit' ? 1 : -1;

		$opening_row = $this->db->query("
			SELECT COALESCE(SUM(item.debit - item.credit), 0) AS net
			FROM acc_journal_items item
			JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE item.account_id = ? AND journal.date < ?
		", array((int) $account->id, $start))->row();
		$opening = $sign * (float) $opening_row->net;

		$entries = $this->db->query("
			SELECT journal.id AS journal_id, journal.date, journal.journal_number, journal.description,
				item.debit, item.credit,
				(SELECT GROUP_CONCAT(DISTINCT CONCAT(other_account.code, ' ', other_account.name) SEPARATOR ', ')
					FROM acc_journal_items other
					JOIN acc_accounts other_account ON other_account.id = other.account_id
					WHERE other.journal_id = journal.id AND other.account_id <> item.account_id) AS counter
			FROM acc_journal_items item
			JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE item.account_id = ? AND journal.date BETWEEN ? AND ?
			ORDER BY journal.date ASC, journal.id ASC, item.id ASC
		", array((int) $account->id, $start, $end))->result();
		$running = $opening;
		foreach ($entries as $entry) {
			$running += $sign * ((float) $entry->debit - (float) $entry->credit);
			$entry->running = $running;
		}

		$data = array(
			'account' => $account,
			'month' => $month,
			'opening' => $opening,
			'entries' => $entries,
			'closing' => $running,
			'judul_besar' => 'Buku Akun',
			'judul_kecil' => $account->code.' · '.$account->name,
			'm_accounting_book' => TRUE
		);
		$data['output'] = $this->load->view('accounting_book_register', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function chart_data()
	{
		$month = trim((string) $this->input->get('month', TRUE));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
			$month = date('Y-m');
		}
		$mode = $this->input->get('mode', TRUE) === 'breakdown' ? 'breakdown' : 'trend';
		$group = (string) $this->input->get('group', TRUE);
		if ($group !== 'net' && !isset($this->groups[$group])) {
			$group = 'income';
		}
		$months = max(2, min(24, (int) $this->input->get('months')));
		$sign = array('income' => -1, 'cogs' => 1, 'expense' => 1, 'asset' => 1, 'liability' => -1, 'equity' => -1);
		$is_flow = in_array($group, array('income', 'expense', 'net'), TRUE);
		$end = date('Y-m-t', strtotime($month.'-01'));

		if ($mode === 'breakdown') {
			$start = $is_flow ? $month.'-01' : '1000-01-01';
			$types = $group === 'net' ? array('income', 'cogs', 'expense') : $this->groups[$group][1];
			$rows = $this->db->query("
				SELECT account.code, account.name, account.type, SUM(item.debit - item.credit) AS net
				FROM acc_accounts account
				JOIN acc_journal_items item ON item.account_id = account.id
				JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
				WHERE account.type IN ('".implode("','", $types)."') AND journal.date BETWEEN ? AND ?
				GROUP BY account.id, account.code, account.name, account.type
			", array($start, $end))->result();
			$items = array();
			foreach ($rows as $row) {
				$value = $sign[$row->type] * (float) $row->net;
				if ($group === 'net' && $row->type !== 'income') {
					$value = abs($value);
				}
				if (abs($value) >= 0.005) {
					$items[] = array('label' => $row->code.' '.$row->name, 'value' => round($value, 2));
				}
			}
			usort($items, function ($a, $b) { return $a['value'] < $b['value'] ? 1 : ($a['value'] > $b['value'] ? -1 : 0); });
			$items = array_slice($items, 0, 12);
			$this->_json(array(
				'labels' => array_column($items, 'label'),
				'values' => array_column($items, 'value')
			));
			return;
		}

		$first = date('Y-m-01', strtotime($month.'-01 -'.($months - 1).' months'));
		$rows = $this->db->query("
			SELECT DATE_FORMAT(journal.date, '%Y-%m') AS ym, account.type, SUM(item.debit - item.credit) AS net
			FROM acc_journal_items item
			JOIN acc_accounts account ON account.id = item.account_id
			JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE journal.date BETWEEN ? AND ?
			GROUP BY ym, account.type
		", array($first, $end))->result();
		$opening = array();
		if (!$is_flow) {
			foreach ($this->db->query("
				SELECT account.type, SUM(item.debit - item.credit) AS net
				FROM acc_journal_items item
				JOIN acc_accounts account ON account.id = item.account_id
				JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
				WHERE journal.date < ? GROUP BY account.type
			", array($first))->result() as $row) {
				$opening[$row->type] = (float) $row->net;
			}
		}
		$cell = array();
		foreach ($rows as $row) {
			$cell[$row->ym][$row->type] = (float) $row->net;
		}
		$types = $group === 'net' ? array('income', 'cogs', 'expense') : $this->groups[$group][1];
		$running = 0;
		foreach ($types as $type) {
			$running += isset($opening[$type]) ? $sign[$type] * $opening[$type] : 0;
		}
		$labels = array();
		$values = array();
		for ($i = 0; $i < $months; $i++) {
			$ym = date('Y-m', strtotime($first.' +'.$i.' months'));
			$value = 0;
			foreach ($types as $type) {
				$term = isset($cell[$ym][$type]) ? $sign[$type] * $cell[$ym][$type] : 0;
				$value += ($group === 'net' && $type !== 'income') ? -$term : $term;
			}
			if (!$is_flow) {
				$running += $value;
				$value = $running;
			}
			$labels[] = $ym;
			$values[] = round($value, 2);
		}
		$this->_json(array('labels' => $labels, 'values' => $values));
	}

	private function _json($payload)
	{
		$this->output->set_content_type('application/json')->set_output(json_encode($payload));
	}

	public function post_transaction()
	{
		$this->_require_post();
		$this->_check_token();
		$date = trim((string) $this->input->post('date', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$return_month = trim((string) $this->input->post('month', TRUE));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $return_month)) {
			$return_month = date('Y-m');
		}
		$from_id = (int) $this->input->post('from_account');
		$to_id = (int) $this->input->post('to_account');
		$amount = $this->_amount($this->input->post('amount'));

		$from = $this->db->get_where('acc_accounts', array('id' => $from_id, 'is_active' => 1))->row();
		$to = $this->db->get_where('acc_accounts', array('id' => $to_id, 'is_active' => 1))->row();
		if (!$this->_is_date($date) || $date > date('Y-m-d') || $description === '' ||
			strlen($description) > 255 || !$from || !$to || $from_id === $to_id || $amount === FALSE) {
			$this->_flash('Lengkapi tanggal, keterangan, akun asal/tujuan yang berbeda, dan nominal valid.', FALSE, $return_month, 'input');
		}

		$this->load->library('accounting');
		$lines = array(
			array('account' => $to->code, 'debit' => $amount, 'credit' => 0, 'memo' => $description),
			array('account' => $from->code, 'debit' => 0, 'credit' => $amount, 'memo' => $description)
		);
		$ok = $this->accounting->post_journal(
			'MANUAL_BOOK',
			strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 32)),
			$date,
			$description,
			(string) $this->ion_auth->get_user_id(),
			$lines
		);
		if (!$ok) {
			$this->_flash('Transaksi gagal: '.$this->accounting->error(), FALSE, $return_month, 'input');
		}
		$this->_flash('Transaksi diposting: '.$from->name.' → '.$to->name.'.', TRUE, $return_month, 'input');
	}

	public function create_account()
	{
		$this->_require_post();
		$this->_check_token();
		$code = trim((string) $this->input->post('code', TRUE));
		$name = trim((string) $this->input->post('name', TRUE));
		$return_month = trim((string) $this->input->post('month', TRUE));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $return_month)) {
			$return_month = date('Y-m');
		}
		$group = (string) $this->input->post('group');
		$parent_id = (int) $this->input->post('parent_id');
		if (!isset($this->groups[$group]) || !preg_match('/^[0-9A-Za-z.\-]{1,32}$/', $code) ||
			$name === '' || strlen($name) > 128) {
			$this->_flash('Kode (maks 32 karakter alfanumerik), nama, dan kelompok akun wajib valid.', FALSE, $return_month, 'accounts');
		}
		$type = $this->groups[$group][1][count($this->groups[$group][1]) - 1];
		$parent = NULL;
		if ($parent_id) {
			$parent = $this->db->get_where('acc_accounts', array('id' => $parent_id, 'is_active' => 1))->row();
			if (!$parent || !in_array($parent->type, $this->groups[$group][1], TRUE)) {
				$this->_flash('Akun induk harus berada pada kelompok yang sama.', FALSE, $return_month, 'accounts');
			}
			$type = $parent->type;
		}
		if ($this->db->get_where('acc_accounts', array('code' => $code))->row()) {
			$this->_flash('Kode akun sudah dipakai.', FALSE, $return_month, 'accounts');
		}
		$this->db->insert('acc_accounts', array(
			'code' => $code,
			'name' => $name,
			'type' => $type,
			'normal_balance' => in_array($type, array('asset', 'cogs', 'expense'), TRUE) ? 'debit' : 'credit',
			'parent_id' => $parent ? (int) $parent->id : NULL,
			'is_active' => 1
		));
		$this->_flash('Akun '.$code.' '.$name.' dibuat.', TRUE, $return_month, 'accounts');
	}

	private function _roll_up($node)
	{
		foreach ($node->children as $child) {
			$this->_roll_up($child);
			$node->total += $child->total;
			$node->total_movement += $child->total_movement;
		}
	}

	private function _amount($value)
	{
		$value = trim((string) $value);
		if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value) || (float) $value <= 0) {
			return FALSE;
		}
		return round((float) $value, 2);
	}

	private function _is_date($value)
	{
		$date = DateTime::createFromFormat('Y-m-d', (string) $value);
		return $date && $date->format('Y-m-d') === $value;
	}

	private function _form_token()
	{
		$token = $this->session->userdata('accounting_book_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('accounting_book_form_token', $token);
		}
		return $token;
	}

	private function _check_token()
	{
		$expected = $this->session->userdata('accounting_book_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}
	}

	private function _require_post()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
	}

	private function _flash($message, $success, $month = '', $tab = 'dashboard')
	{
		$this->session->set_flashdata($success ? 'accounting_book_notice' : 'accounting_book_error', $message);
		redirect('main/accounting_book?tab='.$tab.($month !== '' ? '&month='.$month : ''));
	}
}
