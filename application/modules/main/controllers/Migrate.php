<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('url', 'string'));
		$this->load->library(array('session', 'ion_auth'));
		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
			return;
		}
		if (!$this->ion_auth->is_admin()) {
			show_error('Hanya administrator dapat menjalankan migrasi database.', 403);
		}
		$this->load->library('schema_migrator');
	}

	public function index()
	{
		$token = $this->session->userdata('schema_migration_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('schema_migration_token', $token);
		}
		$data = array(
			'rows' => array(), 'form_token' => $token,
			'error' => $this->session->flashdata('migration_error'),
			'notice' => $this->session->flashdata('migration_notice'),
			'judul_besar' => 'Migrasi Database',
			'judul_kecil' => 'Jalankan pembaruan skema dari browser',
			'm_migrate' => TRUE
		);
		try {
			$data['rows'] = $this->schema_migrator->status();
		} catch (RuntimeException $exception) {
			log_message('error', 'Gagal membaca status migrasi: '.$exception->getMessage());
			$data['error'] = $exception->getMessage();
		}
		$data['output'] = $this->load->view('migrate', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function run()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}
		$expected = $this->session->userdata('schema_migration_token');
		$posted = $this->input->post('form_token');
		if (!is_string($expected) || $expected === '' || !is_string($posted) ||
			!hash_equals($expected, $posted)) {
			show_error('Sesi formulir tidak valid. Muat ulang halaman.', 403);
		}
		if ($this->input->post('backup_confirmed') !== '1') {
			show_error('Konfirmasikan backup dan penghentian transaksi sebelum migrasi.', 400);
		}
		try {
			$message = $this->schema_migrator->run_next($this->ion_auth->get_user_id());
			$this->session->set_flashdata('migration_notice', $message);
		} catch (RuntimeException $exception) {
			log_message('error', 'Migrasi database gagal: '.$exception->getMessage());
			$this->session->set_flashdata('migration_error', $exception->getMessage());
		}
		redirect('main/migrate');
	}
}
