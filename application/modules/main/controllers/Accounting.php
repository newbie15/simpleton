<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounting extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form'));
		$this->load->library(array('session', 'ion_auth'));
		$this->load->library('page_access');

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('accounting')) {
			show_error('Anda tidak memiliki akses ke laporan akuntansi.', 403);
		}
	}

	public function index()
	{
		$start_date = $this->input->get('start_date');
		$end_date = $this->input->get('end_date');
		$today = date('Y-m-d');
		$default_start = date('Y-m-01');
		if (!is_string($start_date) || !$this->_is_date($start_date)) {
			$start_date = $default_start;
		}
		if (!is_string($end_date) || !$this->_is_date($end_date)) {
			$end_date = $today;
		}
		if ($start_date > $end_date) {
			show_error('Tanggal awal laporan tidak boleh setelah tanggal akhir.', 400);
		}

		$accounts = $this->db->query("
			SELECT account.id, account.code, account.name, account.type,
				account.normal_balance,
				COALESCE(SUM(item.debit), 0) AS debit,
				COALESCE(SUM(item.credit), 0) AS credit
			FROM acc_accounts account
			LEFT JOIN acc_journals journal
				ON journal.is_posted = 1
				AND journal.date BETWEEN ? AND ?
			LEFT JOIN acc_journal_items item
				ON item.account_id = account.id AND item.journal_id = journal.id
			WHERE account.is_active = 1
			GROUP BY account.id, account.code, account.name, account.type, account.normal_balance
			ORDER BY account.code ASC
		", array($start_date, $end_date))->result();

		$profit_loss = array();
		$balance_sheet = array();
		$net_income = 0;
		$cash_movement = 0;
		foreach ($accounts as $account) {
			$account->debit = (float) $account->debit;
			$account->credit = (float) $account->credit;
			$account->balance = $account->normal_balance === 'debit'
				? $account->debit - $account->credit
				: $account->credit - $account->debit;
			if (in_array($account->type, array('income', 'cogs', 'expense'), TRUE)) {
				$profit_loss[] = $account;
				if ($account->type === 'income') {
					$net_income += $account->credit - $account->debit;
				} else {
					$net_income -= $account->debit - $account->credit;
				}
			}
			if (in_array($account->code, array('1101', '1102', '1106', '1107'), TRUE)) {
				$cash_movement += $account->debit - $account->credit;
			}
		}

		$balance_accounts = $this->db->query("
			SELECT account.id, account.code, account.name, account.type,
				account.normal_balance,
				COALESCE(SUM(item.debit), 0) AS debit,
				COALESCE(SUM(item.credit), 0) AS credit
			FROM acc_accounts account
			LEFT JOIN acc_journals journal
				ON journal.is_posted = 1 AND journal.date <= ?
			LEFT JOIN acc_journal_items item
				ON item.account_id = account.id AND item.journal_id = journal.id
			WHERE account.is_active = 1
				AND account.type IN ('asset', 'liability', 'equity')
			GROUP BY account.id, account.code, account.name, account.type, account.normal_balance
			ORDER BY account.code ASC
		", array($end_date))->result();
		foreach ($balance_accounts as $account) {
			$account->debit = (float) $account->debit;
			$account->credit = (float) $account->credit;
			$account->balance = $account->normal_balance === 'debit'
				? $account->debit - $account->credit
				: $account->credit - $account->debit;
			$balance_sheet[] = $account;
		}
		$earnings_as_of = $this->db->query("
			SELECT COALESCE(SUM(item.credit - item.debit), 0) AS income_movement
			FROM acc_journals journal
			JOIN acc_journal_items item ON item.journal_id = journal.id
			JOIN acc_accounts account ON account.id = item.account_id
			WHERE journal.is_posted = 1
				AND journal.date <= ?
				AND account.type IN ('income', 'cogs', 'expense')
		", array($end_date))->row();
		$earnings_as_of = (float) $earnings_as_of->income_movement;

		$journals = $this->db->query("
			SELECT journal.id, journal.journal_number, journal.date,
				journal.reference_type, journal.reference_id, journal.description,
				journal.created_by,
				COALESCE(SUM(item.debit), 0) AS total_debit,
				COALESCE(SUM(item.credit), 0) AS total_credit
			FROM acc_journals journal
			LEFT JOIN acc_journal_items item ON item.journal_id = journal.id
			WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
			GROUP BY journal.id
			ORDER BY journal.date DESC, journal.id DESC
			LIMIT 100
		", array($start_date, $end_date))->result();
		$journal_items = array();
		if (count($journals) > 0) {
			$journal_ids = array();
			foreach ($journals as $journal) {
				$journal_ids[] = (int) $journal->id;
			}
			$rows = $this->db->select('item.journal_id, account.code, account.name, item.debit, item.credit, item.memo')
				->from('acc_journal_items item')
				->join('acc_accounts account', 'account.id = item.account_id')
				->where_in('item.journal_id', $journal_ids)
				->order_by('item.id', 'ASC')
				->get()
				->result();
			foreach ($rows as $row) {
				$key = (int) $row->journal_id;
				if (!isset($journal_items[$key])) {
					$journal_items[$key] = array();
				}
				$journal_items[$key][] = $row;
			}
		}

		$ledger_accounts = $this->db->select('id, code, name, normal_balance')
			->where('is_active', 1)
			->order_by('code', 'ASC')
			->get('acc_accounts')
			->result();
		$ledger_account_id = $this->input->get('account_id');
		$ledger_account = NULL;
		foreach ($ledger_accounts as $account) {
			if ((string) $account->id === (string) $ledger_account_id) {
				$ledger_account = $account;
				break;
			}
		}
		if (!$ledger_account && count($ledger_accounts) > 0) {
			$ledger_account = $ledger_accounts[0];
		}

		$ledger_rows = array();
		$ledger_opening = 0;
		$ledger_closing = 0;
		if ($ledger_account) {
			$opening = $this->db->query("
				SELECT COALESCE(SUM(item.debit), 0) AS debit,
					COALESCE(SUM(item.credit), 0) AS credit
				FROM acc_journals journal
				JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date < ?
					AND item.account_id = ?
			", array($start_date, (int) $ledger_account->id))->row();
			$ledger_opening = $ledger_account->normal_balance === 'debit'
				? (float) $opening->debit - (float) $opening->credit
				: (float) $opening->credit - (float) $opening->debit;
			$ledger_closing = $ledger_opening;
			$ledger_rows = $this->db->query("
				SELECT journal.id AS journal_id, journal.date, journal.journal_number,
					journal.reference_type, journal.reference_id, journal.description,
					item.memo, item.debit, item.credit
				FROM acc_journals journal
				JOIN acc_journal_items item ON item.journal_id = journal.id
				WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
					AND item.account_id = ?
				ORDER BY journal.date ASC, journal.id ASC, item.id ASC
				LIMIT 1000
			", array($start_date, $end_date, (int) $ledger_account->id))->result();
			foreach ($ledger_rows as $row) {
				$movement = $ledger_account->normal_balance === 'debit'
					? (float) $row->debit - (float) $row->credit
					: (float) $row->credit - (float) $row->debit;
				$ledger_closing += $movement;
				$row->running_balance = $ledger_closing;
			}
		}

		$cashflow = array(
			'operating' => 0,
			'investing' => 0,
			'financing' => 0,
			'unclassified' => 0
		);
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
		foreach ($cashflow_lines as $line) {
			$line->movement = (float) $line->credit - (float) $line->debit;
			$code_prefix = substr((string) $line->code, 0, 2);
			if ($line->type === 'equity' ||
				($line->type === 'liability' && $code_prefix === '22')) {
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
		$cashflow['unclassified'] += $cash_movement - array_sum($cashflow);

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
		$vat_rows = $this->db->query("
			SELECT journal.date, journal.journal_number, journal.reference_type,
				journal.reference_id, journal.description,
				account.code, account.name, item.memo, item.debit, item.credit
			FROM acc_journal_items item
			JOIN acc_accounts account ON account.id = item.account_id
			JOIN acc_journals journal ON journal.id = item.journal_id
			WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
				AND account.code IN ('1105', '2103')
			ORDER BY journal.date ASC, journal.id ASC, item.id ASC
			LIMIT 1000
		", array($start_date, $end_date))->result();

		$data['start_date'] = $start_date;
		$data['end_date'] = $end_date;
		$data['profit_loss'] = $profit_loss;
		$data['balance_sheet'] = $balance_sheet;
		$data['net_income'] = $net_income;
		$data['earnings_as_of'] = $earnings_as_of;
		$data['cash_movement'] = $cash_movement;
		$data['journals'] = $journals;
		$data['journal_items'] = $journal_items;
		$data['ledger_accounts'] = $ledger_accounts;
		$data['ledger_account'] = $ledger_account;
		$data['ledger_opening'] = $ledger_opening;
		$data['ledger_closing'] = $ledger_closing;
		$data['ledger_rows'] = $ledger_rows;
		$data['cashflow'] = $cashflow;
		$data['cashflow_lines'] = $cashflow_lines;
		$data['vat_accounts'] = $vat_accounts;
		$data['vat_summary'] = $vat_summary;
		$data['vat_rows'] = $vat_rows;
		$data['unposted'] = $this->_get_unposted_counts();
		$data['accounting_periods'] = $this->db->order_by('start_date', 'DESC')
			->order_by('id', 'DESC')
			->limit(100)
			->get('acc_periods')
			->result();
		$data['period_error'] = $this->session->flashdata('accounting_period_error');
		$data['period_notice'] = $this->session->flashdata('accounting_period_notice');
		$data['judul_besar'] = 'Accounting';
		$data['judul_kecil'] = 'Jurnal otomatis dan laporan berdasarkan transaksi yang telah diposting';
		$data['m_accounting'] = TRUE;
		$data['output'] = $this->load->view('accounting', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function create_period()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
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
		$periods = $this->db->query('SELECT id, start_date, end_date FROM acc_periods ORDER BY start_date ASC FOR UPDATE')
			->result();
		foreach ($periods as $period) {
			if ($start_date <= $period->end_date && $end_date >= $period->start_date) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_period_message('Rentang periode bertumpang tindih dengan periode '.$period->start_date.' s.d. '.$period->end_date.'.', FALSE);
				return;
			}
		}

		$this->db->insert('acc_periods', array(
			'period_name' => $name,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'is_closed' => 0,
			'closed_at' => NULL,
			'closed_by' => NULL
		));
		$period_id = (int) $this->db->insert_id();
		$transaction_ok = $period_id > 0 &&
			$this->db->trans_status() !== FALSE &&
			$this->db->trans_commit();
		if (!$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal membuat periode akuntansi '.$name.': '.$error['code'].' '.$error['message']);
			$this->_period_message('Periode akuntansi gagal disimpan.', FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_period_message('Periode '.$name.' berhasil dibuat.', TRUE);
	}

	public function close_period()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$period_id = $this->input->post('period_id', TRUE);
		if (!is_string($period_id) || !ctype_digit($period_id) || (int) $period_id < 1) {
			$this->_period_message('ID periode tidak valid.', FALSE);
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$period = $this->db->query('SELECT * FROM acc_periods WHERE id = ? FOR UPDATE', array((int) $period_id))
			->row();
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
			$message = 'Periode belum dapat ditutup. Transaksi sumber belum terposting: '.
				$integrity['unposted_total'].'; jurnal tidak seimbang/tanpa rincian: '.
				$integrity['unbalanced_journals'].'.';
			$this->_period_message($message, FALSE);
			return;
		}

		$this->db->where('id', (int) $period->id)
			->where('is_closed', 0)
			->update('acc_periods', array(
				'is_closed' => 1,
				'closed_at' => date('Y-m-d H:i:s'),
				'closed_by' => (string) $this->ion_auth->get_user_id()
			));
		$updated = $this->db->affected_rows() === 1;
		$transaction_ok = $updated && $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		if (!$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menutup periode akuntansi '.$period->period_name.': '.$error['code'].' '.$error['message']);
			$this->_period_message('Periode gagal ditutup.', FALSE);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_period_message('Periode '.$period->period_name.' berhasil dikunci. Tidak ada jurnal penutupan SHU yang dibuat.', TRUE);
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

	private function _period_message($message, $success)
	{
		$this->session->set_flashdata(
			$success ? 'accounting_period_notice' : 'accounting_period_error',
			$message
		);
		redirect('main/accounting');
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

	private function _is_date($value)
	{
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return FALSE;
		}
		$parts = explode('-', $value);
		return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
	}
}
