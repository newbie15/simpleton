<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rekap_penjualan extends CI_Controller
{
	public function index()
	{
		$this->load->helper('url');
		$data['css_file'] = array(
			base_url('assets/jexcel/css/jexcel.css'),
			base_url('assets/jexcel/css/jsuites.css'),
			base_url('assets/easyautocomplete/easy-autocomplete.min.css')
		);
		$data['js_file'] = array(
			base_url('assets/easyautocomplete/jquery.easy-autocomplete.min.js'),
			base_url('assets/kasir/rekap.js?v=20261024-return-net-billing'),
			base_url('assets/jexcel/js/jexcel.js'),
			base_url('assets/jexcel/js/jsuites.js'),
			base_url('assets/papaparse/papaparse.min.js')
		);
		$data['ajax_base'] = site_url('main/ajax');
		$data['m_rekap'] = TRUE;
		$data['output'] = $this->load->view('rekap_penjualan', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}
}