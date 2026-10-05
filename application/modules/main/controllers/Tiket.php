<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tiket extends CI_Controller {

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
		$data['css_file'] = [
			// base_url("assets/datatables/css/jquery.dataTables.min.css"),
			base_url("assets/jexcel/css/jquery.jexcel.css"),
			base_url("assets/jexcel/css/jquery.jcalendar.css"),
			base_url("assets/easyautocomplete/easy-autocomplete.min.css"),			
		];

		$data['js_file'] = [
			// base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
			// base_url("assets/datatables/js/jquery.dataTables.min.js"),
			base_url("assets/jexcel/js/jquery.jexcel.js"),
			base_url("assets/jexcel/js/jquery.jcalendar.js"),
			// base_url("assets/jexcel/js/excel-formula.min.js"),			
			base_url("assets/jexcel/js/numeral.min.js"),			
            base_url("assets/easyautocomplete/jquery.easy-autocomplete.min.js"),	
			base_url("assets/kasir/config.js"),			
			base_url("assets/tiket/tiket.js"),			
		];

		$data['output'] = '
        <section class="content-header">
            <h1>Tiket Pesawat Terbang <small>buat,lihat dan cetak tiket</small></h1>
        </section>
        <br>
        <table style="text-align: left; width: 50%;" border="0" cellpadding="1" cellspacing="1">
            <tbody>
            <tr>
                <td style="vertical-align: middle;">Kode booking</td>
                <td style="vertical-align: top;"><input type="text" name="kodebooking" id="kodebooking" placeholder="kodebooking"/></td>
            </tr>
			</tbody>
        </table>
        <br>
        <div id="my-spreadsheet"></div>
        <br>
        <div id="my-spreadsheet2"></div>

        <h4>Daftar Penumpang</h4>
        <div id="daftar-penumpang"></div>
        <br>
        <button id="simpan" class="btn btn-success">Simpan</button>
        <button id="cetak" class="btn btn-info">[F9] Cetak Tiket</button>
        <button id="receipt" class="btn btn-danger">Cetak Receipt</button>

				
		';
		$data['m_tiket'] = TRUE;

		$this->load->view('welcome_message', $data);
		// $data = "";
		// $this->load->view('kamera', $data);
	}

    public function simpan_tiket(){
		$kodebooking = $_REQUEST['kode_booking'];
		// $station = $_REQUEST['station'];

		$this->db->query("DELETE FROM `penjualan_tiket_pesawat` where kode_booking = '$kodebooking' ");
		$data_json1 = $_REQUEST['data_1'];
		$data_json2 = $_REQUEST['data_2'];
        $data1 = json_decode($data_json1);
        $data2 = json_decode($data_json2);

		// $data1[0][1] = str_replace(" 00:00:00","",$data1[0][1]);
		$data1[0][3] = str_replace(" 00:00:00","",$data1[0][3]);

        print_r($data1);
		print_r($data2);
		
		// foreach ($data as $key => $value) {
		// 	// $this->db->insert
			$data = array(
				'kode_booking' => $data1[0][0],
				'tanggal_keberangkatan' => $data1[0][1]." ".$data1[0][2].":00",
				'tanggal_kedatangan' => $data1[0][3]." ".$data1[0][4].":00",
				// 'nama_kontak' => $data1[0][3],
				// 'no_telp' => $data1[0][4],
				'flight_number' => $data1[0][5],
				'asal' => $data2[0][0],
				'tujuan' => $data2[0][1],
				'maskapai' => $data2[0][2],
				'fasilitas' => $data2[0][3],
			);
			print_r($data);
			$this->db->insert('penjualan_tiket_pesawat', $data);
		// }
    }
    public function simpan_penumpang(){
		$kodebooking = $_REQUEST['kode_booking'];
		// $station = $_REQUEST['station'];

		$this->db->query("DELETE FROM `penumpang_tiket_pesawat` where kode_booking = '$kodebooking' ");
		$data_json = $_REQUEST['data_penumpang'];
        $data = json_decode($data_json);

		foreach ($data as $key => $value) {
		// 	// $this->db->insert
			$data = array(
				'kode_booking' => $kodebooking,
				'nama' => $value[0],
				'no_ktp' => $value[1],
				'no_telepon' => $value[2],
				'harga' => $value[3],
			);
			print_r($data);
			$this->db->insert('penumpang_tiket_pesawat', $data);
		}
    }

    public function load_penumpang(){
		@$kodebooking = $_REQUEST['kode_booking'];

		@$query = $this->db->query("SELECT `nama`,`no_ktp`,`no_telepon`,`harga` 
        FROM `penumpang_tiket_pesawat` WHERE 
        kode_booking = '$kodebooking'
        ");

		$i = 0;
		$d = [];
		foreach ($query->result() as $row)
		{
			$d[$i][0] = $row->nama;
			$d[$i][1] = $row->no_ktp;
			$d[$i][2] = $row->no_telepon;
			$d[$i++][3] = $row->harga;
		}
		echo json_encode($d);

    }

	public function load_tiket(){
        // echo $npk = $this->uri->segment(1);
		@$kodebooking = $_REQUEST['kode_booking'];

		@$query = $this->db->query("SELECT `kode_booking`,`tanggal_keberangkatan`,`tanggal_kedatangan`,`flight_number`,`asal`,`tujuan`,`maskapai`,`fasilitas` 
        FROM `penjualan_tiket_pesawat` WHERE 
        kode_booking = '$kodebooking'
        ");

		$i = 0;
		$d = [];
		foreach ($query->result() as $row)
		{
            $t1 = explode(" ",$row->tanggal_keberangkatan);
            $t2 = explode(" ",$row->tanggal_kedatangan);
			$d[$i][0] = $row->kode_booking;
			$d[$i][1] = $t1[0];
			$d[$i][2] = substr($t1[1],0,5);
			$d[$i][3] = $t2[0];
			$d[$i][4] = substr($t2[1],0,5);
			$d[$i][5] = $row->flight_number;
			$d[$i][6] = $row->asal;
			$d[$i][7] = $row->tujuan;
			$d[$i][8] = $row->maskapai;
			$d[$i++][9] = $row->fasilitas;
		}
		echo json_encode($d);

	}
	
	public function cetak(){
        $kodebooking = $this->uri->segment(4);

		@$query = $this->db->query("SELECT `kode_booking`,`tanggal_keberangkatan`,`tanggal_kedatangan`,`flight_number`,`asal`,`tujuan`,`maskapai`,`fasilitas` 
        FROM `penjualan_tiket_pesawat` WHERE 
        kode_booking = '$kodebooking'
        ");

		$i = 0;
		$d = [];
		foreach ($query->result() as $row)
		{
            $t1 = explode(" ",$row->tanggal_keberangkatan);
            $t2 = explode(" ",$row->tanggal_kedatangan);
			$d[$i][0] = $row->kode_booking;
			$d[$i][1] = $t1[0];
			$d[$i][2] = substr($t1[1],0,5);
			$d[$i][3] = $t2[0];
			$d[$i][4] = substr($t2[1],0,5);
			$d[$i][5] = $row->flight_number;
			$d[$i][6] = $row->asal;
			$d[$i][7] = $row->tujuan;
			$d[$i][8] = $row->maskapai;
			$d[$i++][9] = $row->fasilitas;
		}

		//Our YYYY-MM-DD date string.
		$date = $d[0][1];
		$date1 = $d[0][3];

		//Convert the date string into a unix timestamp.
		$unixTimestamp = strtotime($date);
		$unixTimestamp1 = strtotime($date1);

		//Get the day of the week using PHP's date function.
		$dayOfWeek = date("N", $unixTimestamp);
		$dayOfWeek1 = date("N", $unixTimestamp1);

		//Print out the day that our date fell on.
		$hari_indonesia = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
		// echo $date . ' fell on a ' . $dayOfWeek;
		$data["hari"] = $hari_indonesia[$dayOfWeek-1];
		$data["hari1"] = $hari_indonesia[$dayOfWeek1-1];




		@$query = $this->db->query("SELECT `nama`,`no_ktp`,`no_telepon`,`harga` 
        FROM `penumpang_tiket_pesawat` WHERE 
        kode_booking = '$kodebooking'
        ");

		$i = 0;
		$p = [];
		foreach ($query->result() as $row)
		{
			$p[$i][0] = $row->nama;
			$p[$i][1] = $row->no_ktp;
			$p[$i][2] = $row->no_telepon;
			$p[$i++][3] = $row->harga;
		}
		$data['pesawat'] = $d;
		$data['penumpang'] = $p;

		$this->load->view('cetak_tiket_pesawat', $data);

	}

	public function receipt(){
        $kodebooking = $this->uri->segment(4);

		@$query = $this->db->query("SELECT `kode_booking`,`tanggal_keberangkatan`,`tanggal_kedatangan`,`flight_number`,`asal`,`tujuan`,`maskapai`,`fasilitas` 
        FROM `penjualan_tiket_pesawat` WHERE 
        kode_booking = '$kodebooking'
        ");

		$i = 0;
		$d = [];
		foreach ($query->result() as $row)
		{
            $t1 = explode(" ",$row->tanggal_keberangkatan);
            $t2 = explode(" ",$row->tanggal_kedatangan);
			$d[$i][0] = $row->kode_booking;
			$d[$i][1] = $t1[0];
			$d[$i][2] = substr($t1[1],0,5);
			$d[$i][3] = $t2[0];
			$d[$i][4] = substr($t2[1],0,5);
			$d[$i][5] = $row->flight_number;
			$d[$i][6] = $row->asal;
			$d[$i][7] = $row->tujuan;
			$d[$i][8] = $row->maskapai;
			$d[$i++][9] = $row->fasilitas;
		}

		//Our YYYY-MM-DD date string.
		$date = $d[0][1];
		$date1 = $d[0][3];

		//Convert the date string into a unix timestamp.
		$unixTimestamp = strtotime($date);
		$unixTimestamp1 = strtotime($date1);

		//Get the day of the week using PHP's date function.
		$dayOfWeek = date("N", $unixTimestamp);
		$dayOfWeek1 = date("N", $unixTimestamp1);

		//Print out the day that our date fell on.
		$hari_indonesia = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
		// echo $date . ' fell on a ' . $dayOfWeek;
		$data["hari"] = $hari_indonesia[$dayOfWeek-1];
		$data["hari1"] = $hari_indonesia[$dayOfWeek1-1];




		@$query = $this->db->query("SELECT `nama`,`no_ktp`,`no_telepon`,`harga` 
        FROM `penumpang_tiket_pesawat` WHERE 
        kode_booking = '$kodebooking'
        ");

		$i = 0;
		$p = [];
		foreach ($query->result() as $row)
		{
			$p[$i][0] = $row->nama;
			$p[$i][1] = $row->no_ktp;
			$p[$i][2] = $row->no_telepon;
			$p[$i++][3] = $row->harga;
		}
		$data['pesawat'] = $d;
		$data['penumpang'] = $p;

		$this->load->view('cetak_receipt_pesawat', $data);

	}	
}
