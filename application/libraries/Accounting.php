<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounting
{
	private $CI;
	private $last_error = '';

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->database();
	}

	public function post_journal($reference_type, $reference_id, $date, $description, $created_by, $lines)
	{
		$this->last_error = '';
		$reference_type = strtoupper(trim((string) $reference_type));
		$reference_id = (string) $reference_id;
		$created_by = (string) $created_by;
		if (!preg_match('/^[A-Z_]{1,32}$/', $reference_type) ||
			$reference_id === '' || strlen($reference_id) > 64 ||
			!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
			$created_by === '' || strlen($created_by) > 32 ||
			!is_array($lines) || count($lines) < 2) {
			return $this->_fail('Data jurnal tidak valid.');
		}

		$existing = $this->CI->db->get_where('acc_journals', array(
			'reference_type' => $reference_type,
			'reference_id' => $reference_id
		))->row();
		if ($existing) {
			return TRUE;
		}

		$periods = $this->CI->db->query("
			SELECT is_closed FROM acc_periods
			WHERE start_date <= ? AND end_date >= ?
			FOR UPDATE
		", array($date, $date))->result();
		foreach ($periods as $period) {
			if ((int) $period->is_closed === 1) {
				return $this->_fail('Periode akuntansi tanggal '.$date.' sudah ditutup.');
			}
		}

		$account_rows = $this->CI->db->where('is_active', 1)->get('acc_accounts')->result();
		$accounts = array();
		foreach ($account_rows as $account) {
			$accounts[$account->code] = $account;
		}

		$clean_lines = array();
		$total_debit = 0;
		$total_credit = 0;
		foreach ($lines as $line) {
			if (!is_array($line) || !isset($line['account'], $line['memo']) ||
				!isset($line['debit'], $line['credit']) ||
				!isset($accounts[$line['account']])) {
				return $this->_fail('Akun atau rincian jurnal tidak valid.');
			}
			$debit = round((float) $line['debit'], 2);
			$credit = round((float) $line['credit'], 2);
			if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) ||
				($debit <= 0 && $credit <= 0) ||
				$debit > 9999999999999.99 || $credit > 9999999999999.99) {
				return $this->_fail('Nilai debit atau kredit jurnal tidak valid.');
			}
			$clean_lines[] = array(
				'account_id' => (int) $accounts[$line['account']]->id,
				'debit' => $debit,
				'credit' => $credit,
				'memo' => substr((string) $line['memo'], 0, 255)
			);
			$total_debit += $debit;
			$total_credit += $credit;
		}
		if (round($total_debit, 2) <= 0 ||
			abs(round($total_debit - $total_credit, 2)) > 0.009) {
			return $this->_fail('Total debit dan kredit jurnal tidak seimbang.');
		}

		$reference_prefix = array(
			'POS' => 'POS',
			'PURCHASING' => 'PUR',
			'SALES_RETURN' => 'RET',
			'SUPPLIER_RETURN' => 'RTS',
			'STOCK_OPNAME' => 'SO'
		);
		$prefix = isset($reference_prefix[$reference_type]) ? $reference_prefix[$reference_type] : 'JRN';
		$journal_number = $prefix.'-'.date('Ymd-His').'-'.strtoupper(substr(md5(uniqid((string) mt_rand(), TRUE)), 0, 8));
		$previous_db_debug = $this->CI->db->db_debug;
		$this->CI->db->db_debug = FALSE;
		$inserted = $this->CI->db->insert('acc_journals', array(
			'journal_number' => $journal_number,
			'date' => $date,
			'reference_type' => $reference_type,
			'reference_id' => $reference_id,
			'description' => substr((string) $description, 0, 65535),
			'is_posted' => 1,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => $created_by
		));
		if (!$inserted) {
			$error = $this->CI->db->error();
			$this->CI->db->db_debug = $previous_db_debug;
			if ((int) $error['code'] === 1062) {
				$existing = $this->CI->db->get_where('acc_journals', array(
					'reference_type' => $reference_type,
					'reference_id' => $reference_id
				))->row();
				if ($existing) {
					return TRUE;
				}
			}
			return $this->_fail('Gagal menyimpan jurnal: '.$error['code'].' '.$error['message']);
		}

		$journal_id = (int) $this->CI->db->insert_id();
		foreach ($clean_lines as $line) {
			if (!$this->CI->db->insert('acc_journal_items', array(
				'journal_id' => $journal_id,
				'account_id' => $line['account_id'],
				'debit' => $line['debit'],
				'credit' => $line['credit'],
				'memo' => $line['memo']
			))) {
				$error = $this->CI->db->error();
				$this->CI->db->db_debug = $previous_db_debug;
				return $this->_fail('Gagal menyimpan detail jurnal: '.$error['code'].' '.$error['message']);
			}
		}
		$this->CI->db->db_debug = $previous_db_debug;
		return TRUE;
	}

	public function error()
	{
		return $this->last_error;
	}

	private function _fail($message)
	{
		$this->last_error = $message;
		return FALSE;
	}
}
