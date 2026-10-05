<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Examples extends CI_Controller {

	public function __construct()
	{
		parent::__construct();

		$this->load->database();
		$this->load->helper('url');

		$this->load->library('grocery_CRUD');
	}

	public function _example_output($output = null)
	{
		$this->load->view('welcome_message',$output);
	}

	public function offices()
	{
		$output = $this->grocery_crud->render();

		$this->_example_output($output);
	}

	public function index()
	{
		$this->_example_output((object)array('output' => '' , 'js_files' => array() , 'css_files' => array()));
	}

	public function stok(){
		$crud = new grocery_CRUD();

		$crud->set_table('stok');

		$output = $crud->render();

		//Config Halaman
		$output->judul_besar = 'Stok';
		$output->judul_kecil = 'lihat stok dan rubah stok';
		$output->m_stok = TRUE;

		$this->_example_output($output);
	}

	public function update_data(){

		$data['css_file'] = [
			// base_url("assets/datatables/css/jquery.dataTables.min.css"),
			// base_url("assets/jexcel/css/jquery.jexcel.css"),
			// base_url("assets/jexcel/css/jquery.jcalendar.css"),
		];

		$data['js_file'] = [
			// base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
			// base_url("assets/datatables/js/jquery.dataTables.min.js"),
			// base_url("assets/jexcel/js/jquery.jexcel.js"),
			// base_url("assets/jexcel/js/jquery.jcalendar.js"),
			// base_url("assets/jexcel/js/excel-formula.min.js"),			
			// base_url("assets/jexcel/js/numeral.min.js"),			
			// base_url("assets/papaparse/papaparse.min.js"),			
			base_url("assets/kasir/update.js"),			

		];

		$data['output'] = '
		
		<h3>Update Data Stok</h3>
		<input type="file" name="stok" id="stok">
		<div id="dropBox"></div>
		<br/>
		<img src="'.base_url("assets/misc/samplestok.png").'"/>
		<br/>
		<h3>Update Data Karyawan</h3>
		<input type="file" name="karyawan" id="karyawan">
		<div id="dropBox1"></div>
		<br/>
		<img src="'.base_url("assets/misc/samplekaryawan.png").'"/>		
		<br/>

		';

		// <input type="file" name="stok" id="stok">

		// <input type="file" name="karyawan">
		// <input type="file" name="">


		//Config Halaman
		// $output->judul_besar = 'Update Stok';
		// $output->judul_kecil = 'lihat stok dan rubah stok';
		// $output->m_ustok = TRUE;

		// $this->_example_output($output);
		$data['m_update'] = TRUE;

		$this->load->view('welcome_message', $data);
	}

	public function karyawan(){
		$crud = new grocery_CRUD();

		$crud->set_table('karyawan');

		$output = $crud->render();

		//Config Halaman
		$output->judul_besar = 'Karyawan';
		$output->judul_kecil = 'menambah dan mngurangi daftar karyawan';
		$output->m_karyawan = TRUE;

		$this->_example_output($output);
	
	}
	public function user(){
		$crud = new grocery_CRUD();

		$crud->set_table('user');

		$output = $crud->render();

		//Config Halaman
		$output->judul_besar = 'User';
		$output->judul_kecil = 'menambah mengurangi user';
		$output->m_user = TRUE;

		$this->_example_output($output);

	}

	public function kartu(){
		$data['css_file'] = [
			base_url("assets/datatables/css/jquery.dataTables.min.css"),
			base_url("assets/jexcel/css/jquery.jexcel.css"),
			base_url("assets/jexcel/css/jquery.jcalendar.css"),
			base_url("assets/easyautocomplete/easy-autocomplete.min.css"),			
		];

		$data['js_file'] = [
			// base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
			base_url("assets/datatables/js/jquery.dataTables.min.js"),
			base_url("assets/jexcel/js/jquery.jexcel.js"),
			base_url("assets/jexcel/js/jquery.jcalendar.js"),
			base_url("assets/jexcel/js/excel-formula.min.js"),			
			base_url("assets/jexcel/js/numeral.min.js"),			
			base_url("assets/easyautocomplete/jquery.easy-autocomplete.min.js"),			
			base_url("assets/kartu/kartu.js"),			
		];

		$data['output'] = '
		<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4"><div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;"><div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div></div><div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:200%;height:200%;left:0; top:0"></div></div></div>
		  <h3>Kartu Anggota</h3>
          <br>
          <table style="text-align: left; width: 100%;" border="0" cellpadding="1" cellspacing="1">
            <tbody>
            <tr>
              <td style="vertical-align: middle;">NPK</td>
              <td style="vertical-align: top;"><input type="text" name="npk" id="npk" placeholder="npk"/></td>
            </tr>

			</tbody>
          </table>
          <br>
					<div style="
						height: 325px;
						width: 100%;
						overflow: auto;
						border: 1px solid #666;
						background-color: #ccc;
						padding: 8px;
					">
						<div id="my"></div>				
					</div>
					<br>
					<button id="checkout" class="btn btn-info">Cetak [ F9 ]</button>
				</main>
				
		';
		$data['m_kartu'] = TRUE;

		$this->load->view('welcome_message', $data);

	}

	public function kamera(){
		$data['css_file'] = [
			// base_url("assets/datatables/css/jquery.dataTables.min.css"),
			// base_url("assets/jexcel/css/jquery.jexcel.css"),
			// base_url("assets/jexcel/css/jquery.jcalendar.css"),
			base_url("assets/easyautocomplete/easy-autocomplete.min.css"),			
		];

		$data['js_file'] = [
			// base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
			// base_url("assets/datatables/js/jquery.dataTables.min.js"),
			// base_url("assets/jexcel/js/jquery.jexcel.js"),
			// base_url("assets/jexcel/js/jquery.jcalendar.js"),
			// base_url("assets/jexcel/js/excel-formula.min.js"),			
			// base_url("assets/jexcel/js/numeral.min.js"),			
			base_url("assets/easyautocomplete/jquery.easy-autocomplete.min.js"),			
			base_url("assets/kamera/kamera.js"),			
		];

		$data['output'] = '
		<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4"><div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;"><div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div></div><div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:200%;height:200%;left:0; top:0"></div></div></div>
		  <h3>Ambil Foto Anggota Koperasi</h3>
          <table style="text-align: left; width: 100%;" border="0" cellpadding="1" cellspacing="1">
            <tbody>
            <tr>
              <td style="vertical-align: middle;">NPK</td>
              <td style="vertical-align: top;"><input type="text" name="npk" id="npk" placeholder="npk"/></td>
            </tr>
			</tbody>
          </table>
		  <br>
		  
				<div style="
					height: 325px;
					width: 47%;
					overflow: auto;
					border: 1px solid #666;
					background-color: #ccc;
					padding: 8px;
					margin-right: 10px;
					float: left;
				">
					<video autoplay="true" id="videoElement" style="
						height: 100%;
						width: 100%;
						overflow: auto;
						border: 1px solid #666;
						background-color: #ccc;
						padding: 8px;
						margin-right: 10px;
						float: left;
					">
					</video>
				</div>
				<div style="
					height: 325px;
					width: 47%;
					overflow: auto;
					border: 1px solid #666;
					background-color: #ccc;
					padding: 8px;
				">
					<div id="my"></div>				
				</div>
				<br>
				<button id="checkout" class="btn btn-info">Cetak [ F9 ]</button>
			</main>
				
		';
		$data['m_foto'] = TRUE;
		$this->load->view('welcome_message', $data);

	}

	public function penjualan(){
		// $data['judul_besar'] = 'Simpleton';
		// $data['judul_kecil'] = 'Version 1.0.0';

		$data['css_file'] = [
			base_url("assets/datatables/css/jquery.dataTables.min.css"),
			base_url("assets/jexcel/css/jquery.jexcel.css"),
			base_url("assets/jexcel/css/jquery.jcalendar.css"),
			base_url("assets/easyautocomplete/easy-autocomplete.min.css"),			
		];

		$data['js_file'] = [
			// base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
			base_url("assets/datatables/js/jquery.dataTables.min.js"),
			base_url("assets/jexcel/js/jquery.jexcel.js"),
			base_url("assets/jexcel/js/jquery.jcalendar.js"),
			base_url("assets/jexcel/js/excel-formula.min.js"),			
			base_url("assets/jexcel/js/numeral.min.js"),			
			base_url("assets/easyautocomplete/jquery.easy-autocomplete.min.js"),			
			base_url("assets/kasir/kasir.js?v=20261004-cashier-default"),
		];
		$data['m_penjualan'] = TRUE;

		$data['output'] = '
		<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4"><div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;"><div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div></div><div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:200%;height:200%;left:0; top:0"></div></div></div>
		  <br>
          <div class="row">
            <div class="col-sm-4">
              <div class="form-group">
                <label for="npk">NPK / Karyawan</label>
                <input class="form-control" type="text" name="npk" id="npk" placeholder="Cari nama atau masukkan NPK">
              </div>
              <div class="form-group">
                <label for="metode_pembayaran">Metode Pembayaran</label>
                <select class="form-control" id="metode_pembayaran" name="metode_pembayaran">
                  <option value="potong_gaji">Potong gaji</option>
                  <option value="tunai">Tunai / Cash</option>
                  <option value="qris">QRIS</option>
                </select>
              </div>
              <div class="form-group">
                <label for="nkasir">Kasir</label>
                <select class="form-control" id="nkasir" name="nkasir">
                  <option value="belum">Memuat daftar kasir...</option>
                </select>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="min-height: 145px; padding-top: 4px;">
                <div><strong id="nkaryawan"></strong></div>
                <div id="akaryawan" class="text-muted"></div>
                <div class="small" style="margin-top: 8px;">Belanja bulan ini: <strong id="limit"></strong></div>
                <div class="small" style="margin-top: 4px;">Limit belanja: <strong id="limit_belanja"></strong></div>
                <div style="margin-top: 14px; font-size: 22px; font-weight: bold;" id="ttx">Total Rp 0</div>
                <div class="checkbox" style="margin-top: 12px;">
                  <label style="font-weight: normal;">
                    <input type="checkbox" id="pajak-active">
                    Aktifkan pajak (PPN)
                  </label>
                </div>
                <span class="help-block" style="margin: 0;">Atur sebelum scan barang.</span>
              </div>
            </div>
            <div class="col-sm-4 text-center">
              <label style="display: block;">Verifikasi karyawan</label>
              <div id="foto-karyawan-placeholder" class="text-muted small" style="width: 150px; height: 190px; margin: 0 auto; border: 1px dashed #aaa; display: flex; align-items: center; justify-content: center; text-align: center;">Foto belum ada</div>
              <img id="foto_karyawan" alt="Foto karyawan" style="display: none; width: 150px; height: 190px; margin: 0 auto; object-fit: cover; border: 1px solid #ddd;">
            </div>
          </div>
          <div class="row">
            <div class="col-sm-9">
              <label for="barcode">Scan / cari barang</label>
              <input class="form-control" type="text" name="barcode" id="barcode" autocomplete="off" placeholder="Scan barcode barang">
            </div>
            <div class="col-sm-3">
              <label>&nbsp;</label>
              <button id="checkout" class="btn btn-info btn-block">Checkout [ F9 ]</button>
            </div>
          </div>
          <br>
          <!-- <div class="table-responsive">
            <table id="tablecart" class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>no</th>
                  <th>kode</th>
                  <th>nama barang</th>
                  <th>harga</th>
                  <th>kuantiti</th>
				  <th>jumlah</th>
									
                </tr>
              </thead>
              <tbody id="cart-list">
              </tbody>
              
            </table>
					</div> -->

					<div style="
						height: 325px;
						width: 100%;
						overflow: auto;
						border: 1px solid #666;
						background-color: #ccc;
						padding: 8px;
					"
					id="scrll"
					>
						<div id="my"></div>				
					</div>
				</main>
				
		';
		$this->load->view('welcome_message', $data);
	}
	public function rekap_penjualan(){
		$data['css_file'] = [
			// base_url("assets/datatables/css/jquery.dataTables.min.css"),
			base_url("assets/jexcel/css/jexcel.css"),
			base_url("assets/jexcel/css/jsuites.css"),
			base_url("assets/easyautocomplete/easy-autocomplete.min.css"),			
		];
		$data['js_file'] = [
			// base_url("assets/flot/jquery.flot.min.js"),			
			base_url("assets/easyautocomplete/jquery.easy-autocomplete.min.js"),			
			base_url("assets/kasir/rekap.js?v=20261004-4"),
			base_url("assets/jexcel/js/jexcel.js"),
			base_url("assets/jexcel/js/jsuites.js"),
			base_url("assets/papaparse/papaparse.min.js"),			
		];

	$data['output'] = '
	<div id="exTab2" class="container" data-ajax-base="'.site_url('main/ajax').'">
		<ul class="nav nav-tabs">
			<li class="active">
				<a id="h" href="#harian">Harian</a>
			</li>
			<li>
				<a id="m" href="#perorangan">Perorangan</a>
			</li>
			<li>
				<a id="b" href="#bulanan">Bulanan</a>
			</li>
			<li>
				<a id="t" href="#tahunan">Tahunan</a>
			</li>
		</ul>

		<div class="tab-content ">
			<div id="harian">
				<br>Tanggal : <input type="date" name="tanggal" id="tanggal"/>
				<button type="button" id="download-csv-harian">Download CSV</button>
				<div style="max-width: 100%; overflow-x: auto;">
					<div id="harian-sheet"></div>
				</div>
			</div>
			<div id="perorangan">
				<br>
				<table>
					<tr>
						<td align="right">NPK&nbsp;&nbsp;</td>
						<td>
							<div class="easy-autocomplete" style="width: 196px; float: left">
								<input type="text" name="npk" id="npk" placeholder="Nama atau NPK karyawan" autocomplete="off">
							</div>						
						</td>
						<td>
							&nbsp;&nbsp;
							Tahun :
							<select id="tahun_perorangan">
								<option>tahun</option>
							</select>
							Bulan : 
							<select id="bulan_perorangan">
								<option value="01">17 Des - 20 Jan</option>
								<option value="02">21 Jan - 20 Feb</option>
								<option value="03">21 Feb - 20 Mar</option>
								<option value="04">21 Mar - 20 Apr</option>
								<option value="05">21 Apr - 20 Mei</option>
								<option value="06">21 Mei - 20 Jun</option>
								<option value="07">21 Jun - 20 Jul</option>
								<option value="08">21 Jul - 20 Agu</option>
								<option value="09">21 Agu - 20 Sep</option>
								<option value="10">21 Sep - 20 Okt</option>
								<option value="11">21 Okt - 20 Nov</option>
								<option value="12">21 Nov - 16 Des</option>
							</select>						
						</td>
						<td align="right">
							&nbsp;&nbsp;
							<button type="button" id="download-csv-perorangan">Download CSV</button>
						</td>
					</tr>
				</table>
				<br>

				<br>

				<div id="perorangan-sheet"></div>
			</div>
			<div id="bulanan">
			<br/>
				Tahun :
				<select id="tahun_bulanan">
					<option>tahun</option>
				</select>
				Bulan Pembukuan
				<select id="bulan_bulanan">
					<option value="01">17 desember - 20 januari</option>
					<option value="02">21 januari - 20 februari</option>
					<option value="03">21 februari - 20 maret</option>
					<option value="04">21 maret - 20 april</option>
					<option value="05">21 april - 20 mei</option>
					<option value="06">21 mei - 20 juni</option>
					<option value="07">21 juni - 20 juli</option>
					<option value="08">21 juli - 20 agustus</option>
					<option value="09">21 agustus - 20 september</option>
					<option value="10">21 september - 20 oktober</option>
					<option value="11">21 oktober - 20 november</option>
					<option value="12">21 november - 16 desember</option>
				</select>
				<button type="button" id="download-csv-bulanan">Download CSV</button>
				<div id="bulanan-sheet"></div>

			</div>
			<div id="tahunan">
				<br>Tahun :
				<select id="tahun_tahunan"><option>tahun</option></select>
				<button type="button" id="download-csv-tahunan">Download CSV</button>
				<div id="tahunan-sheet"></div>
			</div>
		</div>
  </div>

<hr></hr>

	</div>
	';

	$data['m_rekap'] = TRUE;

	$this->load->view('welcome_message', $data);
	}
}