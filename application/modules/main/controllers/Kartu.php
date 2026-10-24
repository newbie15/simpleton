<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Kartu extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
		$data = "";
		$this->load->view('kartu', $data);
	}

	public function batch()
	{
		$this->load->helper('url');
		$data['css_file'] = array(
			base_url('assets/datatables/css/jquery.dataTables.min.css'),
			base_url('assets/jexcel/css/jquery.jexcel.css'),
			base_url('assets/jexcel/css/jquery.jcalendar.css'),
			base_url('assets/easyautocomplete/easy-autocomplete.min.css')
		);
		$data['js_file'] = array(
			base_url('assets/datatables/js/jquery.dataTables.min.js'),
			base_url('assets/jexcel/js/jquery.jexcel.js'),
			base_url('assets/jexcel/js/jquery.jcalendar.js'),
			base_url('assets/jexcel/js/excel-formula.min.js'),
			base_url('assets/jexcel/js/numeral.min.js'),
			base_url('assets/easyautocomplete/jquery.easy-autocomplete.min.js'),
			base_url('assets/kartu/kartu.js')
		);
		$data['m_kartu'] = TRUE;
		$data['output'] = '
		<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4">
			<h3>Kartu Anggota</h3>
			<div class="form-group"><label for="npk">NPK</label><input class="form-control" type="text" name="npk" id="npk" placeholder="NPK"></div>
			<div style="height:325px;width:100%;overflow:auto;border:1px solid #666;background-color:#ccc;padding:8px;">
				<div id="my"></div>
			</div>
			<br><button id="checkout" class="btn btn-info">Cetak [ F9 ]</button>
		</main>';
		$this->load->view('welcome_message', $data);
	}

	public function cetak()
	{
		$npk = $this->uri->segment(4, 0);
		// echo $npk;
		
		$query = $this->db->query("SELECT nama,bagian FROM karyawan where npk = '$npk'");
		// $query = $this->db->query("SELECT * FROM karyawan");
		// $nama="";
		// $bagian="";

		$data['nama'] = "";
		$data['bagian'] = "";

		foreach ($query->result() as $row)
		{	
			$data['nama'] = $row->nama;
			$data['bagian'] = $row->bagian;
		}
		$data['npk'] = $npk;

		// print_r($data);

		$this->load->view('kartu', $data);
	}
}
