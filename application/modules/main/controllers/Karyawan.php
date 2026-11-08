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
		if (!$this->db->field_exists('nama_bank', 'karyawan') ||
			!$this->db->field_exists('nomor_rekening', 'karyawan')) {
			show_error('Kolom data payroll belum tersedia. Jalankan application/sql/karyawan_employment_status.sql.', 500);
		}
		$crud = new grocery_CRUD();
		$crud->set_table('karyawan');
		$crud->columns('npk', 'nama', 'bagian', 'limit_belanja', 'join_date', 'is_active', 'resign_date', 'nama_bank', 'nomor_rekening');
		$crud->display_as('is_active', 'Status Aktif');
		$crud->display_as('resign_date', 'Tanggal Resign');
		$crud->display_as('nama_bank', 'Nama Bank Payroll');
		$crud->display_as('nomor_rekening', 'Nomor Rekening Payroll');
		$crud->field_type('is_active', 'dropdown', array(1 => 'Aktif', 0 => 'Resign'));
		$data = $crud->render();
		$data->judul_besar = 'Karyawan';
		$data->judul_kecil = 'Mengelola daftar karyawan';
		$data->m_karyawan = TRUE;
		$this->load->view('welcome_message', $data);
	}
}