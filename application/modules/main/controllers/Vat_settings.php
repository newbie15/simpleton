<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Vat_settings extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper(array('url', 'form', 'string'));
		$this->load->library(array('session', 'ion_auth', 'page_access', 'sales_tax'));

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('accounting')) {
			show_error('Hanya admin atau pengguna accounting yang dapat mengubah tarif PPN.', 403);
		}
	}

	public function index()
	{
		$rate = $this->sales_tax->current_rate();
		if ($rate === FALSE) {
			show_error('Pengaturan PPN belum tersedia atau nilainya tidak valid. Jalankan application/sql/sales_tax_inclusive.sql.', 500);
		}
		$data = array(
			'rate' => $rate,
			'updated_at' => NULL,
			'updated_by' => NULL,
			'form_token' => $this->_form_token(),
			'notice' => $this->session->flashdata('vat_settings_notice'),
			'error' => $this->session->flashdata('vat_settings_error'),
			'judul_besar' => 'Pengaturan PPN Penjualan',
			'judul_kecil' => 'Tarif global untuk transaksi POS baru',
			'm_vat_settings' => TRUE
		);
		$setting = $this->db->get_where('sales_tax_settings', array('id' => 1))->row();
		if ($setting) {
			$data['updated_at'] = $setting->updated_at;
			$data['updated_by'] = $setting->updated_by;
		}
		$data['output'] = $this->load->view('vat_settings', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function save()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$this->_check_form_token();
		$rate_input = trim((string) $this->input->post('rate_percent', TRUE));
		if (!preg_match('/^(?:[0-9]{1,3})(?:\.[0-9]{1,2})?$/D', $rate_input) ||
			(float) $rate_input <= 0 || (float) $rate_input > 100) {
			$this->_redirect_error('Tarif PPN harus lebih dari 0% dan maksimal 100%.');
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Database tidak dapat memulai perubahan tarif PPN.');
		}
		$setting_query = $this->db->query('SELECT id FROM sales_tax_settings WHERE id = 1 FOR UPDATE');
		if ($setting_query === FALSE || !$setting_query->row()) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Pengaturan PPN tidak dapat dibaca. Jalankan application/sql/sales_tax_inclusive.sql dan coba lagi.');
		}
		$updated = $this->db->where('id', 1)->update('sales_tax_settings', array(
			'rate_percent' => round((float) $rate_input, 2),
			'updated_by' => (string) $this->ion_auth->get_user_id(),
			'updated_at' => date('Y-m-d H:i:s')
		));
		if (!$updated || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal memperbarui tarif PPN: '.$error['code'].' '.$error['message']);
			$this->_redirect_error('Tarif PPN gagal disimpan.');
		}
		$this->db->db_debug = $previous_db_debug;
		$this->session->set_flashdata('vat_settings_notice', 'Tarif PPN transaksi baru berhasil diperbarui menjadi '.$rate_input.'%.');
		redirect('main/vat_settings');
	}

	private function _form_token()
	{
		$token = $this->session->userdata('vat_settings_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('vat_settings_form_token', $token);
		}
		return $token;
	}

	private function _check_form_token()
	{
		$expected = $this->session->userdata('vat_settings_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}
	}

	private function _redirect_error($message)
	{
		$this->session->set_flashdata('vat_settings_error', $message);
		redirect('main/vat_settings');
	}
}
