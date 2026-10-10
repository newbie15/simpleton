<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Voucher extends CI_Controller
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
		if (!$this->page_access->can_access('vouchers')) {
			show_error('Anda tidak memiliki akses ke master voucher.', 403);
		}
	}

	public function index()
	{
		$data['vouchers'] = $this->db->order_by('id', 'DESC')->get('vouchers')->result();
		$data['can_manage'] = $this->_can_manage();
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
			base_url('assets/voucher/voucher_history.js'),
			base_url('assets/voucher/voucher_form.js?v=20261010-field-validation')
		);
		$data['output'] = $this->load->view('vouchers', $data, TRUE);
		$this->load->view('welcome_message', $data);
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

		$total = $this->_voucher_usage_query()->count_all_results();
		$filtered_query = $this->_voucher_usage_query();
		if ($search !== '') {
			$filtered_query->group_start()
				->like('voucher.kode', $search)
				->or_like('voucher.nama', $search)
				->or_like('voucher_use.nota', $search)
				->or_like('voucher_use.npk_anggota', $search)
				->or_like('voucher_use.used_at', $search)
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
			5 => 'voucher_use.nominal_diskon'
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

		$rows = $this->_voucher_usage_query()
			->select('voucher_use.used_at, voucher.kode, voucher.nama, voucher_use.nota,
				voucher_use.npk_anggota, voucher_use.nominal_diskon')
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

	private function _can_manage()
	{
		return $this->ion_auth->in_group(array('admin', 'management'));
	}

	private function _voucher_usage_query()
	{
		return $this->db
			->from('voucher_usages AS voucher_use')
			->join('vouchers AS voucher', 'voucher.id = voucher_use.voucher_id');
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
