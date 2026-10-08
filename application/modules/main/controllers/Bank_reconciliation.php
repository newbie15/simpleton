<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bank_reconciliation extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form'));
		$this->load->library(array('session', 'ion_auth'));

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->ion_auth->in_group(array('admin', 'management', 'pengurus'))) {
			show_error('Anda tidak memiliki akses ke rekonsiliasi bank.', 403);
		}
	}

	public function index()
	{
		$statements = $this->db->query("
			SELECT statement.id, statement.statement_number, statement.start_date,
				statement.end_date, statement.opening_balance, statement.closing_balance,
				statement.status, account.code AS account_code, account.name AS account_name,
				COUNT(item.id) AS item_count
			FROM acc_bank_statements statement
			JOIN acc_accounts account ON account.id = statement.account_id
			LEFT JOIN acc_bank_statement_items item ON item.statement_id = statement.id
			GROUP BY statement.id
			ORDER BY statement.start_date DESC, statement.id DESC
			LIMIT 100
		")->result();

		$requested_id = $this->input->get('statement_id');
		$statement_id = is_string($requested_id) && ctype_digit($requested_id)
			? (int) $requested_id
			: 0;
		$statement = $statement_id > 0 ? $this->_get_statement($statement_id) : NULL;
		if (!$statement && count($statements) > 0) {
			$statement = $this->_get_statement((int) $statements[0]->id);
		}

		$items = array();
		$candidates = array();
		$unmatched_book_items = array();
		$statement_total = 0;
		$unmatched_statement_count = 0;
		$book_balance = 0;
		$unmatched_book_total = 0;
		$adjusted_statement_balance = 0;
		$balance_difference = 0;
		if ($statement) {
			$items = $this->db->query("
				SELECT item.id, item.transaction_date, item.description,
					item.external_reference, item.amount,
					item.matched_journal_item_id, item.matched_at,
					journal.journal_number, journal.date AS journal_date,
					journal.reference_type, journal.reference_id,
					journal_item.memo AS journal_memo
				FROM acc_bank_statement_items item
				LEFT JOIN acc_journal_items journal_item
					ON journal_item.id = item.matched_journal_item_id
				LEFT JOIN acc_journals journal
					ON journal.id = journal_item.journal_id
				WHERE item.statement_id = ?
				ORDER BY item.transaction_date ASC, item.id ASC
			", array((int) $statement->id))->result();
			foreach ($items as $item) {
				$statement_total += (float) $item->amount;
				if (!$item->matched_journal_item_id) {
					$unmatched_statement_count++;
				}
			}
			$statement_total = round($statement_total, 2);

			$book = $this->db->query("
				SELECT COALESCE(SUM(item.debit - item.credit), 0) AS balance
				FROM acc_journal_items item
				JOIN acc_journals journal ON journal.id = item.journal_id
				WHERE journal.is_posted = 1 AND journal.date <= ?
					AND item.account_id = ?
			", array($statement->end_date, (int) $statement->account_id))->row();
			$book_balance = (float) $book->balance;

			$unmatched_book_items = $this->db->query("
				SELECT item.id AS journal_item_id, journal.date,
					journal.journal_number, journal.reference_type,
					journal.reference_id, journal.description, item.memo,
					item.debit - item.credit AS amount
				FROM acc_journal_items item
				JOIN acc_journals journal ON journal.id = item.journal_id
				WHERE journal.is_posted = 1
					AND journal.date BETWEEN ? AND ?
					AND item.account_id = ?
					AND NOT EXISTS (
						SELECT 1
						FROM acc_bank_statement_items matched_item
						WHERE matched_item.matched_journal_item_id = item.id
					)
				ORDER BY journal.date ASC, journal.id ASC, item.id ASC
				LIMIT 500
			", array(
				$statement->start_date,
				$statement->end_date,
				(int) $statement->account_id
			))->result();
			foreach ($unmatched_book_items as $item) {
				$unmatched_book_total += (float) $item->amount;
			}
			$unmatched_book_total = round($unmatched_book_total, 2);
			$adjusted_statement_balance = round(
				(float) $statement->closing_balance + $unmatched_book_total,
				2
			);
			$balance_difference = round($adjusted_statement_balance - $book_balance, 2);

			if ($statement->status === 'draft') {
				$candidates = $this->db->query("
					SELECT item.id AS journal_item_id, journal.date,
						journal.journal_number, journal.reference_type,
						journal.reference_id, journal.description, item.memo,
						item.debit - item.credit AS amount
					FROM acc_journal_items item
					JOIN acc_journals journal ON journal.id = item.journal_id
					WHERE journal.is_posted = 1
						AND journal.date <= ?
						AND item.account_id = ?
						AND NOT EXISTS (
							SELECT 1
							FROM acc_bank_statement_items matched_item
							WHERE matched_item.matched_journal_item_id = item.id
						)
					ORDER BY journal.date DESC, journal.id DESC, item.id DESC
					LIMIT 500
				", array($statement->end_date, (int) $statement->account_id))->result();
			}
		}

		$data['statements'] = $statements;
		$data['statement'] = $statement;
		$data['items'] = $items;
		$data['candidates'] = $candidates;
		$data['unmatched_book_items'] = $unmatched_book_items;
		$data['statement_total'] = $statement_total;
		$data['unmatched_statement_count'] = $unmatched_statement_count;
		$data['book_balance'] = $book_balance;
		$data['unmatched_book_total'] = $unmatched_book_total;
		$data['adjusted_statement_balance'] = $adjusted_statement_balance;
		$data['balance_difference'] = $balance_difference;
		$data['can_manage'] = $this->ion_auth->in_group(array('admin', 'management', 'pengurus'));
		$data['error'] = $this->session->flashdata('bank_reconciliation_error');
		$data['notice'] = $this->session->flashdata('bank_reconciliation_notice');
		$data['judul_besar'] = 'Rekonsiliasi Bank';
		$data['judul_kecil'] = 'Input rekening koran manual dan pencocokan terhadap jurnal kas bank';
		$data['m_accounting'] = TRUE;
		$data['output'] = $this->load->view('bank_reconciliation', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function create_statement()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$start_date = trim((string) $this->input->post('start_date', TRUE));
		$end_date = trim((string) $this->input->post('end_date', TRUE));
		$opening_balance = $this->_money($this->input->post('opening_balance', TRUE));
		$closing_balance = $this->_money($this->input->post('closing_balance', TRUE));
		if (!$this->_is_date($start_date) || !$this->_is_date($end_date) ||
			$start_date > $end_date || $opening_balance === NULL || $closing_balance === NULL) {
			$this->_fail('Rentang tanggal atau saldo awal/akhir rekening koran tidak valid.');
			return;
		}

		$account = $this->db->get_where('acc_accounts', array(
			'code' => '1102',
			'is_active' => 1
		))->row();
		if (!$account) {
			$this->_fail('Akun kas bank 1102 tidak aktif atau tidak ditemukan.');
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$this->db->query('SELECT id FROM acc_accounts WHERE id = ? FOR UPDATE', array((int) $account->id));
		$overlap = $this->db->query("
			SELECT id FROM acc_bank_statements
			WHERE account_id = ? AND start_date <= ? AND end_date >= ?
			LIMIT 1 FOR UPDATE
		", array((int) $account->id, $end_date, $start_date))->row();
		if ($overlap) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Rentang tanggal bertumpang tindih dengan rekening koran yang sudah dicatat.');
			return;
		}

		$statement_number = 'BANK-'.date('Ymd-His').'-'.strtoupper(substr(md5(uniqid('', TRUE)), 0, 8));
		$this->db->insert('acc_bank_statements', array(
			'account_id' => (int) $account->id,
			'statement_number' => $statement_number,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'opening_balance' => $opening_balance,
			'closing_balance' => $closing_balance,
			'status' => 'draft',
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => (string) $this->ion_auth->get_user_id()
		));
		if ($this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal membuat rekening koran '.$statement_number.': '.$error['code'].' '.$error['message']);
			$this->_fail('Rekening koran gagal disimpan.');
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_notice('Rekening koran '.$statement_number.' berhasil dibuat.', (int) $this->db->insert_id());
	}

	public function add_item()
	{
		$statement_id = $this->_post_id('statement_id');
		$statement = $statement_id ? $this->_get_statement($statement_id) : NULL;
		$date = trim((string) $this->input->post('transaction_date', TRUE));
		$description = trim((string) $this->input->post('description', TRUE));
		$reference = trim((string) $this->input->post('external_reference', TRUE));
		$amount = $this->_money($this->input->post('amount', TRUE));
		if (!$statement || $statement->status !== 'draft') {
			$this->_fail('Rekening koran tidak ditemukan atau sudah direkonsiliasi.', $statement_id);
			return;
		}
		if (!$this->_is_date($date) || $date < $statement->start_date || $date > $statement->end_date ||
			$description === '' || strlen($description) > 255 ||
			($reference !== '' && strlen($reference) > 128) ||
			$amount === NULL || abs($amount) < 0.01) {
			$this->_fail('Tanggal, keterangan, referensi, atau nominal mutasi rekening koran tidak valid.', $statement_id);
			return;
		}
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$locked_statement = $this->db->query("
			SELECT status FROM acc_bank_statements WHERE id = ? FOR UPDATE
		", array((int) $statement->id))->row();
		if (!$locked_statement || $locked_statement->status !== 'draft') {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Rekening koran tidak ditemukan atau sudah direkonsiliasi.', $statement_id);
			return;
		}
		$this->db->insert('acc_bank_statement_items', array(
			'statement_id' => (int) $statement->id,
			'transaction_date' => $date,
			'description' => $description,
			'external_reference' => $reference !== '' ? $reference : NULL,
			'amount' => $amount,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => (string) $this->ion_auth->get_user_id()
		));
		$inserted = $this->db->affected_rows() === 1;
		$transaction_ok = $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		if (!$inserted || !$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menambah baris rekening koran '.$statement->statement_number.': '.$error['code'].' '.$error['message']);
			$this->_fail('Mutasi rekening koran gagal disimpan.', $statement_id);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_notice('Mutasi rekening koran berhasil ditambahkan.', $statement_id);
	}

	public function remove_item()
	{
		$statement_item_id = $this->_post_id('item_id');
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$item = $statement_item_id ? $this->db->query("
			SELECT item.id, item.statement_id, item.matched_journal_item_id,
				statement.status
			FROM acc_bank_statement_items item
			JOIN acc_bank_statements statement ON statement.id = item.statement_id
			WHERE item.id = ?
			FOR UPDATE
		", array($statement_item_id))->row() : NULL;
		if (!$item || $item->status !== 'draft' || $item->matched_journal_item_id) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Hanya mutasi rekening koran yang belum dicocokkan pada draft yang dapat dihapus.', $item ? (int) $item->statement_id : 0);
			return;
		}
		$this->db->delete('acc_bank_statement_items', array('id' => (int) $item->id));
		$deleted = $this->db->affected_rows() === 1;
		$transaction_ok = $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		if (!$deleted || !$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menghapus mutasi rekening koran '.$statement_item_id.': '.$error['code'].' '.$error['message']);
			$this->_fail('Mutasi rekening koran gagal dihapus.', (int) $item->statement_id);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_notice('Mutasi rekening koran dihapus.', (int) $item->statement_id);
	}

	public function match_item()
	{
		$statement_item_id = $this->_post_id('statement_item_id');
		$journal_item_id = $this->_post_id('journal_item_id');
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$item = $statement_item_id ? $this->db->query("
			SELECT item.id, item.statement_id, item.amount,
				item.matched_journal_item_id, statement.account_id,
				statement.end_date, statement.status
			FROM acc_bank_statement_items item
			JOIN acc_bank_statements statement ON statement.id = item.statement_id
			WHERE item.id = ?
			FOR UPDATE
		", array($statement_item_id))->row() : NULL;
		if (!$item || $item->status !== 'draft' || $item->matched_journal_item_id || !$journal_item_id) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Baris rekening koran tidak dapat dicocokkan.', $item ? (int) $item->statement_id : 0);
			return;
		}
		$journal_item = $this->db->query("
			SELECT item.id, item.debit, item.credit
			FROM acc_journal_items item
			JOIN acc_journals journal ON journal.id = item.journal_id
			WHERE item.id = ? AND item.account_id = ?
				AND journal.is_posted = 1 AND journal.date <= ?
			FOR UPDATE
		", array($journal_item_id, (int) $item->account_id, $item->end_date))->row();
		if (!$journal_item ||
			abs(round((float) $journal_item->debit - (float) $journal_item->credit - (float) $item->amount, 2)) > 0.009) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Mutasi jurnal harus berasal dari akun bank yang sama dan bernilai sama dengan rekening koran.', (int) $item->statement_id);
			return;
		}

		$this->db->query("
			UPDATE acc_bank_statement_items
			SET matched_journal_item_id = ?, matched_at = ?, matched_by = ?
			WHERE id = ? AND matched_journal_item_id IS NULL
		", array(
			$journal_item_id,
			date('Y-m-d H:i:s'),
			(string) $this->ion_auth->get_user_id(),
			$statement_item_id
		));
		$matched = $this->db->affected_rows() === 1;
		$transaction_ok = $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		if (!$matched || !$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal mencocokkan mutasi bank '.$statement_item_id.': '.$error['code'].' '.$error['message']);
			$this->_fail('Pencocokan gagal; jurnal mungkin sudah dipakai oleh baris rekening koran lain.', (int) $item->statement_id);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_notice('Mutasi rekening koran berhasil dicocokkan.', (int) $item->statement_id);
	}

	public function unmatch_item()
	{
		$statement_item_id = $this->_post_id('statement_item_id');
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$item = $statement_item_id ? $this->db->query("
			SELECT item.id, item.statement_id, statement.status
			FROM acc_bank_statement_items item
			JOIN acc_bank_statements statement ON statement.id = item.statement_id
			WHERE item.id = ?
			FOR UPDATE
		", array($statement_item_id))->row() : NULL;
		if (!$item || $item->status !== 'draft') {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Pencocokan tidak dapat diubah setelah rekening koran direkonsiliasi.', $item ? (int) $item->statement_id : 0);
			return;
		}
		$this->db->where('id', (int) $item->id)
			->where('matched_journal_item_id IS NOT NULL', NULL, FALSE)
			->update('acc_bank_statement_items', array(
			'matched_journal_item_id' => NULL,
			'matched_at' => NULL,
			'matched_by' => NULL
		));
		$updated = $this->db->affected_rows() === 1;
		$transaction_ok = $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		if (!$updated || !$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal melepas pencocokan mutasi bank '.$statement_item_id.': '.$error['code'].' '.$error['message']);
			$this->_fail('Pencocokan mutasi gagal dilepas.', (int) $item->statement_id);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_notice('Pencocokan mutasi dibatalkan.', (int) $item->statement_id);
	}

	public function reconcile()
	{
		$statement_id = $this->_post_id('statement_id');
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$statement = $statement_id ? $this->db->query("
			SELECT statement.*, account.code AS account_code, account.name AS account_name
			FROM acc_bank_statements statement
			JOIN acc_accounts account ON account.id = statement.account_id
			WHERE statement.id = ?
			FOR UPDATE
		", array($statement_id))->row() : NULL;
		if (!$statement || $statement->status !== 'draft') {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Rekening koran tidak ditemukan atau sudah direkonsiliasi.', $statement_id);
			return;
		}
		$totals = $this->db->query("
			SELECT COALESCE(SUM(amount), 0) AS total,
				COUNT(*) AS item_count,
				SUM(CASE WHEN matched_journal_item_id IS NULL THEN 1 ELSE 0 END) AS unmatched_count
			FROM acc_bank_statement_items
			WHERE statement_id = ?
		", array($statement_id))->row();
		$calculated_closing = round((float) $statement->opening_balance + (float) $totals->total, 2);
		if ((int) $totals->item_count === 0 ||
			(int) $totals->unmatched_count > 0 ||
			abs(round($calculated_closing - (float) $statement->closing_balance, 2)) > 0.009) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_fail('Selesaikan semua pencocokan dan pastikan saldo awal ditambah mutasi sama dengan saldo akhir rekening koran.', $statement_id);
			return;
		}

		$this->db->query("
			UPDATE acc_bank_statements SET status = 'reconciled',
				reconciled_at = ?, reconciled_by = ?
			WHERE id = ? AND status = 'draft'
		", array(date('Y-m-d H:i:s'), (string) $this->ion_auth->get_user_id(), $statement_id));
		$updated = $this->db->affected_rows() === 1;
		$transaction_ok = $this->db->trans_status() !== FALSE && $this->db->trans_commit();
		if (!$updated || !$transaction_ok) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menutup rekonsiliasi '.$statement->statement_number.': '.$error['code'].' '.$error['message']);
			$this->_fail('Rekening koran gagal ditandai selesai.', $statement_id);
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_notice('Rekening koran ditandai selesai. Tinjau juga selisih saldo buku dan daftar transaksi buku yang belum muncul di rekening koran.', $statement_id);
	}

	private function _get_statement($id)
	{
		return $this->db->query("
			SELECT statement.*, account.code AS account_code, account.name AS account_name
			FROM acc_bank_statements statement
			JOIN acc_accounts account ON account.id = statement.account_id
			WHERE statement.id = ?
		", array((int) $id))->row();
	}

	private function _post_id($name)
	{
		$value = $this->input->post($name, TRUE);
		return is_string($value) && ctype_digit($value) && (int) $value > 0
			? (int) $value
			: 0;
	}

	private function _money($value)
	{
		if (!is_string($value) || !preg_match('/^-?\d{1,13}(?:\.\d{1,2})?$/', $value)) {
			return NULL;
		}
		return round((float) $value, 2);
	}

	private function _is_date($value)
	{
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return FALSE;
		}
		$parts = explode('-', $value);
		return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
	}

	private function _fail($message, $statement_id = 0)
	{
		$this->session->set_flashdata('bank_reconciliation_error', $message);
		$this->_redirect($statement_id);
	}

	private function _notice($message, $statement_id = 0)
	{
		$this->session->set_flashdata('bank_reconciliation_notice', $message);
		$this->_redirect($statement_id);
	}

	private function _redirect($statement_id)
	{
		$url = 'main/bank_reconciliation';
		if ((int) $statement_id > 0) {
			$url .= '?statement_id='.(int) $statement_id;
		}
		redirect($url);
	}
}
