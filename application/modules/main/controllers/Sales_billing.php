<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sales_billing extends CI_Controller
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
			show_error('Anda tidak memiliki akses ke tagihan penjualan.', 403);
		}
		foreach (array('sales_billing_periods', 'sales_validation_runs', 'sales_validation_items', 'sales_billing_items') as $table) {
			if (!$this->db->table_exists($table)) {
				show_error('Skema tagihan belum tersedia. Jalankan application/sql/sales_billing.sql.', 500);
			}
		}
	}

	public function index()
	{
		$periods = $this->db->order_by('start_date', 'DESC')->order_by('id', 'DESC')
			->limit(100)->get('sales_billing_periods')->result();
		$period_id = $this->_positive_id($this->input->get('period_id', TRUE));
		$selected = $period_id ? $this->db->get_where('sales_billing_periods', array('id' => $period_id))->row() : NULL;
		$runs = array();
		$items = array();
		$validation_items = array();
		if ($selected) {
			$runs = $this->db->where('period_id', (int) $selected->id)
				->order_by('id', 'DESC')->limit(20)->get('sales_validation_runs')->result();
			if (!empty($runs)) {
				$validation_items = $this->db->where('run_id', (int) $runs[0]->id)
					->order_by('status', 'ASC')->order_by('nota', 'ASC')
					->limit(500)->get('sales_validation_items')->result();
			}
			if ($selected->status === 'finalized') {
				$items = $this->db->query("
					SELECT billing_item.*, employee.nama AS current_employee_name
					FROM sales_billing_items billing_item
					LEFT JOIN karyawan employee ON employee.npk = billing_item.npk
					WHERE billing_item.period_id = ?
					ORDER BY billing_item.npk ASC, billing_item.transaction_date ASC, billing_item.nota ASC
				", array((int) $selected->id))->result();
			}
		}
		$data = array(
			'periods' => $periods,
			'selected' => $selected,
			'runs' => $runs,
			'validation_items' => $validation_items,
			'items' => $items,
			'is_manager' => $this->_is_manager(),
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('sales_billing_notice'),
			'error' => $this->session->flashdata('sales_billing_error'),
			'judul_besar' => 'Tagihan & Validasi Penjualan',
			'judul_kecil' => 'Periode custom · validasi nota · snapshot tagihan',
			'm_sales_billing' => TRUE
		);
		$data['output'] = $this->load->view('sales_billing', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function create_period()
	{
		$this->_require_manager_post();
		$name = trim((string) $this->input->post('period_name', TRUE));
		$start = trim((string) $this->input->post('start_date', TRUE));
		$end = trim((string) $this->input->post('end_date', TRUE));
		if ($name === '' || strlen($name) > 64 || !$this->_is_date($start) || !$this->_is_date($end) ||
			$start > $end) {
			$this->_flash('Nama periode dan rentang tanggal harus valid.', FALSE);
		}
		$lock = $this->db->query("SELECT GET_LOCK('simpleton_sales_billing_periods', 5) AS acquired")->row();
		if (!$lock || (int) $lock->acquired !== 1) {
			$this->_flash('Tidak dapat mengunci pembuatan periode. Coba lagi.', FALSE);
		}
		if (!$this->db->trans_begin()) {
			$this->db->query("SELECT RELEASE_LOCK('simpleton_sales_billing_periods')");
			$this->_flash('Database tidak dapat memulai pembuatan periode.', FALSE);
		}
		$overlap = $this->db->query("
			SELECT id, period_name, start_date, end_date FROM sales_billing_periods
			WHERE start_date <= ? AND end_date >= ?
			LIMIT 1
		", array($end, $start))->row();
		if ($overlap && $this->_ranges_overlap($start, $end, $overlap->start_date, $overlap->end_date)) {
			$this->db->trans_rollback();
			$this->db->query("SELECT RELEASE_LOCK('simpleton_sales_billing_periods')");
			$this->_flash('Rentang bertumpang tindih dengan periode '.$overlap->period_name.'. Pilih tanggal yang tidak overlap.', FALSE);
		}
		$saved = $this->db->insert('sales_billing_periods', array(
			'period_name' => $name,
			'start_date' => $start,
			'end_date' => $end,
			'status' => 'open',
			'created_by' => (string) $this->ion_auth->get_user_id(),
			'created_at' => date('Y-m-d H:i:s')
		));
		if (!$saved || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->query("SELECT RELEASE_LOCK('simpleton_sales_billing_periods')");
			log_message('error', 'Gagal membuat periode tagihan '.$name.': '.$error['code'].' '.$error['message']);
			$this->_flash('Periode tagihan gagal disimpan.', FALSE);
		}
		$this->db->query("SELECT RELEASE_LOCK('simpleton_sales_billing_periods')");
		$this->_flash('Periode '.$name.' berhasil dibuat.', TRUE);
	}

	public function validate_sales($period_id = NULL)
	{
		$this->_require_manager_post();
		if (!$this->_positive_id($period_id)) {
			show_404();
		}
		$period = $this->db->get_where('sales_billing_periods', array('id' => (int) $period_id))->row();
		if (!$period || $period->status !== 'open') {
			$this->_flash('Periode tidak ditemukan atau sudah difinalisasi.', FALSE);
		}
		$mode = trim((string) $this->input->post('validation_mode', TRUE));
		if (!in_array($mode, array('period', 'week', 'nota'), TRUE)) {
			$this->_flash('Pilih validasi periode penuh, mingguan, atau per nota.', FALSE, (int) $period_id);
		}
		$start = $period->start_date;
		$end = $period->end_date;
		$nota_filter = NULL;
		if ($mode === 'week') {
			$start = trim((string) $this->input->post('start_date', TRUE));
			$end = trim((string) $this->input->post('end_date', TRUE));
			if (!$this->_is_date($start) || !$this->_is_date($end) || $start > $end ||
				$start < $period->start_date || $end > $period->end_date ||
				(strtotime($end) - strtotime($start)) > 6 * 86400) {
				$this->_flash('Rentang validasi mingguan harus maksimal 7 hari dan berada di dalam periode.', FALSE, (int) $period_id);
			}
		}
		if ($mode === 'nota') {
			$nota_filter = trim((string) $this->input->post('nota', TRUE));
			if ($nota_filter === '' || strlen($nota_filter) > 64) {
				$this->_flash('Masukkan nomor nota yang valid.', FALSE, (int) $period_id);
			}
		}

		if (!$this->db->trans_begin()) {
			$this->_flash('Database tidak dapat memulai validasi penjualan.', FALSE, (int) $period_id);
		}
		$locked_period = $this->db->query('SELECT * FROM sales_billing_periods WHERE id = ? FOR UPDATE', array((int) $period_id))->row();
		if (!$locked_period || $locked_period->status !== 'open') {
			$this->db->trans_rollback();
			$this->_flash('Periode sudah difinalisasi atau tidak tersedia.', FALSE, (int) $period_id);
		}
		$summary = $this->_record_validation((int) $period_id, $mode, $start, $end, $nota_filter);
		if ($this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->_flash('Hasil validasi gagal disimpan.', FALSE, (int) $period_id);
		}
		$this->_flash('Validasi selesai: '.$summary['checked'].' nota, '.$summary['invalid'].' bermasalah.',
			$summary['invalid'] === 0, (int) $period_id);
	}

	public function finalize_period($period_id = NULL)
	{
		$this->_require_manager_post();
		if (!$this->_positive_id($period_id)) {
			show_404();
		}
		if (!$this->db->trans_begin()) {
			$this->_flash('Database tidak dapat memulai finalisasi tagihan.', FALSE, (int) $period_id);
		}
		$period = $this->db->query('SELECT * FROM sales_billing_periods WHERE id = ? FOR UPDATE', array((int) $period_id))->row();
		if (!$period || $period->status !== 'open') {
			$this->db->trans_rollback();
			$this->_flash('Periode tidak ditemukan atau sudah difinalisasi.', FALSE, (int) $period_id);
		}
		$summary = $this->_record_validation((int) $period_id, 'period', $period->start_date, $period->end_date, NULL);
		if ($summary['invalid'] > 0) {
			$this->db->trans_commit();
			$this->_flash('Periode belum dapat digenerate: '.$summary['invalid'].' nota bermasalah. Lihat hasil validasi.', FALSE, (int) $period_id);
		}
		if (!$this->_save_billing_snapshot((int) $period_id, $period->start_date, $period->end_date, (int) $summary['run_id'])) {
			$this->db->trans_rollback();
			$this->_flash('Snapshot tagihan gagal dibuat. Periode tetap terbuka.', FALSE, (int) $period_id);
		}
		$updated = $this->db->where('id', (int) $period_id)->where('status', 'open')->update('sales_billing_periods', array(
			'status' => 'finalized',
			'finalized_by' => (string) $this->ion_auth->get_user_id(),
			'finalized_at' => date('Y-m-d H:i:s')
		));
		if (!$updated || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->_flash('Periode gagal difinalisasi; tidak ada snapshot yang disimpan.', FALSE, (int) $period_id);
		}
		$this->_flash('Tagihan '.$period->period_name.' berhasil digenerate dan difinalisasi.', TRUE, (int) $period_id);
	}

	private function _record_validation($period_id, $mode, $start, $end, $nota_filter)
	{
		$notes = $this->_period_notes($start, $end, $nota_filter);
		$results = array();
		$invalid = 0;
		if ($mode === 'nota' && empty($notes)) {
			$results[] = array(
				'nota' => $nota_filter,
				'npk' => '',
				'transaction_date' => $start,
				'valid' => FALSE,
				'issues' => array('Nota tidak ditemukan dalam periode tagihan ini.'),
				'total_sales' => 0,
				'approved_returns' => 0,
				'billable_total' => 0
			);
			$invalid++;
		}
		foreach ($notes as $note) {
			$result = $this->_validate_nota($note->nota, $start, $end);
			$results[] = $result;
			if (!$result['valid']) {
				$invalid++;
			}
		}
		if ($mode === 'period') {
			foreach ($this->_late_return_credits($start, $end) as $credit) {
				$issues = array();
				if ((float) $credit->amount > (float) $credit->original_total + 0.01) {
					$issues[] = 'Retur approved melebihi nilai penjualan nota asal.';
				}
				$result = array(
					'nota' => $credit->nota,
					'npk' => (string) $credit->npk,
					'transaction_date' => substr($credit->approved_at, 0, 10),
					'valid' => empty($issues),
					'issues' => $issues,
					'total_sales' => 0,
					'approved_returns' => (float) $credit->amount,
					'billable_total' => -1 * (float) $credit->amount
				);
				$results[] = $result;
				if (!$result['valid']) {
					$invalid++;
				}
			}
		}
		$run_result = $invalid === 0 ? 'passed' : 'failed';
		$this->db->insert('sales_validation_runs', array(
			'period_id' => (int) $period_id,
			'validation_mode' => $mode,
			'start_date' => $start,
			'end_date' => $end,
			'nota_filter' => $nota_filter,
			'result' => $run_result,
			'checked_count' => count($results),
			'created_by' => (string) $this->ion_auth->get_user_id(),
			'created_at' => date('Y-m-d H:i:s')
		));
		$run_id = (int) $this->db->insert_id();
		foreach ($results as $result) {
			$this->db->insert('sales_validation_items', array(
				'run_id' => $run_id,
				'nota' => $result['nota'],
				'npk' => $result['npk'],
				'transaction_date' => $result['transaction_date'],
				'status' => $result['valid'] ? 'valid' : 'invalid',
				'issues' => empty($result['issues']) ? NULL : implode(' ', $result['issues']),
				'total_sales' => $result['total_sales'],
				'approved_returns' => $result['approved_returns'],
				'billable_total' => $result['billable_total']
			));
		}
		return array('run_id' => $run_id, 'checked' => count($results), 'invalid' => $invalid);
	}

	private function _period_notes($start, $end, $nota_filter)
	{
		$end_exclusive = date('Y-m-d', strtotime($end.' +1 day'));
		$this->db->distinct()->select('nota')->from('penjualan')
			->where('tgl >=', $start.' 00:00:00')->where('tgl <', $end_exclusive.' 00:00:00');
		if ($nota_filter !== NULL) {
			$this->db->where('nota', $nota_filter);
		}
		return $this->db->order_by('nota', 'ASC')->get()->result();
	}

	private function _late_return_credits($start, $end)
	{
		return $this->db->query("
			SELECT return_header.nota_penjualan AS nota, return_header.id_karyawan AS npk,
				MAX(COALESCE(return_header.approved_at, return_header.tgl_retur)) AS approved_at,
				SUM(return_item.harga_satuan * return_item.qty_retur) AS amount,
				MAX(original.total_sales) AS original_total
			FROM retur_penjualan return_header
			JOIN retur_penjualan_items return_item ON return_item.retur_id = return_header.id
			JOIN (
				SELECT nota, id_karyawan, SUM(jumlah) AS total_sales, MAX(tgl) AS last_sale
				FROM penjualan GROUP BY nota, id_karyawan
			) original ON original.nota = return_header.nota_penjualan
				AND original.id_karyawan = return_header.id_karyawan
			WHERE return_header.status IN ('approved', 'completed')
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) >= ?
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) < ?
				AND original.last_sale < ?
			GROUP BY return_header.nota_penjualan, return_header.id_karyawan
		", array(
			$start.' 00:00:00',
			date('Y-m-d', strtotime($end.' +1 day')).' 00:00:00',
			$start.' 00:00:00'
		))->result();
	}

	private function _validate_nota($nota, $start, $end)
	{
		$row = $this->db->query("
			SELECT sale.nota, MIN(sale.tgl) AS transaction_date,
				MIN(sale.id_karyawan) AS npk, COUNT(*) AS line_count,
				COUNT(DISTINCT sale.id_karyawan) AS npk_count,
				SUM(sale.jumlah) AS total_sales,
				SUM(sale.jumlah * COALESCE(sale.ppn, 0)) AS tax_total,
				SUM(CASE WHEN sale.qty <= 0 OR sale.harga <= 0 OR sale.jumlah <> sale.harga * sale.qty THEN 1 ELSE 0 END) AS invalid_lines,
				payment.nota AS payment_nota, payment.total_tagihan, payment.diskon_voucher,
				payment.metode_pembayaran
			FROM penjualan sale
			LEFT JOIN penjualan_pembayaran payment ON payment.nota = sale.nota
			WHERE sale.nota = ?
			GROUP BY sale.nota, payment.nota, payment.total_tagihan,
				payment.diskon_voucher, payment.metode_pembayaran
		", array($nota))->row();
		$issues = array();
		if (!$row) {
			return array('nota' => $nota, 'npk' => '', 'transaction_date' => date('Y-m-d'),
				'valid' => FALSE, 'issues' => array('Nota tidak ditemukan.'), 'total_sales' => 0,
				'approved_returns' => 0, 'billable_total' => 0);
		}
		if ((int) $row->line_count === 0 || (int) $row->invalid_lines > 0) {
			$issues[] = 'Ada detail barang dengan qty/harga/jumlah tidak valid.';
		}
		if ((int) $row->npk_count !== 1 || trim((string) $row->npk) === '') {
			$issues[] = 'NPK nota kosong atau tidak konsisten.';
		}
		if ($row->payment_nota === NULL) {
			$issues[] = 'Header pembayaran nota tidak ditemukan.';
		} else {
			$gross = (float) $row->total_sales + (float) $row->tax_total;
			$expected = $gross - (float) $row->diskon_voucher;
			if ((float) $row->diskon_voucher < 0 || $expected < 0 ||
				abs($expected - (float) $row->total_tagihan) > 0.01) {
				$issues[] = 'Total header pembayaran tidak cocok dengan detail, PPN, dan voucher.';
			}
			if (trim((string) $row->metode_pembayaran) === '') {
				$issues[] = 'Metode pembayaran belum tercatat.';
			}
		}
		$journal = $this->db->get_where('acc_journals', array(
			'reference_type' => 'POS',
			'reference_id' => $nota,
			'is_posted' => 1
		))->row();
		if (!$journal) {
			$issues[] = 'Jurnal POS untuk nota ini belum terposting.';
		}
		$return = $this->db->query("
			SELECT COALESCE(SUM(return_item.harga_satuan * return_item.qty_retur), 0) AS amount
			FROM retur_penjualan_items return_item
			JOIN retur_penjualan return_header ON return_header.id = return_item.retur_id
			WHERE return_header.nota_penjualan = ?
				AND return_header.status IN ('approved', 'completed')
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) >= ?
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) < ?
		", array($nota, $start.' 00:00:00', date('Y-m-d', strtotime($end.' +1 day')).' 00:00:00'))->row();
		$approved_returns = $return ? (float) $return->amount : 0;
		$billable_total = $this->_billable_total((float) $row->total_sales, $approved_returns);
		return array(
			'nota' => $row->nota,
			'npk' => (string) $row->npk,
			'transaction_date' => substr($row->transaction_date, 0, 10),
			'valid' => empty($issues),
			'issues' => $issues,
			'total_sales' => (float) $row->total_sales,
			'approved_returns' => $approved_returns,
			'billable_total' => $billable_total
		);
	}

	private function _save_billing_snapshot($period_id, $start, $end, $run_id)
	{
		$validation_items = $this->db->where('run_id', (int) $run_id)
			->where('status', 'valid')->get('sales_validation_items')->result();
		$rows = array();
		foreach ($validation_items as $item) {
			$rows[$item->nota] = array(
				'nota' => $item->nota,
				'npk' => $item->npk,
				'date' => $item->transaction_date.' 00:00:00',
				'total_sales' => (float) $item->total_sales,
				'approved_returns' => (float) $item->approved_returns
			);
		}
		$returns = $this->db->query("
			SELECT return_header.nota_penjualan AS nota, sale.id_karyawan AS npk,
				MAX(COALESCE(return_header.approved_at, return_header.tgl_retur)) AS approved_at,
				SUM(return_item.harga_satuan * return_item.qty_retur) AS amount
			FROM retur_penjualan return_header
			JOIN retur_penjualan_items return_item ON return_item.retur_id = return_header.id
			JOIN penjualan sale ON sale.id = return_item.penjualan_id
			WHERE return_header.status IN ('approved', 'completed')
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) >= ?
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) < ?
			GROUP BY return_header.nota_penjualan, sale.id_karyawan
		", array($start.' 00:00:00', date('Y-m-d', strtotime($end.' +1 day')).' 00:00:00'))->result();
		foreach ($returns as $return) {
			if (!isset($rows[$return->nota])) {
				$rows[$return->nota] = array(
					'nota' => $return->nota,
					'npk' => $return->npk,
					'date' => $return->approved_at,
					'total_sales' => 0,
					'approved_returns' => 0
				);
			}
			if ((float) $rows[$return->nota]['approved_returns'] === 0.0) {
				$rows[$return->nota]['approved_returns'] = (float) $return->amount;
			}
		}
		foreach ($rows as $row) {
			$employee = $this->db->get_where('karyawan', array('npk' => $row['npk']))->row();
			if (!$this->db->insert('sales_billing_items', array(
				'period_id' => (int) $period_id,
				'nota' => $row['nota'],
				'npk' => $row['npk'],
				'employee_name' => $employee ? $employee->nama : NULL,
				'transaction_date' => $row['date'],
				'total_sales' => $row['total_sales'],
				'approved_returns' => $row['approved_returns'],
				'billable_total' => $this->_billable_total($row['total_sales'], $row['approved_returns'])
			))) {
				return FALSE;
			}
		}
		return TRUE;
	}

	private function _positive_id($value)
	{
		return (is_string($value) || is_numeric($value)) && preg_match('/^[1-9][0-9]*$/', (string) $value)
			? (int) $value : FALSE;
	}

	private function _ranges_overlap($start_a, $end_a, $start_b, $end_b)
	{
		return $start_a <= $end_b && $end_a >= $start_b;
	}

	private function _billable_total($total_sales, $approved_returns)
	{
		return round((float) $total_sales - (float) $approved_returns, 2);
	}

	private function _is_date($value)
	{
		$date = DateTime::createFromFormat('!Y-m-d', (string) $value);
		$errors = DateTime::getLastErrors();
		return $date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) &&
			$date->format('Y-m-d') === $value;
	}

	private function _is_manager()
	{
		return $this->ion_auth->in_group(array('admin', 'management', 'pengurus'));
	}

	private function _form_token()
	{
		$token = $this->session->userdata('sales_billing_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('sales_billing_form_token', $token);
		}
		return $token;
	}

	private function _require_manager_post()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		if (!$this->_is_manager()) {
			show_error('Hanya admin, management, atau pengurus yang dapat mengelola tagihan.', 403);
		}
		$expected = $this->session->userdata('sales_billing_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir tagihan tidak valid. Muat ulang halaman.', 403);
		}
	}

	private function _flash($message, $success, $period_id = NULL)
	{
		$this->session->set_flashdata($success ? 'sales_billing_notice' : 'sales_billing_error', $message);
		redirect('main/sales_billing'.($period_id ? '?period_id='.(int) $period_id : ''));
	}
}