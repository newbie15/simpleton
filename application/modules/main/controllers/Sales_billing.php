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
		foreach (array('sales_billing_periods', 'sales_validation_runs', 'sales_validation_items', 'sales_billing_items', 'sales_billing_scans', 'sales_billing_adjustments') as $table) {
			if (!$this->db->table_exists($table)) {
				show_error('Skema tagihan belum tersedia. Jalankan application/sql/sales_billing.sql dan sales_billing_v2.sql.', 500);
			}
		}
		if (!$this->db->field_exists('is_active', 'karyawan') ||
			!$this->db->field_exists('resign_date', 'karyawan')) {
			show_error('Skema status karyawan belum tersedia. Jalankan application/sql/karyawan_employment_status.sql.', 500);
		}
	}

	public function index()
	{
		$legacy_id = $this->_positive_id($this->input->get('period_id', TRUE));
		if ($legacy_id) {
			redirect('main/sales_billing/detail/'.$legacy_id);
		}
		$periods = $this->db->query("
			SELECT period.*, ".$this->_user_name_sql('creator', 'period.created_by')." AS created_name
			FROM sales_billing_periods period
			LEFT JOIN users creator ON creator.id = period.created_by
			ORDER BY period.start_date DESC, period.id DESC
			LIMIT 100
		")->result();
		$this->_render('sales_billing_list', array(
			'periods' => $periods,
			'is_manager' => $this->_is_manager(),
			'form_token' => $this->_form_token(),
			'judul_kecil' => 'Daftar periode tagihan'
		));
	}

	public function detail($period_id = NULL)
	{
		$period = $this->_load_period($period_id);
		$runs = $this->db->query("
			SELECT run.*, ".$this->_user_name_sql('operator', 'run.created_by')." AS operator_name
			FROM sales_validation_runs run
			LEFT JOIN users operator ON operator.id = run.created_by
			WHERE run.period_id = ?
			ORDER BY run.id DESC LIMIT 5
		", array((int) $period->id))->result();
		$reference = $this->_reference_run((int) $period->id);
		$items = array();
		$employee_summaries = array();
		if ($period->status === 'finalized') {
			$items = $this->db->query("
				SELECT billing_item.*, employee.nama AS current_employee_name
				FROM sales_billing_items billing_item
				LEFT JOIN karyawan employee ON employee.npk = billing_item.npk
				WHERE billing_item.period_id = ?
				ORDER BY billing_item.npk ASC, billing_item.transaction_date ASC, billing_item.nota ASC
			", array((int) $period->id))->result();
			$employee_summaries = $this->db->query("
				SELECT billing_item.npk,
					MAX(COALESCE(employee.nama, billing_item.employee_name)) AS employee_name,
					SUM(billing_item.billable_total) AS total_potong_gaji,
					MAX(employee.limit_belanja) AS limit_belanja,
					MAX(employee.is_active) AS is_active,
					MAX(employee.resign_date) AS resign_date
				FROM sales_billing_items billing_item
				LEFT JOIN (
					SELECT npk, MAX(nama) AS nama, MAX(limit_belanja) AS limit_belanja,
						MIN(COALESCE(is_active, 1)) AS is_active, MAX(resign_date) AS resign_date
					FROM karyawan GROUP BY npk
				) employee ON employee.npk = billing_item.npk
				WHERE billing_item.period_id = ? AND billing_item.payment_method = 'potong_gaji'
				GROUP BY billing_item.npk
				ORDER BY employee_name ASC, billing_item.npk ASC
			", array((int) $period->id))->result();
			foreach ($employee_summaries as $employee_summary) {
				$employee_summary->over_limit = $employee_summary->limit_belanja !== NULL &&
					(float) $employee_summary->total_potong_gaji > (float) $employee_summary->limit_belanja;
				$employee_summary->resign_status = $this->_billing_employee_status(
					$employee_summary->is_active,
					$employee_summary->resign_date,
					$period->end_date
				);
			}
		}
		$methods = array();
		if ($period->status === 'finalized') {
			$methods = $this->db->query("
				SELECT payment_method, COUNT(*) AS nota_count, SUM(billable_total) AS billable
				FROM sales_billing_items WHERE period_id = ?
				GROUP BY payment_method ORDER BY payment_method = 'potong_gaji' DESC, payment_method ASC
			", array((int) $period->id))->result();
			foreach ($methods as $method) {
				$method->slug = $method->payment_method === '' ? 'tidak_tercatat' : $method->payment_method;
				$method->label = $this->_method_label($method->slug);
			}
		}
		$this->_render('sales_billing_detail', array(
			'methods' => $methods,
			'period' => $period,
			'runs' => $runs,
			'reference' => $reference,
			'stats' => $reference ? $this->_scan_stats((int) $period->id, (int) $reference->id) : NULL,
			'items' => $items,
			'employee_summaries' => $employee_summaries,
			'is_manager' => $this->_is_manager(),
			'form_token' => $this->_form_token(),
			'judul_kecil' => 'Detail periode tagihan'
		));
	}

	private function _billing_employee_status($is_active, $resign_date, $period_end)
	{
		if ($is_active === NULL) {
			return 'Status karyawan tidak ditemukan';
		}
		if (is_string($resign_date) && $resign_date !== '' && $resign_date !== '0000-00-00') {
			return $resign_date <= $period_end ? 'Resign pada/ sebelum akhir periode' : 'Aktif selama periode';
		}
		return (int) $is_active === 0 ? 'Resign (tanggal belum tercatat)' : 'Aktif';
	}

	public function validation($period_id = NULL)
	{
		$period = $this->_load_period($period_id);
		$runs = $this->db->query("
			SELECT run.*, ".$this->_user_name_sql('operator', 'run.created_by')." AS operator_name
			FROM sales_validation_runs run
			LEFT JOIN users operator ON operator.id = run.created_by
			WHERE run.period_id = ?
			ORDER BY run.id DESC LIMIT 20
		", array((int) $period->id))->result();
		$run_id = $this->_positive_id($this->input->get('run_id', TRUE));
		$run = NULL;
		foreach ($runs as $candidate) {
			if ($run_id ? (int) $candidate->id === $run_id : $run === NULL) {
				$run = $candidate;
			}
		}
		$only_issues = $this->input->get('all', TRUE) !== '1';
		$items = array();
		if ($run) {
			$this->db->where('run_id', (int) $run->id);
			if ($only_issues) {
				$this->db->where('status', 'invalid');
			}
			$items = $this->db->order_by('status', 'ASC')->order_by('nota', 'ASC')
				->limit(2000)->get('sales_validation_items')->result();
		}
		$this->_render('sales_billing_validation', array(
			'period' => $period,
			'runs' => $runs,
			'run' => $run,
			'items' => $items,
			'only_issues' => $only_issues,
			'judul_kecil' => 'Hasil validasi nota'
		));
	}

	public function scan($period_id = NULL)
	{
		$period = $this->_load_period($period_id);
		$reference = $this->_reference_run((int) $period->id);
		$pending = $scanned = $problems = array();
		$stats = NULL;
		if ($reference) {
			$stats = $this->_scan_stats((int) $period->id, (int) $reference->id);
			$base = "
				FROM sales_validation_items vi
				LEFT JOIN sales_billing_scans scan ON scan.period_id = ? AND scan.nota = vi.nota
				".$this->_adjustment_join('vi', '?')."
				LEFT JOIN karyawan member ON member.npk = vi.npk
				LEFT JOIN users operator ON operator.id = scan.scanned_by
				WHERE vi.run_id = ? AND ".$this->_expected_sql('vi');
			$args = array((int) $period->id, (int) $period->id, (int) $reference->id);
			$columns = "SELECT vi.nota, vi.npk, vi.transaction_date, vi.billable_total, vi.status AS validation_status,
				vi.issues, member.nama AS member_name, scan.scan_time, scan.nota_total, scan.status AS scan_status,
				".$this->_user_name_sql('operator', 'scan.scanned_by')." AS operator_name ";
			$pending = $this->db->query($columns.$base." AND adj.id IS NULL AND scan.id IS NULL ORDER BY vi.nota ASC LIMIT 1000", $args)->result();
			$scanned = $this->db->query($columns.$base." AND adj.id IS NULL AND ".$this->_match_sql('scan', 'vi')." ORDER BY scan.scan_time DESC LIMIT 200", $args)->result();
			$problems = $this->db->query($columns.$base." AND adj.id IS NULL AND scan.id IS NOT NULL AND NOT (".$this->_match_sql('scan', 'vi').") ORDER BY scan.scan_time DESC LIMIT 500", $args)->result();
		}
		$this->_render('sales_billing_scan', array(
			'period' => $period,
			'reference' => $reference,
			'stats' => $stats,
			'pending' => $pending,
			'scanned' => $scanned,
			'problems' => $problems,
			'form_token' => $this->_form_token(),
			'judul_kecil' => 'Cek nota dengan scan barcode'
		));
	}

	private function _render($view, $data)
	{
		$data['notice'] = $this->session->flashdata('sales_billing_notice');
		$data['error'] = $this->session->flashdata('sales_billing_error');
		$data['judul_besar'] = 'Tagihan & Validasi Penjualan';
		$data['m_sales_billing'] = TRUE;
		$data['output'] = $this->load->view($view, $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	private function _load_period($period_id)
	{
		if (!$this->_positive_id($period_id)) {
			show_404();
		}
		$period = $this->db->query("
			SELECT period.*, ".$this->_user_name_sql('creator', 'period.created_by')." AS created_name,
				".$this->_user_name_sql('finalizer', 'period.finalized_by')." AS finalized_name
			FROM sales_billing_periods period
			LEFT JOIN users creator ON creator.id = period.created_by
			LEFT JOIN users finalizer ON finalizer.id = period.finalized_by
			WHERE period.id = ?
		", array((int) $period_id))->row();
		if (!$period) {
			show_404();
		}
		return $period;
	}

	private function _user_name_sql($alias, $fallback)
	{
		return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', ".$alias.".first_name, ".$alias.".last_name)), ''), ".$alias.".username, ".$fallback.")";
	}

	private function _reference_run($period_id)
	{
		return $this->db->query("
			SELECT run.*, ".$this->_user_name_sql('operator', 'run.created_by')." AS operator_name
			FROM sales_validation_runs run
			LEFT JOIN users operator ON operator.id = run.created_by
			WHERE run.period_id = ? AND run.validation_mode IN ('period', 'cutoff')
			ORDER BY run.id DESC LIMIT 1
		", array((int) $period_id))->row();
	}

	// Retur dari nota periode lain bukan nota fisik, jadi tidak perlu di-scan.
	private function _expected_sql($alias)
	{
		return "NOT (".$alias.".total_sales = 0 AND ".$alias.".approved_returns > 0)";
	}

	private function _match_sql($scan, $item)
	{
		return $scan.".status = 'match' AND ABS(".$scan.".system_total - ".$item.".billable_total) < 0.01";
	}

	private function _scan_stats($period_id, $run_id)
	{
		$row = $this->db->query("
			SELECT COUNT(*) AS total,
				COALESCE(SUM(adj.id IS NOT NULL), 0) AS adjusted,
				COALESCE(SUM(adj.id IS NULL AND scan.id IS NULL), 0) AS pending,
				COALESCE(SUM(adj.id IS NULL AND scan.id IS NOT NULL AND ".$this->_match_sql('scan', 'vi')."), 0) AS matched,
				COALESCE(SUM(adj.id IS NULL AND scan.id IS NOT NULL AND NOT (".$this->_match_sql('scan', 'vi').")), 0) AS problems
			FROM sales_validation_items vi
			LEFT JOIN sales_billing_scans scan ON scan.period_id = ? AND scan.nota = vi.nota
			".$this->_adjustment_join('vi', '?')."
			WHERE vi.run_id = ? AND ".$this->_expected_sql('vi'), array($period_id, $period_id, $run_id))->row();
		return array(
			'total' => (int) $row->total,
			'adjusted' => (int) $row->adjusted,
			'pending' => (int) $row->pending,
			'matched' => (int) $row->matched,
			'problems' => (int) $row->problems
		);
	}

	// Revisi disetujui hanya berlaku selama total sistem tidak berubah sejak disetujui.
	private function _adjustment_join($item, $period_placeholder)
	{
		return "LEFT JOIN sales_billing_adjustments adj ON adj.period_id = ".$period_placeholder."
			AND adj.nota = ".$item.".nota AND adj.status = 'approved'
			AND ABS(adj.system_total - ".$item.".billable_total) < 0.01";
	}

	private function _unresolved_invalid($period_id, $run_id)
	{
		$row = $this->db->query("
			SELECT COUNT(*) AS total FROM sales_validation_items vi
			".$this->_adjustment_join('vi', '?')."
			WHERE vi.run_id = ? AND vi.status = 'invalid' AND adj.id IS NULL
		", array((int) $period_id, (int) $run_id))->row();
		return (int) $row->total;
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
		$new_id = (int) $this->db->insert_id();
		if (!$saved || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->query("SELECT RELEASE_LOCK('simpleton_sales_billing_periods')");
			log_message('error', 'Gagal membuat periode tagihan '.$name.': '.$error['code'].' '.$error['message']);
			$this->_flash('Periode tagihan gagal disimpan.', FALSE);
		}
		$this->db->query("SELECT RELEASE_LOCK('simpleton_sales_billing_periods')");
		$this->_flash('Periode '.$name.' berhasil dibuat.', TRUE, $new_id);
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
		if (!in_array($mode, array('period', 'week', 'nota', 'cutoff'), TRUE)) {
			$this->_flash('Pilih validasi periode penuh, sampai tanggal tertentu, mingguan, atau per nota.', FALSE, (int) $period_id);
		}
		$start = $period->start_date;
		$end = $period->end_date;
		$nota_filter = NULL;
		if ($mode === 'cutoff') {
			$end = trim((string) $this->input->post('end_date', TRUE));
			if (!$this->_is_date($end) || $end < $period->start_date || $end > $period->end_date) {
				$this->_flash('Tanggal batas validasi harus berada di dalam periode.', FALSE, (int) $period_id);
			}
		}
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

		if ($this->input->post('confirm_repeat') !== '1') {
			$previous = $this->db->query("
				SELECT created_at FROM sales_validation_runs
				WHERE period_id = ? AND validation_mode = ? AND start_date = ? AND end_date = ?
					AND (nota_filter <=> ?) ORDER BY id DESC LIMIT 1
			", array((int) $period_id, $mode, $start, $end, $nota_filter))->row();
			if ($previous) {
				$this->_flash('Validasi dengan pengaturan yang sama sudah dilakukan pada '.$previous->created_at.'. Ulangi hanya jika data nota sudah diperbaiki; konfirmasi di dialog saat menekan tombol validasi.', FALSE, (int) $period_id);
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
		$unresolved = $this->_unresolved_invalid((int) $period_id, (int) $summary['run_id']);
		if ($unresolved > 0) {
			$this->db->trans_commit();
			$this->_flash('Periode belum dapat digenerate: '.$unresolved.' nota bermasalah belum direvisi dan disetujui. Lihat halaman revisi.', FALSE, (int) $period_id);
		}
		$scan = $this->_scan_stats((int) $period_id, (int) $summary['run_id']);
		if ($scan['pending'] > 0 || $scan['problems'] > 0) {
			$this->db->trans_commit();
			$this->_flash('Periode belum dapat digenerate: '.$scan['pending'].' nota belum di-scan dan '.$scan['problems'].' nota beda nominal belum direvisi/disetujui. Selesaikan di halaman scan atau revisi.', FALSE, (int) $period_id);
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
					'billable_total' => -1 * (float) $credit->amount,
					'payment_method' => $this->_nota_payment_method($credit->nota)
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
				'billable_total' => $result['billable_total'],
				'payment_method' => isset($result['payment_method']) ? $result['payment_method'] : ''
			));
		}
		return array('run_id' => $run_id, 'checked' => count($results), 'invalid' => $invalid);
	}

	private function _nota_payment_method($nota)
	{
		$row = $this->db->select('metode_pembayaran')->get_where('penjualan_pembayaran', array('nota' => $nota))->row();
		return $row ? trim((string) $row->metode_pembayaran) : '';
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
				SUM(return_item.subtotal) AS amount,
				MAX(original.total_sales) AS original_total
			FROM retur_penjualan return_header
			JOIN retur_penjualan_items return_item ON return_item.retur_id = return_header.id
			JOIN (
				SELECT nota, id_karyawan,
					SUM(CASE WHEN dpp_satuan IS NOT NULL THEN jumlah
						ELSE jumlah + jumlah * COALESCE(ppn, 0) END) AS total_sales,
					MAX(tgl) AS last_sale
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
				SUM(CASE WHEN sale.dpp_satuan IS NOT NULL
					THEN sale.dpp_satuan * sale.qty ELSE sale.jumlah END) AS dpp_total,
				SUM(CASE WHEN sale.ppn_satuan IS NOT NULL
					THEN sale.ppn_satuan * sale.qty ELSE sale.jumlah * COALESCE(sale.ppn, 0) END) AS tax_total,
				SUM(CASE WHEN sale.dpp_satuan IS NOT NULL
					THEN sale.jumlah ELSE sale.jumlah + sale.jumlah * COALESCE(sale.ppn, 0) END) AS gross_total,
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
			$gross = (float) $row->dpp_total + (float) $row->tax_total;
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
			SELECT COALESCE(SUM(return_item.subtotal), 0) AS amount
			FROM retur_penjualan_items return_item
			JOIN retur_penjualan return_header ON return_header.id = return_item.retur_id
			WHERE return_header.nota_penjualan = ?
				AND return_header.status IN ('approved', 'completed')
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) >= ?
				AND COALESCE(return_header.approved_at, return_header.tgl_retur) < ?
		", array($nota, $start.' 00:00:00', date('Y-m-d', strtotime($end.' +1 day')).' 00:00:00'))->row();
		$approved_returns = $return ? (float) $return->amount : 0;
		$sales_due = max(0, (float) $row->gross_total - (float) $row->diskon_voucher);
		$billable_total = $this->_billable_total($sales_due, $approved_returns);
		return array(
			'nota' => $row->nota,
			'npk' => (string) $row->npk,
			'transaction_date' => substr($row->transaction_date, 0, 10),
			'valid' => empty($issues),
			'issues' => $issues,
			'total_sales' => $sales_due,
			'approved_returns' => $approved_returns,
			'billable_total' => $billable_total,
			'payment_method' => trim((string) $row->metode_pembayaran)
		);
	}

	private function _save_billing_snapshot($period_id, $start, $end, $run_id)
	{
		$validation_items = $this->db->query("
			SELECT vi.*, adj.correct_source, adj.correct_total, adj.note AS adjustment_note
			FROM sales_validation_items vi
			".$this->_adjustment_join('vi', '?')."
			WHERE vi.run_id = ? AND (vi.status = 'valid' OR adj.id IS NOT NULL)
		", array((int) $period_id, (int) $run_id))->result();
		$rows = array();
		foreach ($validation_items as $item) {
			$rows[$item->nota] = array(
				'nota' => $item->nota,
				'npk' => $item->npk,
				'date' => $item->transaction_date.' 00:00:00',
				'total_sales' => (float) $item->total_sales,
				'approved_returns' => (float) $item->approved_returns,
				'payment_method' => $item->payment_method,
				'override_total' => $item->correct_source === NULL ? NULL : ($item->correct_source === 'physical'
					? (float) $item->correct_total : (float) $item->billable_total),
				'note' => $item->adjustment_note
			);
		}
		$returns = $this->db->query("
			SELECT return_header.nota_penjualan AS nota, sale.id_karyawan AS npk,
				MAX(COALESCE(return_header.approved_at, return_header.tgl_retur)) AS approved_at,
				SUM(return_item.subtotal) AS amount
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
					'approved_returns' => 0,
					'payment_method' => $this->_nota_payment_method($return->nota),
					'override_total' => NULL,
					'note' => NULL
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
				'billable_total' => $row['override_total'] !== NULL ? round($row['override_total'], 2)
					: $this->_billable_total($row['total_sales'], $row['approved_returns']),
				'payment_method' => $row['payment_method'],
				'adjustment_note' => $row['note']
			))) {
				return FALSE;
			}
		}
		return TRUE;
	}

	public function revisions($period_id = NULL)
	{
		$period = $this->_load_period($period_id);
		$reference = $this->_reference_run((int) $period->id);
		$items = array();
		$requested_adjustment = $this->_positive_id($this->input->get('adjustment_id', TRUE));
		if ($reference) {
			$adjustment_filter = $requested_adjustment ? ' AND adj.id = ?' : '';
			$params = array((int) $period->id, (int) $period->id, (int) $reference->id);
			if ($requested_adjustment) {
				$params[] = $requested_adjustment;
			}
			$items = $this->db->query("
				SELECT vi.nota, vi.npk, vi.billable_total, vi.status AS validation_status, vi.issues,
					member.nama AS member_name, scan.nota_total, scan.status AS scan_status,
					adj.id AS adj_id, adj.correct_source, adj.system_total AS adj_system_total, adj.correct_total,
					adj.note AS adj_note, adj.status AS adj_status, adj.revised_at, adj.decided_at,
					".$this->_user_name_sql('reviser', 'adj.revised_by')." AS reviser_name,
					".$this->_user_name_sql('decider', 'adj.decided_by')." AS decider_name
				FROM sales_validation_items vi
				LEFT JOIN sales_billing_scans scan ON scan.period_id = ? AND scan.nota = vi.nota
				LEFT JOIN sales_billing_adjustments adj ON adj.period_id = ? AND adj.nota = vi.nota
				LEFT JOIN karyawan member ON member.npk = vi.npk
				LEFT JOIN users reviser ON reviser.id = adj.revised_by
				LEFT JOIN users decider ON decider.id = adj.decided_by
				WHERE vi.run_id = ? AND (vi.status = 'invalid' OR adj.id IS NOT NULL
					OR (scan.id IS NOT NULL AND NOT (".$this->_match_sql('scan', 'vi').")))
				".$adjustment_filter."
				ORDER BY vi.nota ASC LIMIT 500
			", $params)->result();
		}
		$details = array();
		foreach ($items as $item) {
			$details[$item->nota] = $this->_nota_details($item->nota, $reference->start_date, $reference->end_date);
		}
		$this->_render('sales_billing_revisions', array(
			'period' => $period,
			'reference' => $reference,
			'items' => $items,
			'details' => $details,
			'is_manager' => $this->_is_manager(),
			'form_token' => $this->_form_token(),
			'judul_kecil' => 'Revisi & approval nota bermasalah'
		));
	}

	// Rincian pembentuk total sistem: barang, voucher, dan semua retur (termasuk yang tidak dihitung).
	private function _nota_details($nota, $start, $end)
	{
		$lines = $this->db->query("
			SELECT sale.id, sale.kode, stock.nama AS barang, sale.qty, sale.harga, sale.jumlah,
				CASE WHEN sale.dpp_satuan IS NOT NULL THEN 0 ELSE sale.jumlah * COALESCE(sale.ppn, 0) END AS ppn_tambahan,
				CASE WHEN sale.ppn_satuan IS NOT NULL THEN sale.ppn_satuan * sale.qty
					ELSE sale.jumlah * COALESCE(sale.ppn, 0) END AS ppn_total
			FROM penjualan sale
			LEFT JOIN stok stock ON stock.barcode = sale.kode
			WHERE sale.nota = ? ORDER BY sale.id ASC
		", array($nota))->result();
		$gross = 0;
		foreach ($lines as $line) {
			$gross += (float) $line->jumlah + (float) $line->ppn_tambahan;
		}
		$payment = $this->db->get_where('penjualan_pembayaran', array('nota' => $nota))->row();
		$returns = $this->db->query("
			SELECT header.id, header.nomor_retur, header.status, header.tgl_retur, header.approved_at,
				header.alasan, header.rejection_reason, header.total_retur,
				COALESCE(header.approved_at, header.tgl_retur) AS effective_at
			FROM retur_penjualan header WHERE header.nota_penjualan = ? ORDER BY header.id ASC
		", array($nota))->result();
		$start_at = $start.' 00:00:00';
		$end_at = date('Y-m-d', strtotime($end.' +1 day')).' 00:00:00';
		$counted_total = 0;
		foreach ($returns as $return) {
			$return->items = $this->db->query("
				SELECT item.barcode, stock.nama AS barang, item.qty_retur, item.harga_satuan, item.subtotal
				FROM retur_penjualan_items item LEFT JOIN stok stock ON stock.barcode = item.barcode
				WHERE item.retur_id = ?
			", array((int) $return->id))->result();
			$approved = in_array($return->status, array('approved', 'completed'), TRUE);
			$in_period = $return->effective_at >= $start_at && $return->effective_at < $end_at;
			$return->counted = $approved && $in_period;
			if ($return->counted) {
				foreach ($return->items as $item) {
					$counted_total += (float) $item->subtotal;
				}
			}
			if (!$approved) {
				$return->reason_not_counted = 'Status '.$return->status.' — belum/tidak disetujui sehingga tidak mengurangi tagihan.';
			} elseif (!$in_period) {
				$return->reason_not_counted = 'Disetujui di luar rentang validasi ('.$start.' s.d. '.$end.') sehingga tidak mengurangi tagihan periode ini.';
			} else {
				$return->reason_not_counted = '';
			}
		}
		$voucher = $payment ? (float) $payment->diskon_voucher : 0;
		return array(
			'lines' => $lines,
			'gross' => $gross,
			'voucher' => $voucher,
			'voucher_code' => $payment ? $payment->kode_voucher : NULL,
			'payment_method' => $payment ? $payment->metode_pembayaran : NULL,
			'payment_total' => $payment ? (float) $payment->total_tagihan : NULL,
			'sales_due' => max(0, $gross - $voucher),
			'returns' => $returns,
			'returns_counted' => $counted_total
		);
	}

	public function revise_nota($period_id = NULL)
	{
		$this->_require_post_token();
		$period_id = $this->_positive_id($period_id);
		if (!$period_id) {
			show_404();
		}
		$back = 'main/sales_billing/revisions/'.$period_id;
		$period = $this->db->get_where('sales_billing_periods', array('id' => $period_id))->row();
		if (!$period || $period->status !== 'open') {
			$this->_flash_to('Periode tidak ditemukan atau sudah difinalisasi.', FALSE, $back);
		}
		$reference = $this->_reference_run($period_id);
		$nota = trim((string) $this->input->post('nota', TRUE));
		$source = (string) $this->input->post('correct_source', TRUE);
		$note = trim((string) $this->input->post('note', TRUE));
		$total_raw = trim((string) $this->input->post('correct_total', TRUE));
		if (!$reference || $nota === '' || !in_array($source, array('system', 'physical'), TRUE) ||
			$note === '' || strlen($note) > 500) {
			$this->_flash_to('Pilih sumber yang benar dan isi keterangan (maks. 500 karakter).', FALSE, $back);
		}
		if ($source === 'physical' && !preg_match('/^-?[0-9]+(\.[0-9]{1,2})?$/', $total_raw)) {
			$this->_flash_to('Nominal nota fisik harus angka tanpa titik ribuan.', FALSE, $back);
		}
		$item = $this->db->query("
			SELECT vi.*, scan.status AS scan_status, scan.system_total AS scan_system_total
			FROM sales_validation_items vi
			LEFT JOIN sales_billing_scans scan ON scan.period_id = ? AND scan.nota = vi.nota
			WHERE vi.run_id = ? AND vi.nota = ?
		", array($period_id, (int) $reference->id, $nota))->row();
		if (!$item) {
			$this->_flash_to('Nota tidak ada pada validasi terakhir.', FALSE, $back);
		}
		$scan_ok = $item->scan_status === 'match' && abs((float) $item->scan_system_total - (float) $item->billable_total) < 0.01;
		$has_scan = $item->scan_status !== NULL;
		$existing = $this->db->get_where('sales_billing_adjustments', array('period_id' => $period_id, 'nota' => $nota))->row();
		if ($item->status !== 'invalid' && !($has_scan && !$scan_ok) && !$existing) {
			$this->_flash_to('Nota ini tidak bermasalah sehingga tidak perlu revisi.', FALSE, $back);
		}
		$row = array(
			'correct_source' => $source,
			'system_total' => (float) $item->billable_total,
			'correct_total' => $source === 'physical' ? (float) $total_raw : (float) $item->billable_total,
			'note' => $note,
			'status' => 'pending',
			'revised_by' => (string) $this->ion_auth->get_user_id(),
			'revised_at' => date('Y-m-d H:i:s'),
			'decided_by' => NULL,
			'decided_at' => NULL
		);
		if ($existing) {
			$saved = $this->db->where('id', (int) $existing->id)->update('sales_billing_adjustments', $row);
		} else {
			$saved = $this->db->insert('sales_billing_adjustments', array_merge($row, array('period_id' => $period_id, 'nota' => $nota)));
		}
		$this->_flash_to($saved ? 'Revisi nota '.$nota.' tersimpan dan menunggu approval.' : 'Revisi gagal disimpan.', (bool) $saved, $back);
	}

	public function decide_adjustment($period_id = NULL)
	{
		$this->_require_manager_post();
		$period_id = $this->_positive_id($period_id);
		$adjustment_id = $this->_positive_id($this->input->post('adjustment_id', TRUE));
		$decision = (string) $this->input->post('decision', TRUE);
		if (!$period_id || !$adjustment_id || !in_array($decision, array('approved', 'rejected'), TRUE)) {
			show_404();
		}
		$back = 'main/sales_billing/revisions/'.$period_id;
		$period = $this->db->get_where('sales_billing_periods', array('id' => $period_id))->row();
		$adjustment = $this->db->get_where('sales_billing_adjustments', array('id' => $adjustment_id, 'period_id' => $period_id))->row();
		if (!$period || $period->status !== 'open' || !$adjustment || $adjustment->status !== 'pending') {
			$this->_flash_to('Revisi tidak ditemukan atau sudah diputuskan.', FALSE, $back);
		}
		$user_id = (string) $this->ion_auth->get_user_id();
		if ($adjustment->revised_by === $user_id && !$this->ion_auth->in_group('admin')) {
			$this->_flash_to('Revisi harus disetujui oleh orang lain selain pembuat revisi.', FALSE, $back);
		}
		$this->db->where('id', $adjustment_id)->where('status', 'pending')->update('sales_billing_adjustments', array(
			'status' => $decision,
			'decided_by' => $user_id,
			'decided_at' => date('Y-m-d H:i:s')
		));
		$this->_flash_to('Revisi nota '.$adjustment->nota.($decision === 'approved' ? ' disetujui.' : ' ditolak.'), TRUE, $back);
	}

	public function export_recap($period_id = NULL, $method = '')
	{
		$period = $this->_load_period($period_id);
		if ($period->status !== 'finalized') {
			show_error('Rekap hanya tersedia setelah periode digenerate.', 409);
		}
		if (!preg_match('/^[a-z_]{1,20}$/', (string) $method)) {
			show_404();
		}
		$db_method = $method === 'tidak_tercatat' ? '' : $method;
		$summary = $this->db->query("
			SELECT bi.npk, MAX(COALESCE(employee.nama, bi.employee_name)) AS nama, COUNT(*) AS nota_count,
				SUM(bi.total_sales) AS sales, SUM(bi.approved_returns) AS returns, SUM(bi.billable_total) AS billable
			FROM sales_billing_items bi
			LEFT JOIN karyawan employee ON employee.npk = bi.npk
			WHERE bi.period_id = ? AND bi.payment_method = ?
			GROUP BY bi.npk ORDER BY bi.npk ASC
		", array((int) $period->id, $db_method))->result();
		if (empty($summary)) {
			show_404();
		}
		$label = $this->_method_label($method);
		$sheet1 = array(array('NPK', 'Nama', 'Jumlah Nota', 'Penjualan', 'Retur Disetujui', $method === 'potong_gaji' ? 'Total Potong Gaji' : 'Total Tagihan'));
		$totals = array(0, 0, 0, 0);
		foreach ($summary as $row) {
			$sheet1[] = array((string) $row->npk, (string) $row->nama, (int) $row->nota_count, (float) $row->sales, (float) $row->returns, (float) $row->billable);
			$totals[0] += (int) $row->nota_count;
			$totals[1] += (float) $row->sales;
			$totals[2] += (float) $row->returns;
			$totals[3] += (float) $row->billable;
		}
		$sheet1[] = array('', 'TOTAL', $totals[0], $totals[1], $totals[2], $totals[3]);

		$lines = $this->db->query("
			SELECT sale.nota, sale.tgl, sale.id_karyawan AS npk, COALESCE(employee.nama, bi.employee_name) AS nama,
				sale.kode, stock.nama AS barang, sale.qty, sale.harga, sale.jumlah,
				CASE WHEN sale.ppn_satuan IS NOT NULL THEN sale.ppn_satuan * sale.qty
					ELSE sale.jumlah * COALESCE(sale.ppn, 0) END AS ppn_total
			FROM sales_billing_items bi
			JOIN penjualan sale ON sale.nota = bi.nota
			LEFT JOIN karyawan employee ON employee.npk = bi.npk
			LEFT JOIN stok stock ON stock.barcode = sale.kode
			WHERE bi.period_id = ? AND bi.payment_method = ?
			ORDER BY sale.tgl ASC, sale.nota ASC, sale.id ASC
		", array((int) $period->id, $db_method))->result();
		$sheet2 = array(array('Nota', 'Tanggal', 'NPK', 'Nama', 'Kode', 'Barang', 'Qty', 'Harga', 'Jumlah', 'PPN'));
		foreach ($lines as $line) {
			$sheet2[] = array((string) $line->nota, (string) $line->tgl, (string) $line->npk, (string) $line->nama,
				(string) $line->kode, (string) $line->barang, (int) $line->qty, (float) $line->harga,
				(float) $line->jumlah, (float) $line->ppn_total);
		}

		$notas = $this->db->query("
			SELECT bi.*, COALESCE(employee.nama, bi.employee_name) AS nama
			FROM sales_billing_items bi LEFT JOIN karyawan employee ON employee.npk = bi.npk
			WHERE bi.period_id = ? AND bi.payment_method = ?
			ORDER BY bi.npk ASC, bi.transaction_date ASC, bi.nota ASC
		", array((int) $period->id, $db_method))->result();
		$sheet3 = array(array('Nota', 'Tanggal', 'NPK', 'Nama', 'Penjualan', 'Retur Disetujui', 'Tertagih', 'Catatan Revisi'));
		foreach ($notas as $nota) {
			$sheet3[] = array((string) $nota->nota, substr((string) $nota->transaction_date, 0, 10), (string) $nota->npk,
				(string) $nota->nama, (float) $nota->total_sales, (float) $nota->approved_returns,
				(float) $nota->billable_total, (string) $nota->adjustment_note);
		}

		$this->load->library('simple_xlsx');
		$file = $this->simple_xlsx->build(array('Rekap per NPK' => $sheet1, 'Detail Penjualan' => $sheet2, 'Ringkasan Nota' => $sheet3));
		if ($file === FALSE) {
			show_error('File Excel gagal dibuat.', 500);
		}
		$slug = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $period->period_name.'_'.$label), '_');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="rekap_'.$slug.'.xlsx"');
		header('Content-Length: '.filesize($file));
		readfile($file);
		unlink($file);
		exit;
	}

	private function _method_label($method)
	{
		$labels = array('potong_gaji' => 'Potong Gaji', 'tunai' => 'Tunai', 'cash' => 'Cash', 'qris' => 'QRIS',
			'edc' => 'EDC', 'transfer' => 'Transfer', 'tidak_tercatat' => 'Tidak Tercatat');
		return isset($labels[$method]) ? $labels[$method] : ucfirst($method);
	}

	private function _require_post_token()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$expected = $this->session->userdata('sales_billing_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir tagihan tidak valid. Muat ulang halaman.', 403);
		}
	}

	private function _flash_to($message, $success, $uri)
	{
		$this->session->set_flashdata($success ? 'sales_billing_notice' : 'sales_billing_error', $message);
		redirect($uri);
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

	public function scan_nota()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('kind' => 'error', 'message' => 'Metode permintaan tidak diizinkan.'));
		}
		$expected = $this->session->userdata('sales_billing_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			$this->_json(array('kind' => 'error', 'message' => 'Sesi tidak valid. Muat ulang halaman.'));
		}
		$period_id = $this->_positive_id($this->input->post('period_id', TRUE));
		$nota = trim((string) $this->input->post('nota', TRUE));
		$nota_total_raw = trim((string) $this->input->post('nota_total', TRUE));
		if (!$period_id || $nota === '' || strlen($nota) > 64) {
			$this->_json(array('kind' => 'error', 'message' => 'Nomor nota tidak valid.'));
		}
		if ($nota_total_raw !== '' && !preg_match('/^-?[0-9]+(\.[0-9]{1,2})?$/', $nota_total_raw)) {
			$this->_json(array('kind' => 'error', 'message' => 'Nominal nota harus berupa angka tanpa titik ribuan.'));
		}
		$period = $this->db->get_where('sales_billing_periods', array('id' => $period_id))->row();
		if (!$period || $period->status !== 'open') {
			$this->_json(array('kind' => 'error', 'message' => 'Periode tidak ditemukan atau sudah difinalisasi.'));
		}
		$reference = $this->_reference_run($period_id);
		if (!$reference) {
			$this->_json(array('kind' => 'error', 'message' => 'Jalankan validasi seluruh periode terlebih dahulu.'));
		}
		$item = $this->db->query("
			SELECT vi.*, member.nama AS member_name
			FROM sales_validation_items vi
			LEFT JOIN karyawan member ON member.npk = vi.npk
			WHERE vi.run_id = ? AND vi.nota = ? AND ".$this->_expected_sql('vi'),
			array((int) $reference->id, $nota))->row();
		if (!$item) {
			$this->_json(array('kind' => 'error', 'message' => 'Nota '.$nota.' tidak termasuk dalam periode ini.'));
		}
		$info = array(
			'nota' => $item->nota,
			'npk' => $item->npk,
			'member_name' => $item->member_name,
			'transaction_date' => $item->transaction_date,
			'system_total' => (float) $item->billable_total
		);
		if ($item->status !== 'valid') {
			$this->_json(array_merge($info, array('kind' => 'error',
				'message' => 'Nota bermasalah pada validasi: '.($item->issues ? $item->issues : 'perlu diperiksa').' Perbaiki lalu validasi ulang.')));
		}
		$existing = $this->db->query("
			SELECT scan.*, ".$this->_user_name_sql('operator', 'scan.scanned_by')." AS operator_name
			FROM sales_billing_scans scan
			LEFT JOIN users operator ON operator.id = scan.scanned_by
			WHERE scan.period_id = ? AND scan.nota = ?
		", array($period_id, $nota))->row();
		$is_current = $existing && $existing->status === 'match' &&
			abs((float) $existing->system_total - (float) $item->billable_total) < 0.01;
		if ($is_current && $nota_total_raw === '') {
			$this->_json(array_merge($info, array('kind' => 'duplicate',
				'message' => 'Nota sudah di-scan oleh '.$existing->operator_name.' pada '.$existing->scan_time.'.',
				'stats' => $this->_scan_stats($period_id, (int) $reference->id))));
		}
		$status = 'match';
		$nota_total = NULL;
		if ($nota_total_raw !== '') {
			$nota_total = (float) $nota_total_raw;
			$status = abs($nota_total - (float) $item->billable_total) < 0.01 ? 'match' : 'mismatch';
		}
		$row = array(
			'period_id' => $period_id,
			'nota' => $nota,
			'scan_time' => date('Y-m-d H:i:s'),
			'scanned_by' => (string) $this->ion_auth->get_user_id(),
			'system_total' => (float) $item->billable_total,
			'nota_total' => $nota_total,
			'status' => $status
		);
		if ($existing) {
			$saved = $this->db->where('id', (int) $existing->id)->update('sales_billing_scans', $row);
		} else {
			$saved = $this->db->insert('sales_billing_scans', $row);
		}
		if (!$saved) {
			$this->_json(array_merge($info, array('kind' => 'error', 'message' => 'Hasil scan gagal disimpan.')));
		}
		$user = $this->ion_auth->user()->row();
		$operator = $user ? trim($user->first_name.' '.$user->last_name) : '';
		if ($operator === '' && $user) {
			$operator = $user->username;
		}
		$this->_json(array_merge($info, array(
			'kind' => $status === 'match' ? 'ok' : 'mismatch',
			'message' => $status === 'match'
				? 'Nota tercatat. Cocokkan nominal sistem dengan nota fisik.'
				: 'Nominal nota fisik tidak sama dengan sistem. Selisih '.number_format($nota_total - (float) $item->billable_total, 0, ',', '.').'.',
			'scan_time' => $row['scan_time'],
			'operator_name' => $operator,
			'nota_total' => $nota_total,
			'stats' => $this->_scan_stats($period_id, (int) $reference->id)
		)));
	}

	private function _json($payload)
	{
		$payload['success'] = isset($payload['kind']) && in_array($payload['kind'], array('ok', 'mismatch', 'duplicate'), TRUE);
		$this->output->set_content_type('application/json')->set_output(json_encode($payload));
		$this->output->_display();
		exit;
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
		redirect('main/sales_billing'.($period_id ? '/detail/'.(int) $period_id : ''));
	}
}