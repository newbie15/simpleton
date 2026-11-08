<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Starting_setup extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library(array('ion_auth', 'page_access'));

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
			return;
		}
		if (!$this->page_access->can_access('accounting')) {
			show_error('Anda tidak memiliki akses ke panduan setup awal.', 403);
		}
	}

	public function index()
	{
		$data = array(
			'judul_besar' => 'Setup Awal & Migrasi',
			'judul_kecil' => 'Panduan persiapan data sebelum aplikasi mulai digunakan',
			'm_starting_setup' => TRUE
		);
		$data['output'] = $this->load->view('starting_setup', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}
}
