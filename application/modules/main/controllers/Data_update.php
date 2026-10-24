<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Data_update extends CI_Controller
{
	public function index()
	{
		$this->load->helper('url');
		$data['js_file'] = array(base_url('assets/kasir/update.js'));
		$data['output'] = '
		<h3>Update Data Stok</h3>
		<input type="file" name="stok" id="stok">
		<div id="dropBox"></div>
		<br>
		<img src="'.base_url('assets/misc/samplestok.png').'">
		<br>
		<h3>Update Data Karyawan</h3>
		<input type="file" name="karyawan" id="karyawan">
		<div id="dropBox1"></div>
		<br>
		<img src="'.base_url('assets/misc/samplekaryawan.png').'">
		<br>';
		$data['m_update'] = TRUE;
		$this->load->view('welcome_message', $data);
	}
}