<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Karyawan extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper('url');
		$this->load->library('grocery_CRUD');
	}

	public function index()
	{
		$crud = new grocery_CRUD();
		$crud->set_table('karyawan');
		$data = $crud->render();
		$data->judul_besar = 'Karyawan';
		$data->judul_kecil = 'Mengelola daftar karyawan';
		$data->m_karyawan = TRUE;
		$this->load->view('welcome_message', $data);
	}
}