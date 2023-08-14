<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Kasir extends CI_Controller {

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
		$data['judul_besar'] = 'Simpleton';
		$data['judul_kecil'] = 'Version 1.0.0';
		$data['output'] = '<div class="content body">
							<section id="introduction">
							  <h2 class="page-header">Intro</h2>
							  <p class="lead">
							    <b>Simpleton</b> dibuat untuk memudahkan kamu dalam membuat program PHP yang selalu membutuhkan Autentikasi dan operasi CRUD (Create, Read, Update & Delete).
							    Daripada membuat autentikasi dan CRUD berulang-ulang, lebih baik memakai <b>Simpleton</b> Saja :D
							    Skeleton Codeigniter ini menggunakan template <a href = "https://almsaeedstudio.com/preview">AdminLTE</a>, salah satu template gratis terpopuler saat ini. <b>Simpleton</b> cocok untuk kamu yang familiar dengan :<br><br>
							    1. <a href = "http://www.codeigniter.com/download">Codeigniter</a><br>
							    2. <a href = "http://benedmunds.com/ion_auth/">Ion Auth</a><br>
							    3. <a href = "http//grocerycrud.com">GroceryCRUD</a><br><br>
							    Punya saran atau ingin diskusi tentang <b>Simpleton</b> ? <a href = "https://www.facebook.com/groups/924598607617619">Disini tempatnya.</a><br>
							    Suka dengan <b>Simpleton</b> ? Silahkan like fanpagenya : <a href = "https://www.facebook.com/heruprambadicom-1650504335226276">Disini tempatnya.</a><br>
							  </p>
							</section><!-- /#introduction -->
							<!-- ============================================================= -->

							<section id="download">
							  <div class="row">
							    <div class="col-sm-6">
							      <div class="box box-primary">
							        <div class="box-header with-border">
							          <h3 class="box-title">Punya saran atau ingin diskusi tentang <b>Simpleton</b> ?</h3>
							          <span class="label label-primary pull-right"><i class="fa fa-html5"></i></span>
							        </div><!-- /.box-header -->
							        <div class="box-body">
							          <p>Silahkan beri masukan, atau request fitur tambahan di group dibawah ini.</p>
							          <a class="btn btn-primary" href="https://www.facebook.com/groups/924598607617619" target = "_blank">Group Simpleton</a>
							        </div><!-- /.box-body -->
							      </div><!-- /.box -->
							    </div><!-- /.col -->
							    <div class="col-sm-6">
							      <div class="box box-danger">
							        <div class="box-header with-border">
							          <h3 class="box-title">Suka dengan <b>Simpleton</b> ?</h3>
							          <span class="label label-danger pull-right"><i class="fa fa-database"></i></span>
							        </div><!-- /.box-header -->
							        <div class="box-body">
							          <p>Silahkan like fanpagenya.</p>
							          <a class="btn btn-danger" href = "https://www.facebook.com/heruprambadicom-1650504335226276" target = "_blank">Disini tempatnya.</a>
							        </div><!-- /.box-body -->
							      </div><!-- /.box -->
							    </div><!-- /.col -->
							  </div><!-- /.row -->';
		$this->load->view('welcome_message', $data);
	}

	public function checkout(){
		$pajak_active = false;

		if($pajak_active){
			$id_user = $_REQUEST['id_user'];
			$id_karyawan = $_REQUEST['id_karyawan'];
			$tgl = date('Y-m-d H:i:s');
			$nama = $_REQUEST['nama'];
			$harga = $_REQUEST['harga'];
			$jumlah = $_REQUEST['jumlah'];
			$kode = $_REQUEST['kode'];
			$kurangstok = $_REQUEST['kurangstok'];
			$nota = $_REQUEST['no'];
			$ppn = $_REQUEST['pajak'];
	
			$data = array(
				'nota' => $nota,
				'id_user' => $id_user,
				'id_karyawan' => $id_karyawan,
				'tgl' => $tgl,
				'kode' => $kode,
				'harga' => $harga,
				'qty' => $kurangstok,
				'jumlah' => $jumlah,
				'ppn' => $ppn,
			);
		}else{
			$id_user = $_REQUEST['id_user'];
			$id_karyawan = $_REQUEST['id_karyawan'];
			$tgl = date('Y-m-d H:i:s');
			$nama = $_REQUEST['nama'];
			$harga = $_REQUEST['harga'];
			$jumlah = $_REQUEST['jumlah'];
			$kode = $_REQUEST['kode'];
			$kurangstok = $_REQUEST['kurangstok'];
			$nota = $_REQUEST['no'];
			// $ppn = $_REQUEST['pajak'];
	
			$data = array(
				'nota' => $nota,
				'id_user' => $id_user,
				'id_karyawan' => $id_karyawan,
				'tgl' => $tgl,
				'kode' => $kode,
				'harga' => $harga,
				'qty' => $kurangstok,
				'jumlah' => $jumlah,
				// 'ppn' => $ppn,
			);
		}

		$this->db->insert('penjualan', $data);
		
		$query = $this->db->query("select * from stok where barcode = $kode");
		$row = $query->row_array();

		$update = $row['jumlah'] - $kurangstok;

		$data = array('jumlah' => $update);
		$where = "barcode = $kode";
		$str = $this->db->update_string('stok', $data, $where);
		$this->db->query($str);

		// echo $str;
	}

	public function delete_excel($f){
		unlink(dirname(__FILE__) .'\..\..\..\..\assets\uploads\\'.$f.".xls");
	}

	public function excel($file){
		date_default_timezone_set("Asia/Jakarta");
		// $file = $_POST['filename'];
		// $this->load->helper('file');
		// $this->load->library('excel_reader');

		if($file == "stok"){
			$fe = 'stok.xls';//urldecode($file);
		}else if($file == "karyawan"){
			$fe = 'karyawan.xls';//urldecode($file);
		}

		// Read the spreadsheet via a relative path to the document
		// for example $this->excel_reader->read('./uploads/file.xls');
		// $this->excel_reader->read(dirname(__FILE__) .'\..\..\..\assets\uploads\Book3.xls');
		// $this->excel_reader->read(dirname(__FILE__) .'\..\..\..\..\assets\uploads\\'.$fe);

		// Get the contents of the first worksheet
		// $worksheet = $this->excel_reader->sheets[0];

		// $data = $this->excel_reader->sheets[0];

		$filex = dirname(__FILE__) .'\..\..\..\..\assets\uploads\\'.$fe;
		
		//load the excel library
		$this->load->library('excel');
		
		//read file from path
		$objPHPExcel = PHPExcel_IOFactory::load($filex);
		
		//get only the Cell Collection
		$cell_collection = $objPHPExcel->getActiveSheet()->getCellCollection();

		$data_value = null;
		
		// print_r($cell_collection);
		//extract to a PHP readable array format
		foreach ($cell_collection as $cell) {
				$column = $objPHPExcel->getActiveSheet()->getCell($cell)->getColumn();
				$row = $objPHPExcel->getActiveSheet()->getCell($cell)->getRow();


				if($fe != 'karyawan.xls'){
					$data_value = $objPHPExcel->getActiveSheet()->getCell($cell)->getValue();
				}else{
					if ($column == 'E') {
						$dateTimeObject = PHPExcel_Shared_Date::ExcelToPHPObject($objPHPExcel->getActiveSheet()->getCell($cell)->getValue());
						$data_value = $dateTimeObject->format('Y-m-d');
					}else{
						$data_value = $objPHPExcel->getActiveSheet()->getCell($cell)->getValue();
					}
				}

				//The header will/should be in row 1 only. of course, this can be modified to suit your need.
				if ($row == 1) {
					$header[$row][$column] = $data_value;
				} else {
					$arr_data[$row][$column] = $data_value;
				}
		}
		
		//send the data in an array format
		// $data['header'] = $header;
		$data['values'] = $arr_data;

		if($file == "stok"){
			$this->db->trans_begin();
			$this->db->query("TRUNCATE TABLE `stok`");
			
			foreach ($data['values'] as $key => $value) {
				print_r( $value );
				$v1 = $value['A']; // barcode
				$v2 = str_replace("'","`",$value['B']); // nama
				$v3 = $value['C']; // jumlah
				$v4 = $value['D']; // harga
				$v5 = $value['E']; // distributor	
				$v6 = $value['F'];	
				$this->db->query("INSERT INTO `simpleton`.`stok` (`id`, `barcode`, `nama`, `jumlah`, `harga`, `distributor`,`ppn`) VALUES (NULL, '$v1', '$v2', '$v3', '$v4', '$v5', '$v6');");
				// $this->db->query("INSERT INTO `simpleton`.`stok` (`id`, `barcode`, `nama`, `jumlah`, `harga`) VALUES (NULL, '$v1', '$v2', '$v3', '$v4');");
			}

			if ($this->db->trans_status() === FALSE)
			{
				$this->db->trans_rollback();
			}
			else
			{
				$this->db->trans_commit();
			}

		}else if($file == "karyawan"){
			$this->db->trans_begin();
			$this->db->query("TRUNCATE TABLE `karyawan`");
			// print_r($data['values']);
			foreach ($data['values'] as $key => $value) {
				// print_r( $value );
				$v1 = $value['A']; // NPK
				// $this->db->escape(
				$v2 = str_replace("'","`", $value['B']); // nama
				$v3 = $value['C']; // bagian
				$v4 = $value['D']; // limit
				@$v5 = $value['E']; // join_date

				// echo "INSERT INTO `simpleton`.`karyawan` (`id`, `npk`, `nama`, `bagian`,`limit_belanja`, `join_date`) VALUES (NULL, '$v1', '$v2', '$v3', '$v4','$v5');\n";
				$this->db->query("INSERT INTO `simpleton`.`karyawan` (`id`, `npk`, `nama`, `bagian`,`limit_belanja`, `join_date`) VALUES (NULL, '$v1', '$v2', '$v3', '$v4','$v5');");
			}

			if ($this->db->trans_status() === FALSE)
			{
				$this->db->trans_rollback();
			}
			else
			{
				$this->db->trans_commit();
			}
		}
	}

	public function sales($dstart,$dstop){

	}

}

