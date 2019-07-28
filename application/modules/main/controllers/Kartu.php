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
