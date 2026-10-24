<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Examples extends CI_Controller
{
	public function index()
	{
		redirect('main/welcome', 'refresh');
	}

	public function offices()
	{
		redirect('main/stok', 'refresh');
	}

	public function stok()
	{
		redirect('main/stok', 'refresh');
	}

	public function update_data()
	{
		redirect('main/data_update', 'refresh');
	}

	public function karyawan()
	{
		redirect('main/karyawan', 'refresh');
	}

	public function user()
	{
		redirect('auth/index', 'refresh');
	}

	public function kartu()
	{
		redirect('main/kartu/batch', 'refresh');
	}

	public function kamera()
	{
		redirect('main/kamera', 'refresh');
	}

	public function penjualan()
	{
		redirect('main/penjualan', 'refresh');
	}

	public function rekap_penjualan()
	{
		redirect('main/rekap_penjualan', 'refresh');
	}
}