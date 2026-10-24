<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Voucher extends CI_Controller
{
	private $coupon_schema_available = FALSE;

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->coupon_schema_available = $this->db->table_exists('voucher_coupons') &&
			$this->db->field_exists('voucher_coupon_id', 'voucher_usages');
		$this->load->helper(array('url', 'form', 'string'));
		$this->load->library(array('session', 'ion_auth'));
		$this->load->library('page_access');

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('vouchers')) {
			show_error('Anda tidak memiliki akses ke master voucher.', 403);
		}
	}

	public function index()
	{
		$search_input = $this->input->get('q', TRUE);
		$data['voucher_search'] = is_string($search_input) ? substr(trim($search_input), 0, 100) : '';
		$page_input = $this->input->get('page', TRUE);
		$data['voucher_page'] = max(1, $this->_non_negative_integer($page_input, 1));
		$page_size_input = $this->input->get('per_page', TRUE);
		$data['voucher_page_size'] = is_string($page_size_input) && in_array((int) $page_size_input, array(25, 50, 100), TRUE)
			? (int) $page_size_input : 25;

		$count_query = $this->db->from('vouchers');
		if ($data['voucher_search'] !== '') {
			$count_query->group_start()
				->like('kode', $data['voucher_search'])
				->or_like('nama', $data['voucher_search'])
				->group_end();
		}
		$data['voucher_total'] = (int) $count_query->count_all_results();
		$data['voucher_pages'] = max(1, (int) ceil($data['voucher_total'] / $data['voucher_page_size']));
		$data['voucher_page'] = min($data['voucher_page'], $data['voucher_pages']);

		$list_query = $this->db->from('vouchers');
		if ($data['voucher_search'] !== '') {
			$list_query->group_start()
				->like('kode', $data['voucher_search'])
				->or_like('nama', $data['voucher_search'])
				->group_end();
		}
		$data['vouchers'] = $list_query->order_by('id', 'DESC')
			->limit($data['voucher_page_size'], ($data['voucher_page'] - 1) * $data['voucher_page_size'])
			->get()->result();
		$data['can_manage'] = $this->_can_manage();
		$data['coupon_schema_available'] = $this->coupon_schema_available;
		$data['voucher_import_preview'] = $this->session->userdata('voucher_import_preview');
		$data['voucher_import_token'] = $this->_coupon_form_token();
		$data['notice'] = $this->session->flashdata('voucher_notice');
		$data['error'] = $this->session->flashdata('voucher_error');
		$data['validation_errors'] = $this->session->flashdata('voucher_validation_errors');
		$data['form_values'] = $this->session->flashdata('voucher_form_values');
		$data['form_id'] = $this->session->flashdata('voucher_form_id');
		$data['form_id'] = is_numeric($data['form_id']) ? (int) $data['form_id'] : NULL;
		if (!is_array($data['validation_errors'])) {
			$data['validation_errors'] = array();
		}
		if (!is_array($data['form_values'])) {
			$data['form_values'] = array();
		}
		$data['judul_besar'] = 'Master Voucher';
		$data['judul_kecil'] = 'Atur promo, masa berlaku, kuota, dan lihat pemakaian';
		$data['m_vouchers'] = TRUE;
		$data['css_file'] = array(
			base_url('assets/adminlte/plugins/datatables/dataTables.bootstrap.css')
		);
		$data['js_file'] = array(
			base_url('assets/adminlte/plugins/datatables/jquery.dataTables.min.js'),
			base_url('assets/adminlte/plugins/datatables/dataTables.bootstrap.min.js'),
			base_url('assets/voucher/voucher_history.js?v=20261024-server-paged-vouchers'),
			base_url('assets/voucher/voucher_form.js?v=20261010-field-validation')
		);
		$data['output'] = $this->load->view('vouchers', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function preview_voucher_import()
	{
		$this->_require_coupon_post();
		$file = isset($_FILES['voucher_file']) ? $_FILES['voucher_file'] : NULL;
		$extension = $file && isset($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
		if (!$file || !isset($file['error'], $file['size'], $file['tmp_name']) ||
			$file['error'] !== UPLOAD_ERR_OK || $file['size'] < 1 || $file['size'] > 5242880 ||
			!in_array($extension, array('xls', 'xlsx'), TRUE) || !is_uploaded_file($file['tmp_name'])) {
			$this->session->set_flashdata('voucher_error', 'Pilih file XLS/XLSX maksimal 5 MB.');
			redirect('main/voucher');
		}

		try {
			$this->load->library('excel');
			$sheet = PHPExcel_IOFactory::load($file['tmp_name'])->getActiveSheet();
			$last_row = max(
				(int) $sheet->getHighestDataRow('A'),
				(int) $sheet->getHighestDataRow('B'),
				(int) $sheet->getHighestDataRow('C'),
				(int) $sheet->getHighestDataRow('D')
			);
			if ($last_row > 10001) {
				throw new RuntimeException('File berisi lebih dari 10000 voucher.');
			}
			$expected_headers = array('NAMA_VOUCHER', 'NPK', 'NOMINAL', 'TGL_KADALUARSA');
			foreach ($expected_headers as $column => $expected) {
				$header = strtoupper(trim((string) $sheet->getCellByColumnAndRow($column, 1)->getFormattedValue()));
				if ($header !== $expected) {
					throw new RuntimeException('Header Excel harus NAMA_VOUCHER, NPK, NOMINAL, TGL_KADALUARSA pada kolom A-D.');
				}
			}
			$rows = array();
			for ($row_number = 2; $row_number <= $last_row; $row_number++) {
				$name = trim((string) $sheet->getCellByColumnAndRow(0, $row_number)->getFormattedValue());
				$npk = strtoupper(trim((string) $sheet->getCellByColumnAndRow(1, $row_number)->getFormattedValue()));
				$amount_value = $sheet->getCellByColumnAndRow(2, $row_number)->getCalculatedValue();
				$expiry_cell = $sheet->getCellByColumnAndRow(3, $row_number);
				$expiry_value = $expiry_cell->getValue();
				if ($name === '' && $npk === '' && ($amount_value === NULL || $amount_value === '') &&
					($expiry_value === NULL || $expiry_value === '')) {
					continue;
				}
				$amount = is_numeric($amount_value) && floor((float) $amount_value) === (float) $amount_value
					? sprintf('%.0f', $amount_value) : trim((string) $amount_value);
				$expiry = $this->_parse_import_expiry($expiry_value);
				$serial = $npk.'-'.$amount;
				$rows[] = array(
					'row' => $row_number,
					'name' => $name,
					'assigned_npk' => $npk,
					'amount' => $amount,
					'expiry' => $expiry,
					'serial_code' => $serial
				);
			}
		} catch (Exception $exception) {
			$this->session->set_flashdata('voucher_error', 'File Excel tidak dapat dibaca: '.$exception->getMessage());
			redirect('main/voucher');
		}

		$errors = $this->_validate_voucher_import_rows($rows);
		$this->session->set_userdata('voucher_import_preview', array('rows' => $rows, 'errors' => $errors));
		redirect('main/voucher');
	}

	public function confirm_voucher_import()
	{
		$this->_require_coupon_post();
		$preview = $this->session->userdata('voucher_import_preview');
		if (!is_array($preview) || !empty($preview['errors']) || empty($preview['rows'])) {
			$this->session->set_flashdata('voucher_error', 'Preview impor tidak valid atau kedaluwarsa. Unggah ulang file.');
			redirect('main/voucher');
		}
		$errors = $this->_validate_voucher_import_rows($preview['rows']);
		if (!empty($errors)) {
			$preview['errors'] = $errors;
			$this->session->set_userdata('voucher_import_preview', $preview);
			$this->session->set_flashdata('voucher_error', 'Data berubah setelah preview. Periksa kembali daftar voucher.');
			redirect('main/voucher');
		}
		if (!$this->db->trans_begin()) {
			$this->session->set_flashdata('voucher_error', 'Database tidak dapat memulai impor voucher.');
			redirect('main/voucher');
		}

		$created_at = date('Y-m-d H:i:s');
		$created_by = (string) $this->ion_auth->get_user_id();
		foreach ($preview['rows'] as $row) {
			$voucher = array(
				'kode' => $row['serial_code'],
				'nama' => $row['name'],
				'tipe' => 'diskon_nominal',
				'nilai' => number_format((float) $row['amount'], 2, '.', ''),
				'min_belanja' => 0,
				'max_diskon' => NULL,
				'kuota' => 1,
				'terpakai' => 0,
				'is_active' => 1,
				'tgl_mulai' => $created_at,
				'tgl_kadaluarsa' => $row['expiry'],
				'created_at' => $created_at
			);
			if (!$this->db->insert('vouchers', $voucher)) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('voucher_error', 'Impor gagal. Tidak ada voucher yang disimpan.');
				redirect('main/voucher');
			}
			$voucher_id = (int) $this->db->insert_id();
			if (!$this->db->insert('voucher_coupons', array(
				'voucher_id' => $voucher_id,
				'serial_code' => $row['serial_code'],
				'assigned_npk' => $row['assigned_npk'],
				'status' => 'available',
				'created_at' => $created_at,
				'created_by' => $created_by
			))) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('voucher_error', 'Impor gagal. Tidak ada voucher yang disimpan.');
				redirect('main/voucher');
			}
		}
		if ($this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('voucher_error', 'Impor gagal. Tidak ada voucher yang disimpan.');
			redirect('main/voucher');
		}
		$this->session->unset_userdata('voucher_import_preview');
		$this->session->set_flashdata('voucher_notice', count($preview['rows']).' voucher berhasil diimpor.');
		redirect('main/voucher');
	}

	public function usage_data()
	{
		if ($this->input->method(TRUE) !== 'GET') {
			show_404();
		}

		$draw = $this->_non_negative_integer($this->input->get('draw'), 0);
		$start = $this->_non_negative_integer($this->input->get('start'), 0);
		$length = $this->_non_negative_integer($this->input->get('length'), 25);
		$length = max(1, min(100, $length));
		$search_data = $this->input->get('search');
		$search = is_array($search_data) && isset($search_data['value']) &&
			is_string($search_data['value']) ? trim($search_data['value']) : '';
		$search = substr($search, 0, 100);
		$npk_input = $this->input->get('npk', TRUE);
		$npk = is_string($npk_input) ? trim($npk_input) : '';
		if ((!is_null($npk_input) && !is_string($npk_input)) || strlen($npk) > 32 ||
			($npk !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $npk))) {
			$this->output->set_status_header(400);
			$this->output->set_content_type('application/json', 'utf-8')->set_output(json_encode(array(
				'draw' => $draw,
				'recordsTotal' => 0,
				'recordsFiltered' => 0,
				'data' => array()
			)));
			return;
		}

		$total = $this->_voucher_usage_query()->count_all_results();
		$filtered_query = $this->_voucher_usage_query();
		if ($npk !== '') {
			$filtered_query->where('voucher_use.npk_anggota', $npk);
		}
		if ($search !== '') {
			$filtered_query->group_start()
				->like('voucher.kode', $search)
				->or_like('voucher.nama', $search)
				->or_like('voucher_use.nota', $search)
				->or_like('voucher_use.npk_anggota', $search)
				->or_like('voucher_use.used_at', $search);
			if ($this->coupon_schema_available) {
				$filtered_query->or_like('voucher_coupon.serial_code', $search);
			}
			$filtered_query
				->group_end();
		}
		$filtered = $filtered_query->count_all_results();

		$order_data = $this->input->get('order');
		$order_column = 0;
		$order_direction = 'desc';
		$order_columns = array(
			0 => 'voucher_use.used_at',
			1 => 'voucher.kode',
			2 => 'voucher.nama',
			3 => 'voucher_use.nota',
			4 => 'voucher_use.npk_anggota',
			5 => $this->coupon_schema_available ? 'voucher_coupon.serial_code' : 'voucher_use.id',
			6 => 'voucher_use.nominal_diskon'
		);
		if (is_array($order_data) && isset($order_data[0]) && is_array($order_data[0])) {
			if (isset($order_data[0]['column']) && ctype_digit((string) $order_data[0]['column'])) {
				$order_column = (int) $order_data[0]['column'];
			}
			if (isset($order_data[0]['dir']) && strtolower($order_data[0]['dir']) === 'asc') {
				$order_direction = 'asc';
			}
		}
		if (!isset($order_columns[$order_column])) {
			$order_column = 0;
		}

		$row_query = $this->_voucher_usage_query();
		if ($npk !== '') {
			$row_query->where('voucher_use.npk_anggota', $npk);
		}
		$row_query->select('voucher_use.used_at, voucher.kode, voucher.nama, voucher_use.nota,
			voucher_use.npk_anggota, voucher_use.nominal_diskon');
		if ($this->coupon_schema_available) {
			$row_query->select('voucher_coupon.serial_code');
		} else {
			$row_query->select('NULL AS serial_code', FALSE);
		}
		$rows = $row_query
			->order_by($order_columns[$order_column], $order_direction)
			->order_by('voucher_use.id', 'DESC')
			->limit($length, $start)
			->get()
			->result_array();

		$this->output->set_content_type('application/json', 'utf-8')->set_output(json_encode(array(
			'draw' => $draw,
			'recordsTotal' => $total,
			'recordsFiltered' => $filtered,
			'data' => $rows
		)));
	}

	public function coupons($voucher_id = NULL)
	{
		$this->_require_manager();
		$this->_require_coupon_schema();
		if (!$this->_is_positive_integer($voucher_id)) {
			show_404();
		}
		$voucher = $this->db->get_where('vouchers', array('id' => (int) $voucher_id))->row();
		if (!$voucher) {
			show_404();
		}
		$preview = $this->session->userdata('voucher_coupon_import_preview');
		if (!is_array($preview) || (int) $preview['voucher_id'] !== (int) $voucher_id) {
			$preview = NULL;
		}
		$data = array(
			'voucher' => $voucher,
			'coupons' => $this->db->where('voucher_id', (int) $voucher_id)
				->order_by('id', 'DESC')->limit(500)->get('voucher_coupons')->result(),
			'preview' => $preview,
			'notice' => $this->session->flashdata('voucher_coupon_notice'),
			'error' => $this->session->flashdata('voucher_coupon_error'),
			'form_token' => $this->_coupon_form_token(),
			'judul_besar' => 'Kupon Voucher',
			'judul_kecil' => $voucher->kode.' — '.html_escape($voucher->nama),
			'm_vouchers' => TRUE
		);
		$data['output'] = $this->load->view('voucher_coupons', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function generate_coupons()
	{
		$this->_require_coupon_post();
		$voucher_id = (int) $this->input->post('voucher_id');
		$quantity = trim((string) $this->input->post('quantity', TRUE));
		$prefix = strtoupper(trim((string) $this->input->post('prefix', TRUE)));
		if (!preg_match('/^[1-9][0-9]{0,3}$/', $quantity) ||
			(int) $quantity > 500 || ($prefix !== '' && !preg_match('/^[A-Z0-9_-]{1,12}$/', $prefix))) {
			$this->_coupon_redirect($voucher_id, 'Jumlah, awalan serial, atau voucher tidak valid.', FALSE);
		}
		$quantity = (int) $quantity;
		if (!$this->db->trans_begin()) {
			$this->_coupon_redirect($voucher_id, 'Database tidak dapat memulai generate kupon.', FALSE);
		}
		$voucher = $this->db->query('SELECT * FROM vouchers WHERE id = ? FOR UPDATE', array($voucher_id))->row();
		if (!$voucher) {
			$this->db->trans_rollback();
			$this->_coupon_redirect($voucher_id, 'Voucher tidak ditemukan.', FALSE);
		}
		$prefix = $prefix !== '' ? $prefix : substr(strtoupper($voucher->kode), 0, 12);
		$occupied_quota = $this->_coupon_quota_occupied($voucher_id);
		if ((int) $voucher->kuota > 0 && $occupied_quota + $quantity > (int) $voucher->kuota) {
			$this->db->trans_rollback();
			$this->_coupon_redirect($voucher_id, 'Jumlah kupon melebihi kuota voucher yang tersedia.', FALSE);
		}

		for ($index = 0; $index < $quantity; $index++) {
			$serial = $prefix.'-'.strtoupper(random_string('alnum', 12));
			if ($this->db->insert('voucher_coupons', array(
				'voucher_id' => $voucher_id,
				'serial_code' => $serial,
				'assigned_npk' => NULL,
				'status' => 'available',
				'created_at' => date('Y-m-d H:i:s'),
				'created_by' => (string) $this->ion_auth->get_user_id()
			)) === FALSE) {
				$error = $this->db->error();
				$this->db->trans_rollback();
				log_message('error', 'Gagal membuat kupon voucher '.$voucher->kode.': '.$error['code'].' '.$error['message']);
				$this->_coupon_redirect($voucher_id, 'Kupon gagal dibuat; tidak ada serial yang disimpan.', FALSE);
			}
		}
		if ($this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->_coupon_redirect($voucher_id, 'Kupon gagal dibuat; tidak ada serial yang disimpan.', FALSE);
		}
		$this->_coupon_redirect($voucher_id, $quantity.' kupon berhasil dibuat.', TRUE);
	}

	public function preview_coupon_import($voucher_id = NULL)
	{
		$this->_require_coupon_post();
		if (!$this->_is_positive_integer($voucher_id) ||
			!$this->db->get_where('vouchers', array('id' => (int) $voucher_id))->row()) {
			show_404();
		}
		$file = isset($_FILES['coupon_file']) ? $_FILES['coupon_file'] : NULL;
		$extension = $file && isset($file['name']) ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
		if (!$file || !isset($file['error'], $file['size'], $file['tmp_name']) ||
			$file['error'] !== UPLOAD_ERR_OK || $file['size'] < 1 || $file['size'] > 5242880 ||
			!in_array($extension, array('xls', 'xlsx'), TRUE) || !is_uploaded_file($file['tmp_name'])) {
			$this->_coupon_redirect((int) $voucher_id, 'Pilih file XLS/XLSX maksimal 5 MB.', FALSE);
		}

		$this->load->library('excel');
		try {
			$workbook = PHPExcel_IOFactory::load($file['tmp_name']);
			$sheet = $workbook->getActiveSheet();
			$last_row = (int) $sheet->getHighestRow();
			if ($last_row > 1001) {
				throw new RuntimeException('File berisi lebih dari 1000 baris kupon.');
			}
			$serial_heading = strtoupper(trim((string) $sheet->getCellByColumnAndRow(0, 1)->getFormattedValue()));
			$npk_heading = strtoupper(trim((string) $sheet->getCellByColumnAndRow(1, 1)->getFormattedValue()));
			if ($serial_heading !== 'SERIAL_KUPON' || $npk_heading !== 'NPK') {
				throw new RuntimeException('Header Excel harus SERIAL_KUPON pada kolom A dan NPK pada kolom B.');
			}
			$rows = array();
			for ($row_number = 2; $row_number <= $last_row; $row_number++) {
				$serial = strtoupper(trim((string) $sheet->getCellByColumnAndRow(0, $row_number)->getFormattedValue()));
				$npk = strtoupper(trim((string) $sheet->getCellByColumnAndRow(1, $row_number)->getFormattedValue()));
				if ($serial === '' && $npk === '') {
					continue;
				}
				$rows[] = array('row' => $row_number, 'serial_code' => $serial, 'assigned_npk' => $npk);
			}
		} catch (Exception $exception) {
			$this->_coupon_redirect((int) $voucher_id, 'File Excel tidak dapat dibaca: '.$exception->getMessage(), FALSE);
		}
		$import_errors = $this->_validate_coupon_import_rows((int) $voucher_id, $rows);
		$this->session->set_userdata('voucher_coupon_import_preview', array(
			'voucher_id' => (int) $voucher_id,
			'rows' => $rows,
			'errors' => $import_errors
		));
		redirect('main/voucher/coupons/'.(int) $voucher_id);
	}

	public function confirm_coupon_import($voucher_id = NULL)
	{
		$this->_require_coupon_post();
		if (!$this->_is_positive_integer($voucher_id)) {
			show_404();
		}
		$preview = $this->session->userdata('voucher_coupon_import_preview');
		if (!is_array($preview) || (int) $preview['voucher_id'] !== (int) $voucher_id ||
			!empty($preview['errors']) || empty($preview['rows'])) {
			$this->_coupon_redirect((int) $voucher_id, 'Preview impor tidak valid atau sudah kedaluwarsa. Unggah ulang file.', FALSE);
		}
		if (!$this->db->trans_begin()) {
			$this->_coupon_redirect((int) $voucher_id, 'Database tidak dapat memulai impor kupon.', FALSE);
		}
	$voucher = $this->db->query('SELECT id FROM vouchers WHERE id = ? FOR UPDATE', array((int) $voucher_id))->row();
		$errors = $voucher ? $this->_validate_coupon_import_rows((int) $voucher_id, $preview['rows'])
			: array('Voucher tidak ditemukan.');
		if (!empty($errors)) {
			$this->db->trans_rollback();
			$preview['errors'] = $errors;
			$this->session->set_userdata('voucher_coupon_import_preview', $preview);
			$this->_coupon_redirect((int) $voucher_id, 'Data berubah setelah preview. Periksa kembali daftar kupon.', FALSE);
		}
		foreach ($preview['rows'] as $row) {
			if (!$this->db->insert('voucher_coupons', array(
				'voucher_id' => (int) $voucher_id,
				'serial_code' => $row['serial_code'],
				'assigned_npk' => $row['assigned_npk'] !== '' ? $row['assigned_npk'] : NULL,
				'status' => 'available',
				'created_at' => date('Y-m-d H:i:s'),
				'created_by' => (string) $this->ion_auth->get_user_id()
			))) {
				$this->db->trans_rollback();
				$this->_coupon_redirect((int) $voucher_id, 'Impor gagal; tidak ada kupon yang disimpan.', FALSE);
			}
		}
		if ($this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->_coupon_redirect((int) $voucher_id, 'Impor gagal; tidak ada kupon yang disimpan.', FALSE);
		}
		$this->session->unset_userdata('voucher_coupon_import_preview');
		$this->_coupon_redirect((int) $voucher_id, count($preview['rows']).' kupon berhasil diimpor.', TRUE);
	}

	public function print_coupons($voucher_id = NULL)
	{
		$this->_require_manager();
		$this->_require_coupon_schema();
		if (!$this->_is_positive_integer($voucher_id)) {
			show_404();
		}
		$voucher = $this->db->get_where('vouchers', array('id' => (int) $voucher_id))->row();
		if (!$voucher) {
			show_404();
		}
		$coupons = $this->db->where('voucher_id', (int) $voucher_id)
			->where('status', 'available')->order_by('id', 'ASC')->get('voucher_coupons')->result();
		$this->load->view('voucher_coupons_print', array('voucher' => $voucher, 'coupons' => $coupons));
	}

	public function save($voucher_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$this->_require_manager();

		$is_edit = $voucher_id !== NULL;
		if ($is_edit && (!$this->_is_positive_integer($voucher_id) ||
			!$this->db->get_where('vouchers', array('id' => (int) $voucher_id))->row())) {
			show_404();
		}

		$code = $this->input->post('kode');
		$name = $this->input->post('nama');
		$type = $this->input->post('tipe');
		$value = $this->input->post('nilai');
		$minimum = $this->input->post('min_belanja');
		$maximum_discount = $this->input->post('max_diskon');
		$quota = $this->input->post('kuota');
		$start_input = $this->input->post('tgl_mulai');
		$expiry_input = $this->input->post('tgl_kadaluarsa');
		$values = array(
			'kode' => is_string($code) ? trim($code) : '',
			'nama' => is_string($name) ? trim($name) : '',
			'tipe' => is_string($type) ? trim($type) : '',
			'nilai' => is_string($value) ? trim($value) : '',
			'min_belanja' => is_string($minimum) ? trim($minimum) : '',
			'max_diskon' => is_string($maximum_discount) ? trim($maximum_discount) : '',
			'kuota' => is_string($quota) ? trim($quota) : '',
			'tgl_mulai' => is_string($start_input) ? trim($start_input) : '',
			'tgl_kadaluarsa' => is_string($expiry_input) ? trim($expiry_input) : '',
		);
		$code = $values['kode'];
		$name = $values['nama'];
		$type = $values['tipe'];
		$value = $values['nilai'];
		$minimum = $values['min_belanja'];
		$maximum_discount = $values['max_diskon'];
		$quota = $values['kuota'];
		$start = $this->_parse_datetime($values['tgl_mulai']);
		$expiry = $this->_parse_datetime($values['tgl_kadaluarsa']);
		$allowed_types = array('diskon_nominal', 'diskon_persen', 'cashback');
		$field_errors = array();
		if ($code === '') {
			$field_errors['kode'] = 'Kode voucher wajib diisi.';
		} elseif (!preg_match('/^[A-Z0-9_.-]{1,32}$/D', strtoupper($code))) {
			$field_errors['kode'] = 'Gunakan maksimal 32 huruf, angka, titik, garis bawah, atau tanda hubung.';
		}
		if ($name === '') {
			$field_errors['nama'] = 'Nama voucher wajib diisi.';
		} elseif (strlen($name) > 128) {
			$field_errors['nama'] = 'Nama voucher maksimal 128 karakter.';
		}
		if (!in_array($type, $allowed_types, TRUE)) {
			$field_errors['tipe'] = 'Pilih jenis voucher yang tersedia.';
		}
		if (!$this->_is_valid_decimal_amount($value)) {
			$field_errors['nilai'] = 'Nilai harus angka lebih dari 0 dengan maksimal 2 angka desimal.';
		} elseif ($type === 'diskon_persen' && (float) $value > 100) {
			$field_errors['nilai'] = 'Diskon persentase tidak boleh lebih dari 100%.';
		}
		if (!$this->_is_non_negative_integer($minimum) || (float) $minimum > 2147483647) {
			$field_errors['min_belanja'] = 'Minimum belanja harus bilangan bulat 0 atau lebih.';
		}
		if ($quota === '' || !$this->_is_non_negative_integer($quota) || (float) $quota > 2147483647) {
			$field_errors['kuota'] = 'Kuota harus bilangan bulat 0 atau lebih; isi 0 untuk tanpa batas.';
		}
		if ($maximum_discount !== '' &&
			(!$this->_is_valid_decimal_amount($maximum_discount) || (float) $maximum_discount <= 0)) {
			$field_errors['max_diskon'] = 'Maksimum diskon harus angka lebih dari 0 dengan maksimal 2 angka desimal, atau kosongkan.';
		} elseif ($maximum_discount !== '' && $type !== 'diskon_persen') {
			$field_errors['max_diskon'] = 'Maksimum diskon hanya digunakan untuk voucher diskon persentase.';
		}
		if (!$start) {
			$field_errors['tgl_mulai'] = 'Tanggal dan waktu mulai wajib diisi dengan format yang valid.';
		}
		if (!$expiry) {
			$field_errors['tgl_kadaluarsa'] = 'Tanggal dan waktu kedaluwarsa wajib diisi dengan format yang valid.';
		} elseif ($start && $start > $expiry) {
			$field_errors['tgl_kadaluarsa'] = 'Waktu kedaluwarsa harus sama dengan atau setelah waktu mulai.';
		}
		if (count($field_errors) > 0) {
			$this->_redirect_validation_error($field_errors, $values, $is_edit ? (int) $voucher_id : 0);
		}

		$code = strtoupper($code);
		$duplicate_query = $this->db->where('kode', $code);
		if ($is_edit) {
			$duplicate_query->where('id !=', (int) $voucher_id);
		}
		if ($duplicate_query->count_all_results('vouchers') > 0) {
			$this->_redirect_validation_error(
				array('kode' => 'Kode voucher sudah digunakan. Pilih kode lain.'),
				$values,
				$is_edit ? (int) $voucher_id : 0
			);
		}

		$record = array(
			'kode' => $code,
			'nama' => $name,
			'tipe' => $type,
			'nilai' => number_format((float) $value, 2, '.', ''),
			'min_belanja' => (int) $minimum,
			'max_diskon' => $type === 'diskon_persen' && $maximum_discount !== ''
				? number_format((float) $maximum_discount, 2, '.', '') : NULL,
			'kuota' => (int) $quota,
			'tgl_mulai' => $start,
			'tgl_kadaluarsa' => $expiry,
		);

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if ($is_edit) {
			$saved = $this->db->where('id', (int) $voucher_id)->update('vouchers', $record);
		} else {
			$record['terpakai'] = 0;
			$record['is_active'] = 1;
			$record['created_at'] = date('Y-m-d H:i:s');
			$saved = $this->db->insert('vouchers', $record);
		}
		$error = $this->db->error();
		$this->db->db_debug = $previous_db_debug;
		if (!$saved) {
			log_message('error', 'Gagal menyimpan voucher: '.$error['code'].' '.$error['message']);
			$this->_redirect_error(
				'Voucher gagal disimpan karena terjadi kesalahan database. Silakan coba lagi.',
				$values,
				$is_edit ? (int) $voucher_id : 0
			);
		}

		$this->session->set_flashdata('voucher_notice', 'Voucher berhasil disimpan.');
		redirect('main/voucher');
	}

	public function toggle($voucher_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST' || !$this->_is_positive_integer($voucher_id)) {
			show_404();
		}
		$this->_require_manager();

		$voucher = $this->db->get_where('vouchers', array('id' => (int) $voucher_id))->row();
		if (!$voucher) {
			show_404();
		}
		$updated = $this->db->where('id', (int) $voucher_id)
			->update('vouchers', array('is_active' => (int) !$voucher->is_active));
		if (!$updated) {
			$error = $this->db->error();
			log_message('error', 'Gagal mengubah status voucher '.$voucher_id.': '.$error['code'].' '.$error['message']);
			$this->_redirect_error('Status voucher gagal diperbarui.');
		}

		$this->session->set_flashdata('voucher_notice', 'Status voucher berhasil diperbarui.');
		redirect('main/voucher');
	}

	private function _coupon_form_token()
	{
		$token = $this->session->userdata('voucher_coupon_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('voucher_coupon_form_token', $token);
		}
		return $token;
	}

	private function _require_coupon_post()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$this->_require_manager();
		$this->_require_coupon_schema();
		$expected = $this->session->userdata('voucher_coupon_form_token');
		$posted = $this->input->post('coupon_form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir kupon tidak valid. Muat ulang halaman.', 403);
		}
	}

	private function _require_coupon_schema()
	{
		if (!$this->db->table_exists('voucher_coupons') ||
			!$this->db->field_exists('voucher_coupon_id', 'voucher_usages')) {
			show_error('Skema kupon belum tersedia. Jalankan application/sql/voucher_coupons.sql.', 500);
		}
	}

	private function _coupon_quota_occupied($voucher_id)
	{
		$serial_count = (int) $this->db->where('voucher_id', (int) $voucher_id)
			->count_all_results('voucher_coupons');
		$direct_usage = $this->db->query("
			SELECT COUNT(*) AS total FROM voucher_usages
			WHERE voucher_id = ? AND voucher_coupon_id IS NULL
		", array((int) $voucher_id))->row();
		return $serial_count + ($direct_usage ? (int) $direct_usage->total : 0);
	}

	private function _coupon_redirect($voucher_id, $message, $success)
	{
		$this->session->set_flashdata($success ? 'voucher_coupon_notice' : 'voucher_coupon_error', $message);
		redirect('main/voucher/coupons/'.(int) $voucher_id);
	}

	private function _validate_coupon_import_rows($voucher_id, $rows)
	{
		$errors = array();
		if (!is_array($rows) || count($rows) < 1 || count($rows) > 1000) {
			return array('File harus memiliki 1 sampai 1000 serial kupon.');
		}
		$seen = array();
		$serials = array();
		foreach ($rows as $row) {
			$line = isset($row['row']) ? (int) $row['row'] : 0;
			$serial = isset($row['serial_code']) ? strtoupper(trim((string) $row['serial_code'])) : '';
			$npk = isset($row['assigned_npk']) ? strtoupper(trim((string) $row['assigned_npk'])) : '';
			if (!preg_match('/^[A-Z0-9_-]{3,32}$/D', $serial)) {
				$errors[] = 'Baris '.$line.': serial kupon harus 3–32 karakter huruf, angka, _ atau -.';
			} elseif (isset($seen[$serial])) {
				$errors[] = 'Baris '.$line.': serial '.$serial.' duplikat di file.';
			} else {
				$seen[$serial] = TRUE;
				$serials[] = $serial;
			}
			if ($npk !== '' && (!preg_match('/^[A-Z0-9_-]{1,32}$/D', $npk))) {
				$errors[] = 'Baris '.$line.': NPK hanya boleh berisi huruf, angka, _ atau -.';
			}
		}
		if (!empty($serials)) {
			$existing = $this->db->select('serial_code')->where_in('serial_code', $serials)
				->get('voucher_coupons')->result();
			foreach ($existing as $coupon) {
				$errors[] = 'Serial '.$coupon->serial_code.' sudah pernah digunakan.';
			}
		}
		$voucher = $this->db->get_where('vouchers', array('id' => (int) $voucher_id))->row();
		$occupied_quota = $this->_coupon_quota_occupied((int) $voucher_id);
		if (!$voucher) {
			$errors[] = 'Voucher master tidak ditemukan.';
		} elseif ((int) $voucher->kuota > 0 && $occupied_quota + count($rows) > (int) $voucher->kuota) {
			$errors[] = 'Jumlah serial kupon melebihi kuota voucher yang tersedia.';
		}
		return $errors;
	}

	private function _validate_voucher_import_rows($rows)
	{
		if (!is_array($rows) || count($rows) < 1 || count($rows) > 10000) {
			return array('File harus memiliki 1 sampai 10000 voucher.');
		}
		$errors = array();
		$seen = array();
		$codes = array();
		foreach ($rows as $row) {
			$line = isset($row['row']) ? (int) $row['row'] : 0;
			$name = isset($row['name']) ? trim((string) $row['name']) : '';
			$npk = isset($row['assigned_npk']) ? strtoupper(trim((string) $row['assigned_npk'])) : '';
			$amount = isset($row['amount']) ? trim((string) $row['amount']) : '';
			$expiry = isset($row['expiry']) ? $row['expiry'] : FALSE;
			$serial = $npk.'-'.$amount;
			if ($name === '' || strlen($name) > 128) {
				$errors[] = 'Baris '.$line.': nama voucher wajib diisi dan maksimal 128 karakter.';
			}
			if ($npk === '' || strlen($npk) > 32 || !preg_match('/^[A-Z0-9_-]+$/D', $npk)) {
				$errors[] = 'Baris '.$line.': NPK wajib diisi dan hanya boleh berisi huruf, angka, _ atau -.';
			}
			if (!preg_match('/^[1-9][0-9]{0,12}$/D', $amount)) {
				$errors[] = 'Baris '.$line.': nominal harus berupa rupiah bilangan bulat lebih dari 0.';
			}
			if (strlen($serial) > 32) {
				$errors[] = 'Baris '.$line.': kode NPK-nominal melebihi 32 karakter.';
			}
			if (!is_string($expiry) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $expiry) ||
				$expiry < date('Y-m-d H:i:s')) {
				$errors[] = 'Baris '.$line.': tanggal kedaluwarsa tidak valid atau sudah lewat.';
			}
			if (isset($seen[$serial])) {
				$errors[] = 'Baris '.$line.': kode '.$serial.' duplikat di file.';
			} else {
				$seen[$serial] = TRUE;
				$codes[] = $serial;
			}
		}
		if (!empty($codes)) {
			$existing_vouchers = $this->db->where_in('kode', $codes)->get('vouchers')->result();
			foreach ($existing_vouchers as $voucher) {
				$errors[] = 'Kode voucher '.$voucher->kode.' sudah digunakan.';
			}
			$existing_coupons = $this->db->where_in('serial_code', $codes)->get('voucher_coupons')->result();
			foreach ($existing_coupons as $coupon) {
				$errors[] = 'Serial kupon '.$coupon->serial_code.' sudah digunakan.';
			}
		}
		return $errors;
	}

	private function _parse_import_expiry($value)
	{
		if (is_numeric($value) && (float) $value > 0) {
			$serial = (float) $value;
			$timestamp = PHPExcel_Shared_Date::ExcelToPHP($serial);
			$date = gmdate('Y-m-d', $timestamp);
			if (floor($serial) === $serial) {
				return $date.' 23:59:59';
			}
			return gmdate('Y-m-d H:i:s', $timestamp);
		}
		if (!is_string($value) || trim($value) === '') {
			return FALSE;
		}
		$value = trim($value);
		$formats = array('!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d', '!d/m/Y');
		foreach ($formats as $format) {
			$date = DateTime::createFromFormat($format, $value);
			$parse_errors = DateTime::getLastErrors();
			if ($date && (!$parse_errors || (!$parse_errors['warning_count'] && !$parse_errors['error_count']))) {
				if ($format === '!Y-m-d' || $format === '!d/m/Y') {
					$date->setTime(23, 59, 59);
				}
				return $date->format('Y-m-d H:i:s');
			}
		}
		return FALSE;
	}

	private function _can_manage()
	{
		return $this->ion_auth->in_group(array('admin', 'management'));
	}

	private function _voucher_usage_query()
	{
		$query = $this->db
			->from('voucher_usages AS voucher_use')
			->join('vouchers AS voucher', 'voucher.id = voucher_use.voucher_id');
		if ($this->coupon_schema_available) {
			$query->join('voucher_coupons AS voucher_coupon', 'voucher_coupon.id = voucher_use.voucher_coupon_id', 'left');
		}
		return $query;
	}

	private function _non_negative_integer($value, $default)
	{
		if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
			return $default;
		}
		return min((int) $value, 2147483647);
	}

	private function _require_manager()
	{
		if (!$this->_can_manage()) {
			show_error('Hanya admin atau management yang dapat mengelola voucher.', 403);
		}
	}

	private function _parse_datetime($value)
	{
		if (!is_string($value)) {
			return FALSE;
		}
		$date = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
		$errors = DateTime::getLastErrors();
		if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) ||
			$date->format('Y-m-d\TH:i') !== $value) {
			return FALSE;
		}
		return $date->format('Y-m-d H:i:s');
	}

	private function _is_positive_integer($value)
	{
		return (is_string($value) || is_numeric($value)) &&
			preg_match('/^[1-9][0-9]*$/', (string) $value);
	}

	private function _is_non_negative_integer($value)
	{
		return is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value);
	}

	private function _is_valid_decimal_amount($value)
	{
		if (!is_string($value) ||
			!preg_match('/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/D', $value)) {
			return FALSE;
		}
		return (float) $value > 0;
	}

	private function _redirect_validation_error($errors, $values, $form_id)
	{
		$this->session->set_flashdata('voucher_validation_errors', $errors);
		$this->session->set_flashdata('voucher_form_values', $values);
		$this->session->set_flashdata('voucher_form_id', (int) $form_id);
		redirect('main/voucher');
	}

	private function _redirect_error($message, $values = NULL, $form_id = NULL)
	{
		$this->session->set_flashdata('voucher_error', $message);
		if (is_array($values)) {
			$this->session->set_flashdata('voucher_form_values', $values);
			$this->session->set_flashdata('voucher_form_id', (int) $form_id);
		}
		redirect('main/voucher');
	}
}
