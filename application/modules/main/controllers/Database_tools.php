<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Database_tools extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->helper(array('url', 'string'));
		$this->load->library(array('session', 'ion_auth'));
		if (!$this->ion_auth->logged_in()) { redirect('auth/login', 'refresh'); return; }
		if (!$this->ion_auth->is_admin()) { show_error('Hanya administrator dapat mengelola backup dan restore.', 403); }
		$this->load->library('database_backup');
	}

	public function index()
	{
		$token = $this->session->userdata('database_tools_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('database_tools_token', $token);
		}
		$data = array('records' => array(), 'form_token' => $token, 'database' => $this->db->database,
			'error' => $this->session->flashdata('database_tools_error'),
			'notice' => $this->session->flashdata('database_tools_notice'),
			'judul_besar' => 'Backup & Restore Database', 'judul_kecil' => 'Khusus administrator',
			'm_database_tools' => TRUE);
		try { $data['records'] = $this->database_backup->records(); } catch (RuntimeException $exception) {
			log_message('error', $exception->getMessage());
			$data['error'] = $exception->getMessage();
		}
		$data['output'] = $this->load->view('database_tools', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	private function require_post()
	{
		if ($this->input->method(TRUE) !== 'POST') { show_error('Metode tidak diizinkan.', 405); }
		$token = $this->session->userdata('database_tools_token');
		$posted = $this->input->post('form_token');
		if (!is_string($token) || $token === '' || !is_string($posted) || !hash_equals($token, $posted)) {
			show_error('Sesi formulir tidak valid.', 403);
		}
	}

	public function backup()
	{
		$this->require_post();
		if ($this->input->post('maintenance_confirmed') !== '1') { show_error('Hentikan transaksi pengguna dahulu.', 400); }
		try {
			$row = $this->database_backup->backup($this->ion_auth->get_user_id());
			$this->session->set_flashdata('database_tools_notice', 'Backup berhasil: '.$row['id']);
		} catch (RuntimeException $exception) { $this->failure($exception); }
		redirect('main/database_tools');
	}

	public function download()
	{
		$this->require_post();
		try {
			$id = $this->input->post('backup_id');
			$this->database_backup->record($id);
			$path = $this->database_backup->path($id);
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.$id.'.sql"');
			header('Cache-Control: no-store, private');
			header('Content-Length: '.filesize($path));
			if (readfile($path) === FALSE) { log_message('error', 'Download backup gagal: '.$id); }
			exit;
		} catch (RuntimeException $exception) { $this->failure($exception); redirect('main/database_tools'); }
	}

	public function restore()
	{
		$this->require_post();
		$password = $this->input->post('admin_password');
		if ($this->input->post('maintenance_confirmed') !== '1' ||
			$this->input->post('database_confirmation') !== $this->db->database) {
			show_error('Konfirmasi maintenance dan nama database harus sesuai.', 400);
		}
		if (!is_string($password) || $password === '' ||
			!$this->ion_auth->hash_password_db($this->ion_auth->get_user_id(), $password)) {
			show_error('Password administrator tidak valid.', 403);
		}
		try {
			$safety = $this->database_backup->restore($this->input->post('backup_id'), $this->ion_auth->get_user_id());
		} catch (RuntimeException $exception) {
			$this->failure($exception);
			redirect('main/database_tools');
			return;
		}
		$this->ion_auth->logout();
		$this->session->set_flashdata('message', 'Restore selesai. Login ulang. Backup pengaman: '.$safety['id']);
		redirect('auth/login');
	}

	private function failure($exception)
	{
		log_message('error', 'Database tools: '.$exception->getMessage());
		$this->session->set_flashdata('database_tools_error', $exception->getMessage());
	}
}
