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
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$nota = trim((string) $this->input->post('no', TRUE));
		$id_user = trim((string) $this->input->post('id_user', TRUE));
		$id_karyawan = trim((string) $this->input->post('id_karyawan', TRUE));
		$payment_method = trim((string) $this->input->post('metode_pembayaran', TRUE));
		$items_json = $this->input->post('items_json', FALSE);
		$items = is_string($items_json) ? json_decode($items_json, TRUE) : NULL;
		$allowed_methods = array('potong_gaji', 'tunai', 'qris');

		if ($nota === '' || strlen($nota) > 64 ||
			$id_user === '' || strlen($id_user) > 11 ||
			$id_karyawan === '' || strlen($id_karyawan) > 11 ||
			!in_array($payment_method, $allowed_methods, TRUE) ||
			!is_array($items) || count($items) === 0) {
			$this->_checkout_json(FALSE, 'Data checkout tidak valid. Periksa NPK, kasir, metode pembayaran, dan keranjang.');
			return;
		}

		$clean_items = array();
		$seen_barcodes = array();
		foreach ($items as $item) {
			if (!is_array($item) || !isset($item['barcode'], $item['qty'])) {
				$this->_checkout_json(FALSE, 'Data barang pada keranjang tidak valid.');
				return;
			}
			$barcode = trim((string) $item['barcode']);
			$qty = (string) $item['qty'];
			$ppn = isset($item['ppn']) ? $item['ppn'] : 0;
			if ($barcode === '' || strlen($barcode) > 64 ||
				!preg_match('/^[1-9][0-9]*$/', $qty) ||
				!is_numeric($ppn) || (float) $ppn < 0 ||
				isset($seen_barcodes[$barcode])) {
				$this->_checkout_json(FALSE, 'Barcode atau qty barang pada keranjang tidak valid.');
				return;
			}
			$seen_barcodes[$barcode] = TRUE;
			$clean_items[] = array(
				'barcode' => $barcode,
				'qty' => (int) $qty,
				'ppn' => (float) $ppn
			);
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat. Database tidak dapat memulai penyimpanan.');
			return;
		}
		$resolved_items = array();
		$errors = array();
		foreach ($clean_items as $item) {
			$product_query = $this->db->query("
				SELECT stock.id, stock.barcode, stock.nama, stock.jumlah, stock.harga,
					cost.average_cost
				FROM stok stock
				LEFT JOIN inventory_costs cost
					ON cost.product_id = stock.id AND cost.barcode = stock.barcode
				WHERE stock.barcode = ?
				FOR UPDATE
			", array($item['barcode']));

			if ($product_query === FALSE) {
				$errors[] = 'Database gagal membaca data barang.';
				break;
			}
			$product = $product_query->row();
			if (!$product) {
				$errors[] = 'Barcode '.$item['barcode'].' tidak ditemukan di stok.';
				continue;
			}
			if ($product->average_cost === NULL || (float) $product->average_cost <= 0) {
				$errors[] = $product->nama.' (barcode '.$product->barcode.') belum memiliki HPP valid.';
				continue;
			}
			if ((int) $product->jumlah < $item['qty']) {
				$errors[] = $product->nama.' (barcode '.$product->barcode.') stok tidak cukup. Stok tersedia: '.(int) $product->jumlah.'.';
				continue;
			}
			$resolved_items[] = array(
				'product' => $product,
				'qty' => $item['qty'],
				'ppn' => $item['ppn']
			);
		}

		if (count($errors) > 0) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			if (!empty($database_error['code'])) {
				error_log('Checkout database error '.$database_error['code'].': '.$database_error['message']);
				$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat (database error '.$database_error['code'].').');
				return;
			}
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat.', $errors);
			return;
		}

		date_default_timezone_set('Asia/Jakarta');
		$sale_time = date('Y-m-d H:i:s');
		foreach ($resolved_items as $item) {
			$product = $item['product'];
			$qty = $item['qty'];
			$this->db->set('jumlah', 'jumlah - '.$qty, FALSE)
				->where('id', (int) $product->id)
				->update('stok');
			$this->db->insert('penjualan', array(
				'nota' => $nota,
				'id_user' => $id_user,
				'id_karyawan' => $id_karyawan,
				'tgl' => $sale_time,
				'kode' => $product->barcode,
				'harga' => (int) $product->harga,
				'qty' => $qty,
				'jumlah' => (int) $product->harga * $qty,
				'ppn' => $item['ppn'],
				'hpp' => $product->average_cost,
				'metode_pembayaran' => $payment_method
			));
		}

		if ($this->db->trans_status() === FALSE) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$error_code = !empty($database_error['code']) ? $database_error['code'] : 'unknown';
			$error_message = !empty($database_error['message']) ? $database_error['message'] : 'Database transaction failed.';
			error_log('Checkout database error '.$error_code.': '.$error_message);
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat (database error '.$error_code.').');
			return;
		}
		if (!$this->db->trans_commit()) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$error_code = !empty($database_error['code']) ? $database_error['code'] : 'unknown';
			$error_message = !empty($database_error['message']) ? $database_error['message'] : 'Database commit failed.';
			error_log('Checkout database error '.$error_code.': '.$error_message);
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat (database error '.$error_code.').');
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_checkout_json(TRUE, 'Transaksi berhasil dicatat.');
	}

	private function _checkout_json($success, $message, $errors = array())
	{
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'success' => (bool) $success,
				'message' => $message,
				'errors' => $errors
			)));
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
