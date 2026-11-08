<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounting_unified extends CI_Controller
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
			show_error('Anda tidak memiliki akses ke ruang kerja akuntansi terpadu.', 403);
		}
	}

	public function index()
	{
		$tab = trim((string) $this->input->get('tab', TRUE));
		$valid_tabs = array('dashboard', 'reports', 'ledger', 'journals', 'input', 'accounts', 'periods');
		if (!in_array($tab, $valid_tabs, TRUE)) {
			$tab = 'dashboard';
		}

		$today = date('Y-m-d');
		$month = trim((string) $this->input->get('month', TRUE));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
			$month = date('Y-m');
		}

		$start_date = trim((string) $this->input->get('start_date', TRUE));
		$end_date = trim((string) $this->input->get('end_date', TRUE));
		if (!$this->_is_date($start_date)) {
			$start_date = $month.'-01';
		}
		if (!$this->_is_date($end_date)) {
			$end_date = date('Y-m-t', strtotime($start_date));
		}
		if ($start_date > $end_date) {
			$end_date = $start_date;
		}

		// Data akun dasar
		$accounts_raw = $this->db->query("
			SELECT account.id, account.code, account.name, account.type,
				account.normal_balance, account.parent_id,
				COALESCE(SUM(CASE WHEN journal.date <= ? THEN item.debit - item.credit ELSE 0 END), 0) AS net_to_date,
				COALESCE(SUM(CASE WHEN journal.date BETWEEN ? AND ? THEN item.debit - item.credit ELSE 0 END), 0) AS net_period,
				COALESCE(SUM(CASE WHEN journal.date BETWEEN ? AND ? THEN item.debit ELSE 0 END), 0) AS period_debit,
				COALESCE(SUM(CASE WHEN journal.date BETWEEN ? AND ? THEN item.credit ELSE 0 END), 0) AS period_credit
			FROM acc_accounts account
			LEFT JOIN acc_journal_items item ON item.account_id = account.id
			LEFT JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE account.is_active = 1
			GROUP BY account.id, account.code, account.name, account.type,
				account.normal_balance, account.parent_id
			ORDER BY account.code ASC
		", array($end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date))->result();

		$by_id = array();
		$account_options = array();
		$totals = array(
			'assets' => 0,
			'liabilities' => 0,
			'equity' => 0,
			'income' => 0,
			'expenses' => 0,
			'cogs' => 0
		);

		foreach ($accounts_raw as $row) {
			$sign = $row->normal_balance === 'debit' ? 1 : -1;
			$row->balance = $sign * (float) $row->net_to_date;
			$row->movement = $sign * (float) $row->net_period;
			$row->total = $row->balance;
			$row->total_movement = $row->movement;
			$row->children = array();
			$by_id[(int) $row->id] = $row;

			$account_options[] = array(
				'id' => (string) $row->id,
				'name' => $row->code.' — '.$row->name,
				'code' => $row->code,
				'type' => $row->type
			);

			if ($row->type === 'asset') $totals['assets'] += $row->balance;
			elseif ($row->type === 'liability') $totals['liabilities'] += $row->balance;
			elseif ($row->type === 'equity') $totals['equity'] += $row->balance;
			elseif ($row->type === 'income') $totals['income'] += $row->movement;
			elseif ($row->type === 'expense') $totals['expenses'] += $row->movement;
			elseif ($row->type === 'cogs') $totals['cogs'] += $row->movement;
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

		// Count Jurnal
		$journal_count_row = $this->db->query("
			SELECT COUNT(*) AS cnt FROM acc_journals
			WHERE is_posted = 1 AND date BETWEEN ? AND ?
		", array($start_date, $end_date))->row();
		$journal_count = $journal_count_row ? (int) $journal_count_row->cnt : 0;

		// TAB LEDGER (Buku Besar)
		$ledger_account_id = (int) $this->input->get('account_id');
		$selected_account = NULL;
		if ($ledger_account_id && isset($by_id[$ledger_account_id])) {
			$selected_account = $by_id[$ledger_account_id];
		} elseif (!empty($by_id)) {
			foreach ($by_id as $acc) {
				if ($acc->code === '1101') {
					$selected_account = $acc;
					break;
				}
			}
			if (!$selected_account) {
				$selected_account = reset($by_id);
			}
		}

		$ledger_rows = array();
		$ledger_opening = 0;
		$ledger_closing = 0;
		if ($selected_account) {
			$sign = $selected_account->normal_balance === 'debit' ? 1 : -1;
			$opening_query = $this->db->query("
				SELECT COALESCE(SUM(item.debit - item.credit), 0) AS net
				FROM acc_journal_items item
				JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
				WHERE item.account_id = ? AND journal.date < ?
			", array((int) $selected_account->id, $start_date))->row();
			$ledger_opening = $sign * (float) $opening_query->net;
			$ledger_closing = $ledger_opening;

			$ledger_rows = $this->db->query("
				SELECT journal.id AS journal_id, journal.date, journal.journal_number,
					journal.reference_type, journal.reference_id, journal.description,
					item.memo, item.debit, item.credit,
					(SELECT GROUP_CONCAT(DISTINCT CONCAT(other_account.code, ' ', other_account.name) SEPARATOR ', ')
						FROM acc_journal_items other
						JOIN acc_accounts other_account ON other_account.id = other.account_id
						WHERE other.journal_id = journal.id AND other.account_id <> item.account_id) AS counter
				FROM acc_journals journal
				JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
					AND item.account_id = ?
				ORDER BY journal.date ASC, journal.id ASC, item.id ASC
				LIMIT 1000
			", array($start_date, $end_date, (int) $selected_account->id))->result();

			foreach ($ledger_rows as $row) {
				$movement = $sign * ((float) $row->debit - (float) $row->credit);
				$ledger_closing += $movement;
				$row->running_balance = $ledger_closing;
			}
		}

		// TAB REPORTS (Arus Kas & PPN)
		$cashflow = array('operating' => 0, 'investing' => 0, 'financing' => 0, 'unclassified' => 0);
		$cashflow_lines = $this->db->query("
			SELECT journal.id AS journal_id, journal.date, journal.journal_number,
				journal.reference_type, journal.description,
				account.code, account.name, account.type,
				SUM(counterpart.debit) AS debit,
				SUM(counterpart.credit) AS credit
			FROM acc_journals journal
			JOIN (
				SELECT item.journal_id, SUM(item.debit) AS debit, SUM(item.credit) AS credit
				FROM acc_journal_items item
				JOIN acc_accounts cash_account ON cash_account.id = item.account_id
				WHERE cash_account.code IN ('1101', '1102', '1106', '1107')
				GROUP BY item.journal_id
			) cash ON cash.journal_id = journal.id
			JOIN acc_journal_items counterpart ON counterpart.journal_id = journal.id
			JOIN acc_accounts account ON account.id = counterpart.account_id
			WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
				AND account.code NOT IN ('1101', '1102', '1106', '1107')
			GROUP BY journal.id, journal.date, journal.journal_number,
				journal.reference_type, journal.description, account.id,
				account.code, account.name, account.type
			ORDER BY journal.date ASC, journal.id ASC, account.code ASC
		", array($start_date, $end_date))->result();

		$cash_movement_sum = 0;
		foreach ($accounts_raw as $a) {
			if (in_array($a->code, array('1101', '1102', '1106', '1107'), TRUE)) {
				$cash_movement_sum += ((float) $a->period_debit - (float) $a->period_credit);
			}
		}

		foreach ($cashflow_lines as $line) {
			$line->movement = (float) $line->credit - (float) $line->debit;
			$code_prefix = substr((string) $line->code, 0, 2);
			if ($line->type === 'equity' || ($line->type === 'liability' && $code_prefix === '22')) {
				$line->cashflow_category = 'financing';
			} elseif ($line->type === 'asset' && $code_prefix === '12') {
				$line->cashflow_category = 'investing';
			} elseif (in_array($line->type, array('asset', 'liability', 'income', 'cogs', 'expense'), TRUE)) {
				$line->cashflow_category = 'operating';
			} else {
				$line->cashflow_category = 'unclassified';
			}
			$cashflow[$line->cashflow_category] += $line->movement;
		}
		$cashflow['unclassified'] += $cash_movement_sum - array_sum($cashflow);

		// PPN summary
		$vat_accounts = $this->db->query("
			SELECT account.code, account.name,
				COALESCE(SUM(CASE WHEN journal.id IS NOT NULL THEN item.debit ELSE 0 END), 0) AS debit,
				COALESCE(SUM(CASE WHEN journal.id IS NOT NULL THEN item.credit ELSE 0 END), 0) AS credit
			FROM acc_accounts account
			LEFT JOIN acc_journal_items item ON item.account_id = account.id
			LEFT JOIN acc_journals journal
				ON journal.id = item.journal_id AND journal.is_posted = 1
				AND journal.date BETWEEN ? AND ?
			WHERE account.code IN ('1105', '2103')
			GROUP BY account.id, account.code, account.name
			ORDER BY account.code ASC
		", array($start_date, $end_date))->result();

		$vat_summary = array('input' => 0, 'output' => 0);
		foreach ($vat_accounts as $account) {
			$account->debit = (float) $account->debit;
			$account->credit = (float) $account->credit;
			$account->movement = $account->code === '1105'
				? $account->debit - $account->credit
				: $account->credit - $account->debit;
			if ($account->code === '1105') {
				$vat_summary['input'] = $account->movement;
			} else {
				$vat_summary['output'] = $account->movement;
			}
		}

		// TAB JOURNALS
		$journals = array();
		$journal_items = array();
		if ($tab === 'journals' || $tab === 'dashboard') {
			$limit = $tab === 'dashboard' ? 10 : 100;
			$journals = $this->db->query("
				SELECT journal.id, journal.journal_number, journal.date,
					journal.reference_type, journal.reference_id, journal.description,
					COALESCE(SUM(item.debit), 0) AS total_debit,
					COALESCE(SUM(item.credit), 0) AS total_credit
				FROM acc_journals journal
				LEFT JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
				GROUP BY journal.id, journal.journal_number, journal.date,
					journal.reference_type, journal.reference_id, journal.description
				ORDER BY journal.date DESC, journal.id DESC
				LIMIT ?
			", array($start_date, $end_date, $limit))->result();

			if (!empty($journals)) {
				$journal_ids = array_map(function ($j) { return (int) $j->id; }, $journals);
				$items_raw = $this->db->query("
					SELECT item.journal_id, account.code, account.name,
						item.memo, item.debit, item.credit
					FROM acc_journal_items item
					JOIN acc_accounts account ON account.id = item.account_id
					WHERE item.journal_id IN (".implode(',', $journal_ids).")
					ORDER BY item.journal_id DESC, item.id ASC
				")->result();
				foreach ($items_raw as $item) {
					$journal_items[(int) $item->journal_id][] = $item;
				}
			}
		}

		// TAB PERIODS & UNPOSTED
		$periods = $this->db->order_by('start_date', 'DESC')
			->order_by('id', 'DESC')
			->limit(100)
			->get('acc_periods')
			->result();
		$unposted = $this->_get_unposted_counts();

		$data = array(
			'tab' => $tab,
			'month' => $month,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'groups' => $this->groups,
			'tree' => $tree,
			'sums' => $sums,
			'totals' => $totals,
			'journal_count' => $journal_count,
			'by_id' => $by_id,
			'account_options' => $account_options,
			'selected_account' => $selected_account,
			'ledger_opening' => $ledger_opening,
			'ledger_closing' => $ledger_closing,
			'ledger_rows' => $ledger_rows,
			'cashflow' => $cashflow,
			'cashflow_lines' => $cashflow_lines,
			'cash_movement' => $cash_movement_sum,
			'vat_accounts' => $vat_accounts,
			'vat_summary' => $vat_summary,
			'journals' => $journals,
			'journal_items' => $journal_items,
			'accounting_periods' => $periods,
			'unposted' => $unposted,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('accounting_notice'),
			'error' => $this->session->flashdata('accounting_error'),
			'period_notice' => $this->session->flashdata('accounting_period_notice'),
			'period_error' => $this->session->flashdata('accounting_period_error'),
			'judul_besar' => 'Akuntansi Terpadu',
			'judul_kecil' => 'Dashboard · Laporan · Buku Besar · Jurnal · Input Transaksi · Bagan Akun · Periode',
			'm_accounting_unified' => TRUE,
			'css_files' => array(
				base_url('assets/jexcel/css/jexcel.css'),
				base_url('assets/jexcel/css/jsuites.css')
			),
			'js_files' => array(
				base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
				base_url('assets/adminlte/plugins/input-mask/jquery.inputmask.js'),
				base_url('assets/adminlte/plugins/input-mask/jquery.inputmask.numeric.extensions.js'),
				base_url('assets/jexcel/js/jsuites.js'),
				base_url('assets/jexcel/js/jexcel.js')
			)
		);

		$data['output'] = $this->load->view('accounting_unified', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	// 1. Chart API (dari Accounting_book)
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

	// 1b. Sankey Flow Data API
	public function sankey_data()
	{
		$start_date = trim((string) $this->input->get('start_date', TRUE));
		$end_date = trim((string) $this->input->get('end_date', TRUE));
		$today = date('Y-m-d');
		if (!$this->_is_date($start_date)) {
			$start_date = date('Y-m-01');
		}
		if (!$this->_is_date($end_date)) {
			$end_date = $today;
		}

		// Hitung aliran dana Pendapatan -> Kas & Piutang -> Beban & Laba
		// 1. Dari Akun Pendapatan (Income)
		$incomes = $this->db->query("
			SELECT account.code, account.name,
				COALESCE(SUM(item.credit - item.debit), 0) AS total
			FROM acc_accounts account
			JOIN acc_journal_items item ON item.account_id = account.id
			JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE account.type = 'income' AND journal.date BETWEEN ? AND ?
			GROUP BY account.id, account.code, account.name
			HAVING total > 0
			ORDER BY total DESC
		", array($start_date, $end_date))->result();

		// 2. Ke Akun HPP (COGS) & Beban (Expense)
		$expenses = $this->db->query("
			SELECT account.code, account.name, account.type,
				COALESCE(SUM(item.debit - item.credit), 0) AS total
			FROM acc_accounts account
			JOIN acc_journal_items item ON item.account_id = account.id
			JOIN acc_journals journal ON journal.id = item.journal_id AND journal.is_posted = 1
			WHERE account.type IN ('cogs', 'expense') AND journal.date BETWEEN ? AND ?
			GROUP BY account.id, account.code, account.name, account.type
			HAVING total > 0
			ORDER BY total DESC
		", array($start_date, $end_date))->result();

		$total_income = 0;
		foreach ($incomes as $inc) {
			$total_income += (float) $inc->total;
		}

		$total_expense = 0;
		foreach ($expenses as $exp) {
			$total_expense += (float) $exp->total;
		}

		$net_profit = $total_income - $total_expense;

		$links = array();
		$central_node = 'Total Pendapatan Operasional';

		foreach ($incomes as $inc) {
			$val = round((float) $inc->total, 2);
			if ($val > 0) {
				$links[] = array(
					'from' => $inc->code.' '.$inc->name,
					'to' => $central_node,
					'value' => $val
				);
			}
		}

		foreach ($expenses as $exp) {
			$val = round((float) $exp->total, 2);
			if ($val > 0) {
				$links[] = array(
					'from' => $central_node,
					'to' => $exp->code.' '.$exp->name,
					'value' => $val
				);
			}
		}

		if ($net_profit > 0) {
			$links[] = array(
				'from' => $central_node,
				'to' => 'Laba Bersih Periode',
				'value' => round($net_profit, 2)
			);
		} elseif ($net_profit < 0) {
			$links[] = array(
				'from' => 'Defisit / Rugi Periode',
				'to' => $central_node,
				'value' => round(abs($net_profit), 2)
			);
		}

		// Bila tidak ada pendapatan sama sekali dalam periode, berikan fallback arus kas bank
		if (empty($links)) {
			$cash_flows = $this->db->query("
				SELECT src.name AS src_name, dest.name AS dest_name, SUM(item_credit.credit) AS flow_amount
				FROM acc_journal_items item_credit
				JOIN acc_accounts src ON src.id = item_credit.account_id
				JOIN acc_journals journal ON journal.id = item_credit.journal_id AND journal.is_posted = 1
				JOIN acc_journal_items item_debit ON item_debit.journal_id = journal.id AND item_debit.account_id <> item_credit.account_id
				JOIN acc_accounts dest ON dest.id = item_debit.account_id
				WHERE item_credit.credit > 0 AND item_debit.debit > 0
					AND journal.date BETWEEN ? AND ?
				GROUP BY src.id, dest.id
				HAVING flow_amount > 0
				ORDER BY flow_amount DESC LIMIT 15
			", array($start_date, $end_date))->result();

			foreach ($cash_flows as $cf) {
				$links[] = array(
					'from' => $cf->src_name,
					'to' => $cf->dest_name,
					'value' => round((float) $cf->flow_amount, 2)
				);
			}
		}

		$this->_json(array(
			'start_date' => $start_date,
			'end_date' => $end_date,
			'total_income' => $total_income,
			'total_expense' => $total_expense,
			'net_profit' => $net_profit,
			'links' => $links
		));
	}

	// 2. Post Transfer Sederhana (Gnucash style)
	public function post_transfer()
	{
		$this->_require_post();
		$this->_check_token();
		$date = trim((string) $this->input->post('date', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$from_id = (int) $this->input->post('from_account');
		$to_id = (int) $this->input->post('to_account');
		$amount = $this->_amount($this->input->post('amount'));

		$from = $this->db->get_where('acc_accounts', array('id' => $from_id, 'is_active' => 1))->row();
		$to = $this->db->get_where('acc_accounts', array('id' => $to_id, 'is_active' => 1))->row();
		if (!$this->_is_date($date) || $date > date('Y-m-d') || $description === '' ||
			strlen($description) > 255 || !$from || !$to || $from_id === $to_id || $amount === FALSE) {
			$this->_flash('Lengkapi tanggal, keterangan, akun asal & tujuan berbeda, dan nominal valid.', FALSE, 'input');
			return;
		}

		$this->load->library('accounting');
		$lines = array(
			array('account' => $to->code, 'debit' => $amount, 'credit' => 0, 'memo' => $description),
			array('account' => $from->code, 'debit' => 0, 'credit' => $amount, 'memo' => $description)
		);
		$ok = $this->accounting->post_journal(
			'MANUAL_TRANSFER',
			strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 32)),
			$date,
			$description,
			(string) $this->ion_auth->get_user_id(),
			$lines
		);
		if (!$ok) {
			$this->_flash('Transfer gagal: '.$this->accounting->error(), FALSE, 'input');
			return;
		}
		$this->_flash('Transfer berhasil: '.$from->name.' → '.$to->name.' (Rp '.number_format($amount, 0, ',', '.').').', TRUE, 'input');
	}

	// 3. Post Jurnal Berpasangan Multi-baris (Accounting V2)
	public function post_journal()
	{
		$this->_require_post();
		$this->_check_token();
		$date = trim((string) $this->input->post('date', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$account_ids = (array) $this->input->post('account_id');
		$memos = (array) $this->input->post('memo');
		$debits = (array) $this->input->post('debit');
		$credits = (array) $this->input->post('credit');

		if (!$this->_is_date($date) || $date > date('Y-m-d')) {
			$this->_flash('Tanggal jurnal tidak valid atau melebihi hari ini.', FALSE, 'input');
			return;
		}
		if ($description === '' || strlen($description) > 2000) {
			$this->_flash('Keterangan jurnal wajib diisi (maks 2.000 karakter).', FALSE, 'input');
			return;
		}

		$count = count($account_ids);
		if ($count < 2 || $count > 50) {
			$this->_flash('Jurnal harus memiliki antara 2 sampai 50 baris.', FALSE, 'input');
			return;
		}

		$total_debit = 0;
		$total_credit = 0;
		$lines = array();

		for ($i = 0; $i < $count; $i++) {
			$acc_id = (int) (isset($account_ids[$i]) ? $account_ids[$i] : 0);
			$account = $this->db->get_where('acc_accounts', array('id' => $acc_id, 'is_active' => 1))->row();
			if (!$account) {
				$this->_flash('Akun pada baris '.($i + 1).' tidak ditemukan.', FALSE, 'input');
				return;
			}

			$deb = 0;
			if (isset($debits[$i]) && trim((string)$debits[$i]) !== '') {
				$parsed = $this->_amount($debits[$i]);
				$deb = $parsed !== FALSE ? $parsed : 0;
			}
			$cred = 0;
			if (isset($credits[$i]) && trim((string)$credits[$i]) !== '') {
				$parsed = $this->_amount($credits[$i]);
				$cred = $parsed !== FALSE ? $parsed : 0;
			}
			if ($deb < 0 || $cred < 0 || ($deb == 0 && $cred == 0) || ($deb > 0 && $cred > 0)) {
				$this->_flash('Setiap baris harus memiliki salah satu dari nilai debit atau kredit positif.', FALSE, 'input');
				return;
			}

			$memo = isset($memos[$i]) ? trim((string) $memos[$i]) : '';
			$lines[] = array(
				'account' => $account->code,
				'debit' => $deb,
				'credit' => $cred,
				'memo' => $memo !== '' ? $memo : $description
			);
			$total_debit += $deb;
			$total_credit += $cred;
		}

		if (abs($total_debit - $total_credit) >= 0.01 || $total_debit <= 0) {
			$this->_flash('Total debit (Rp '.number_format($total_debit, 2).') harus sama dengan total kredit (Rp '.number_format($total_credit, 2).').', FALSE, 'input');
			return;
		}

		$this->load->library('accounting');
		$ok = $this->accounting->post_journal(
			'MANUAL_ENTRY',
			strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 32)),
			$date,
			$description,
			(string) $this->ion_auth->get_user_id(),
			$lines
		);

		if (!$ok) {
			$this->_flash('Gagal posting jurnal: '.$this->accounting->error(), FALSE, 'input');
			return;
		}
		$this->_flash('Jurnal berhasil diposting seimbang (Total Rp '.number_format($total_debit, 0, ',', '.').').', TRUE, 'journals');
	}

	// 4. Post Spreadsheet Grid Batch (Accounting V3)
	public function post_grid()
	{
		$this->_require_post();
		$this->_check_token();
		$raw_rows = $this->input->post('rows');
		if (!is_string($raw_rows) || strlen($raw_rows) > 150000) {
			$this->_flash('Data spreadsheet tidak valid atau terlalu besar.', FALSE, 'input');
			return;
		}
		$rows = json_decode($raw_rows, TRUE);
		if (!is_array($rows) || count($rows) > 50) {
			$this->_flash('Spreadsheet harus berisi maksimal 50 baris.', FALSE, 'input');
			return;
		}

		$active_accounts = $this->db->where('is_active', 1)->get('acc_accounts')->result();
		$accounts = array();
		foreach ($active_accounts as $account) {
			$accounts[(string) $account->id] = $account;
		}

		$clean_entries = array();
		foreach ($rows as $index => $row) {
			if (!is_array($row) || count($row) < 5) continue;
			$tgl = trim((string) $row[0]);
			$desc = trim((string) $row[1]);
			$deb_acc_id = trim((string) $row[2]);
			$cred_acc_id = trim((string) $row[3]);
			$amount_val = trim((string) $row[4]);

			if ($tgl === '' && $desc === '' && $deb_acc_id === '' && $cred_acc_id === '' && $amount_val === '') {
				continue;
			}

			if (!$this->_is_date($tgl) || $tgl > date('Y-m-d')) {
				$this->_flash('Baris '.($index + 1).': Tanggal tidak valid atau melebihi hari ini.', FALSE, 'input');
				return;
			}
			if ($desc === '' || strlen($desc) > 255) {
				$this->_flash('Baris '.($index + 1).': Uraian transaksi wajib diisi (maks 255 karakter).', FALSE, 'input');
				return;
			}
			if (!isset($accounts[$deb_acc_id]) || !isset($accounts[$cred_acc_id]) || $deb_acc_id === $cred_acc_id) {
				$this->_flash('Baris '.($index + 1).': Pilih akun debit dan kredit yang berbeda dan valid.', FALSE, 'input');
				return;
			}
			$amount = $this->_amount($amount_val);
			if ($amount === FALSE || $amount <= 0) {
				$this->_flash('Baris '.($index + 1).': Nominal harus angka positif valid.', FALSE, 'input');
				return;
			}

			$clean_entries[] = array(
				'date' => $tgl,
				'description' => $desc,
				'debit_acc' => $accounts[$deb_acc_id],
				'credit_acc' => $accounts[$cred_acc_id],
				'amount' => $amount
			);
		}

		if (empty($clean_entries)) {
			$this->_flash('Tidak ada baris jurnal yang valid untuk disimpan.', FALSE, 'input');
			return;
		}

		$this->load->library('accounting');
		$posted_count = 0;
		$user_id = (string) $this->ion_auth->get_user_id();

		foreach ($clean_entries as $entry) {
			$lines = array(
				array('account' => $entry['debit_acc']->code, 'debit' => $entry['amount'], 'credit' => 0, 'memo' => $entry['description']),
				array('account' => $entry['credit_acc']->code, 'debit' => 0, 'credit' => $entry['amount'], 'memo' => $entry['description'])
			);
			$ok = $this->accounting->post_journal(
				'GRID_BATCH',
				strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 32)),
				$entry['date'],
				$entry['description'],
				$user_id,
				$lines
			);
			if (!$ok) {
				$this->_flash('Gagal pada baris "'.html_escape($entry['description']).'": '.$this->accounting->error(), FALSE, 'input');
				return;
			}
			$posted_count++;
		}

		$this->_flash('Berhasil menyimpan '.$posted_count.' jurnal dari spreadsheet!', TRUE, 'journals');
	}

	// 5. Buat Akun Baru (Accounting_book)
	public function create_account()
	{
		$this->_require_post();
		$this->_check_token();
		$code = trim((string) $this->input->post('code', TRUE));
		$name = trim((string) $this->input->post('name', TRUE));
		$group = (string) $this->input->post('group');
		$parent_id = (int) $this->input->post('parent_id');

		if (!isset($this->groups[$group]) || !preg_match('/^[0-9A-Za-z.\-]{1,32}$/', $code) ||
			$name === '' || strlen($name) > 128) {
			$this->_flash('Kode (maks 32 alfanumerik), nama, dan kelompok akun wajib valid.', FALSE, 'accounts');
			return;
		}
		$type = $this->groups[$group][1][count($this->groups[$group][1]) - 1];
		$parent = NULL;
		if ($parent_id) {
			$parent = $this->db->get_where('acc_accounts', array('id' => $parent_id, 'is_active' => 1))->row();
			if (!$parent || !in_array($parent->type, $this->groups[$group][1], TRUE)) {
				$this->_flash('Akun induk harus berada pada kelompok yang sama.', FALSE, 'accounts');
				return;
			}
			$type = $parent->type;
		}
		if ($this->db->get_where('acc_accounts', array('code' => $code))->row()) {
			$this->_flash('Kode akun '.$code.' sudah digunakan.', FALSE, 'accounts');
			return;
		}

		$this->db->insert('acc_accounts', array(
			'code' => $code,
			'name' => $name,
			'type' => $type,
			'normal_balance' => in_array($type, array('asset', 'cogs', 'expense'), TRUE) ? 'debit' : 'credit',
			'parent_id' => $parent ? (int) $parent->id : NULL,
			'is_active' => 1
		));
		$this->_flash('Akun baru '.$code.' — '.$name.' berhasil dibuat.', TRUE, 'accounts');
	}

	// 6. Buat Periode Akuntansi (Accounting lama)
	public function create_period()
	{
		$this->_require_post();
		$name = trim((string) $this->input->post('period_name', TRUE));
		$start_date = trim((string) $this->input->post('period_start_date', TRUE));
		$end_date = trim((string) $this->input->post('period_end_date', TRUE));
		if ($name === '' || strlen($name) > 32 ||
			!$this->_is_date($start_date) || !$this->_is_date($end_date) ||
			$start_date > $end_date) {
			$this->_period_message('Nama atau rentang tanggal periode tidak valid.', FALSE);
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$periods = $this->db->query('SELECT id, start_date, end_date FROM acc_periods ORDER BY start_date ASC FOR UPDATE')->result();
		foreach ($periods as $period) {
			if ($start_date <= $period->end_date && $end_date >= $period->start_date) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_period_message('Rentang periode bertumpang tindih dengan '.$period->start_date.' s.d. '.$period->end_date.'.', FALSE);
				return;
			}
		}

		$this->db->insert('acc_periods', array(
			'period_name' => $name,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'is_closed' => 0
		));
		$period_id = (int) $this->db->insert_id();
		$transaction_ok = $period_id > 0 && $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		$this->db->db_debug = $previous_db_debug;

		if (!$transaction_ok) {
			$this->_period_message('Periode akuntansi gagal disimpan.', FALSE);
			return;
		}
		$this->_period_message('Periode '.$name.' berhasil dibuat.', TRUE);
	}

	// 7. Tutup Periode Akuntansi (Accounting lama)
	public function close_period()
	{
		$this->_require_post();
		$period_id = (int) $this->input->post('period_id', TRUE);
		if ($period_id < 1) {
			$this->_period_message('ID periode tidak valid.', FALSE);
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$period = $this->db->query('SELECT * FROM acc_periods WHERE id = ? FOR UPDATE', array($period_id))->row();
		if (!$period || (int) $period->is_closed === 1) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_period_message('Periode tidak ditemukan atau sudah ditutup.', FALSE);
			return;
		}
		if ($period->end_date >= date('Y-m-d')) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_period_message('Periode hanya dapat ditutup setelah tanggal akhirnya berlalu.', FALSE);
			return;
		}

		$integrity = $this->_get_period_integrity($period->start_date, $period->end_date);
		if ($integrity['unposted_total'] > 0 || $integrity['unbalanced_journals'] > 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_period_message('Periode belum dapat ditutup. Transaksi belum terposting: '.$integrity['unposted_total'].'; jurnal tidak seimbang: '.$integrity['unbalanced_journals'].'.', FALSE);
			return;
		}

		$this->db->where('id', $period_id)->update('acc_periods', array(
			'is_closed' => 1,
			'closed_at' => date('Y-m-d H:i:s'),
			'closed_by' => (string) $this->ion_auth->get_user_id()
		));
		$transaction_ok = $this->db->affected_rows() === 1 && $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		$this->db->db_debug = $previous_db_debug;

		if (!$transaction_ok) {
			$this->_period_message('Periode gagal ditutup.', FALSE);
			return;
		}
		$this->_period_message('Periode '.$period->period_name.' berhasil dikunci.', TRUE);
	}

	private function _roll_up($node)
	{
		foreach ($node->children as $child) {
			$this->_roll_up($child);
			$node->total += $child->total;
			$node->total_movement += $child->total_movement;
		}
	}

	private function _get_unposted_counts()
	{
		$counts = array(
			'POS' => (int) $this->db->query("
				SELECT COUNT(DISTINCT sale.nota) AS total
				FROM penjualan sale
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'POS' AND journal.reference_id = sale.nota
				WHERE journal.id IS NULL
			")->row()->total,
			'PURCHASING' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM goods_receipts receipt
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'PURCHASING'
					AND journal.reference_id = CAST(receipt.id AS CHAR)
				WHERE journal.id IS NULL
			")->row()->total,
			'SALES_RETURN' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM retur_penjualan item
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'SALES_RETURN'
					AND journal.reference_id = CAST(item.id AS CHAR)
				WHERE item.status = 'completed' AND journal.id IS NULL
			")->row()->total,
			'SUPPLIER_RETURN' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM retur_supplier item
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'SUPPLIER_RETURN'
					AND journal.reference_id = CAST(item.id AS CHAR)
				WHERE item.status = 'final' AND journal.id IS NULL
			")->row()->total,
			'STOCK_OPNAME' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM (
					SELECT opname.id
					FROM stock_opnames opname
					JOIN stock_opname_items item ON item.stock_opname_id = opname.id
					LEFT JOIN acc_journals journal
						ON journal.reference_type = 'STOCK_OPNAME'
						AND journal.reference_id = CAST(opname.id AS CHAR)
					WHERE opname.status = 'finalized'
					GROUP BY opname.id
					HAVING SUM(ABS(item.counted_qty - item.system_qty)) > 0
						AND COUNT(journal.id) = 0
				) missing_opnames
			")->row()->total
		);
		if ($this->db->table_exists('acc_cash_transactions')) {
			$counts['PETTY_CASH'] = (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM acc_cash_transactions cash_tx
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'PETTY_CASH'
					AND journal.reference_id = CAST(cash_tx.id AS CHAR)
				WHERE cash_tx.status = 'pending'
					OR (cash_tx.status = 'posted' AND journal.id IS NULL)
			")->row()->total;
		}
		return $counts;
	}

	private function _get_period_integrity($start_date, $end_date)
	{
		$unposted = array(
			'POS' => (int) $this->db->query("
				SELECT COUNT(*) AS total FROM (
					SELECT sale.nota
					FROM penjualan sale
					LEFT JOIN acc_journals journal
						ON journal.reference_type = 'POS' AND journal.reference_id = sale.nota
					WHERE DATE(sale.tgl) BETWEEN ? AND ? AND journal.id IS NULL
					GROUP BY sale.nota
				) missing
			", array($start_date, $end_date))->row()->total,
			'PURCHASING' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM goods_receipts receipt
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'PURCHASING'
					AND journal.reference_id = CAST(receipt.id AS CHAR)
				WHERE DATE(receipt.received_at) BETWEEN ? AND ? AND journal.id IS NULL
			", array($start_date, $end_date))->row()->total,
			'SALES_RETURN' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM retur_penjualan item
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'SALES_RETURN'
					AND journal.reference_id = CAST(item.id AS CHAR)
				WHERE item.status = 'completed'
					AND DATE(item.tgl_retur) BETWEEN ? AND ? AND journal.id IS NULL
			", array($start_date, $end_date))->row()->total,
			'SUPPLIER_RETURN' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM retur_supplier item
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'SUPPLIER_RETURN'
					AND journal.reference_id = CAST(item.id AS CHAR)
				WHERE item.status = 'final'
					AND DATE(item.tgl_retur) BETWEEN ? AND ? AND journal.id IS NULL
			", array($start_date, $end_date))->row()->total,
			'STOCK_OPNAME' => (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM (
					SELECT opname.id
					FROM stock_opnames opname
					JOIN stock_opname_items item ON item.stock_opname_id = opname.id
					LEFT JOIN acc_journals journal
						ON journal.reference_type = 'STOCK_OPNAME'
						AND journal.reference_id = CAST(opname.id AS CHAR)
					WHERE opname.status = 'finalized'
						AND DATE(opname.finalized_at) BETWEEN ? AND ?
					GROUP BY opname.id
					HAVING SUM(ABS(item.counted_qty - item.system_qty)) > 0
						AND COUNT(journal.id) = 0
				) missing
			", array($start_date, $end_date))->row()->total
		);
		if ($this->db->table_exists('acc_cash_transactions')) {
			$unposted['PETTY_CASH'] = (int) $this->db->query("
				SELECT COUNT(*) AS total
				FROM acc_cash_transactions cash_tx
				LEFT JOIN acc_journals journal
					ON journal.reference_type = 'PETTY_CASH'
					AND journal.reference_id = CAST(cash_tx.id AS CHAR)
				WHERE (cash_tx.status = 'pending'
					OR (cash_tx.status = 'posted' AND journal.id IS NULL))
					AND cash_tx.transaction_date BETWEEN ? AND ?
			", array($start_date, $end_date))->row()->total;
		}
		$unbalanced = (int) $this->db->query("
			SELECT COUNT(*) AS total FROM (
				SELECT journal.id
				FROM acc_journals journal
				LEFT JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
				GROUP BY journal.id
				HAVING COUNT(item.id) < 2
					OR ABS(COALESCE(SUM(item.debit), 0) - COALESCE(SUM(item.credit), 0)) > 0.009
			) invalid_journals
		", array($start_date, $end_date))->row()->total;

		return array(
			'unposted' => $unposted,
			'unposted_total' => array_sum($unposted),
			'unbalanced_journals' => $unbalanced
		);
	}

	private function _amount($value)
	{
		$value = trim((string) $value);
		// Normalisasi format ribuan Indonesia (1.500.000 atau 1.500.000,50) maupun US (1,500,000.50)
		if (strpos($value, '.') !== FALSE && strpos($value, ',') !== FALSE) {
			// Ada titik dan koma, cek mana yang di belakang
			if (strrpos($value, ',') > strrpos($value, '.')) {
				// Format Indonesia: 1.500.000,50 -> hapus titik, ubah koma jadi titik
				$value = str_replace('.', '', $value);
				$value = str_replace(',', '.', $value);
			} else {
				// Format US: 1,500,000.50 -> hapus koma
				$value = str_replace(',', '', $value);
			}
		} elseif (strpos($value, '.') !== FALSE) {
			// Hanya titik: jika polanya ribuan 1.000 atau 1.500.000 (3 digit per group)
			if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
				$value = str_replace('.', '', $value);
			}
		} elseif (strpos($value, ',') !== FALSE) {
			// Hanya koma: jika koma ribuan 1,000,000 atau desimal 1500,50
			if (preg_match('/^\d{1,3}(?:,\d{3})+$/', $value)) {
				$value = str_replace(',', '', $value);
			} else {
				$value = str_replace(',', '.', $value);
			}
		}

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
		$token = $this->session->userdata('accounting_unified_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('accounting_unified_form_token', $token);
		}
		return $token;
	}

	private function _check_token()
	{
		$expected = $this->session->userdata('accounting_unified_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman.', 403);
		}
	}

	private function _require_post()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
	}

	private function _json($payload)
	{
		$this->output->set_content_type('application/json')->set_output(json_encode($payload));
	}

	private function _flash($message, $success, $tab = 'dashboard')
	{
		$this->session->set_flashdata($success ? 'accounting_notice' : 'accounting_error', $message);
		redirect('main/accounting_unified?tab='.$tab);
	}

	private function _period_message($message, $success)
	{
		$this->session->set_flashdata($success ? 'accounting_period_notice' : 'accounting_period_error', $message);
		redirect('main/accounting_unified?tab=periods');
	}
}
