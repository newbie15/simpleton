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
			base_url("assets/kasir/kasir.js"),			
		];

		$data['output'] = '
		<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4"><div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;"><div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div></div><div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;"><div style="position:absolute;width:200%;height:200%;left:0; top:0"></div></div></div>
		  <br>
          <table style="text-align: left; width: 100%;" border="0" cellpadding="1" cellspacing="1">
            <tbody>
			<tr>
				<td style="vertical-align: middle;">Kasir</td>
				<td style="vertical-align: top;">
					<select style="
					border-color: #ccc;
					border-radius: 4px;
					border-style: solid;
					border-width: 1px;
					box-shadow: 0 1px 2px rgba(0,0,0,0.1) inset;
					color: #555;
					float: none;
					padding: 6px 12px" id="nkasir" name="nkasir">
					<option value="belum">Belum bisa dipakai</option>
					<option value="volvo">Volvo</option>
					<option value="saab">Saab</option>
					<option value="fiat">Fiat</option>
					<option value="audi">Audi</option>
				</select>
				</td>
				<td style="vertical-align: middle;">Limit Belanja : <span id="limit_belanja"></span></td>
			</tr>
            <tr>
              <td style="vertical-align: middle;">NPK</td>
              <td style="vertical-align: top;" width="250px"><input type="text" name="npk" id="npk" placeholder="npk"/></td>
			  <td style="vertical-align: middle;">Belanja Bulan ini : <span id="limit"></span></td>
			  <td style="vertical-align: middle;" rowspan="4" width="250px" align="center"><img id="foto_karyawan" style="width:150px;height:150px;"></td>
            </tr>
            <tr>
              <td style="vertical-align: top;">Nama<br></td>
			  <td style="vertical-align: top;"><span id="nkaryawan"></span></td>			  
			  <td colspan="1" rowspan="2" style="vertical-align: top;"><big><big><big><big></big><span id="ttx">Total</span></big></big></big></td>

            </tr>
            <tr>
              <td style="vertical-align: top;">Alamat<br></td>
              <td style="vertical-align: top;"><span id="akaryawan"></span></td>
			</tr>
            <tr>
							<td colspan="3" rowspan="1">
							<br/>
                <input class="form-control w-100" type="text" name="barcode" id="barcode" autocomplete="off" placeholder="scan barcode here"/>
              </td>            
            </tr>
            </tbody>
          </table>
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
					<br>
					<button id="checkout" class="btn btn-info">Checkout [ F9 ]</button>
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
			base_url("assets/kasir/rekap.js"),			
			base_url("assets/jexcel/js/jexcel.js"),
			base_url("assets/jexcel/js/jsuites.js"),
			base_url("assets/papaparse/papaparse.min.js"),			
		];

		// $crud = new grocery_CRUD();

		// $crud->like('tgl',date('Y-m-d'));
		// $crud->set_table('penjualan');

		// $output = $crud->render();



	$data['output'] = '
	<div class="container">
		<h3>Rekap Penjualan</h3>
	</div>


	<div id="exTab2" class="container">	
		<ul class="nav nav-tabs">
			<li class="active">
        		<a id="h" href="#" data-toggle="tab">Harian</a>
			</li>
			<li>
				<a id="m" href="#" data-toggle="tab">Perorangan</a>
			</li>
			<li>
				<a id="b" href="#" data-toggle="tab">Bulanan</a>
			</li>
			<li>
				<a id="t" href="#" data-toggle="tab">Tahunan</a>
			</li>
		</ul>

		<div class="tab-content ">
			<div id="harian">
				<br>Tanggal : <input type="date" name="tanggal" id="tanggal"/>
				<button id="download-csv-harian">Download</button>
				<div id="harian-sheet"></div>
			</div>
			<div id="mingguan">
				<br>
				<table>
					<tr>
						<td align="right">NPK&nbsp;&nbsp;</td>
						<td>
							<div class="easy-autocomplete" style="width: 196px; float: left">
								<input type="text" name="npk" id="npk" placeholder="npk" autocomplete="off">
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
								<option value="xx">-- ALL --</option>
								<option value="01">januari</option>
								<option value="02">februari</option>
								<option value="03">maret</option>
								<option value="04">april</option>
								<option value="05">mei</option>
								<option value="06">juni</option>
								<option value="07">juli</option>
								<option value="08">agustus</option>
								<option value="09">september</option>
								<option value="10">oktober</option>
								<option value="11">november</option>
								<option value="12">desember</option>
							</select>						
						</td>
						<td align="right">
							&nbsp;&nbsp;
							<button id="download-csv-harian">Download</button>						
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
					<option value="xx">--pilih salah satu--</option>
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
					
				<div id="bulanan-sheet"></div>

			</div>
			<div id="tahunan">
				<h3>add clearfix to tab-content (see the css)</h3>
				<div id="placeholder4" style="width: 720px;height: 300px;"></div>
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