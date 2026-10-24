<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Stok extends CI_Controller
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
		$crud->set_table('stok');
		$data = $crud->render();
		$data->judul_besar = 'Stok';
		$data->judul_kecil = 'Lihat dan kelola stok barang';
		$data->m_stok = TRUE;
		$this->load->view('welcome_message', $data);
	}
}