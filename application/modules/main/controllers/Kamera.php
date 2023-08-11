<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Kamera extends CI_Controller {

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
					<canvas id="my" style="display:none"></canvas>
					<img id="#snap_photo" src="" style="height: 100%;width: 100%;">								
				</div>
				<br>
				<button id="snapshot" class="btn btn-info">Ambil Snapshot</button>
				<button id="simpan" class="btn btn-success" style="margin-left: 625px;">Simpan Foto</button>


			</main>
				
		';
		$data['m_foto'] = TRUE;

		$this->load->view('welcome_message', $data);
		// $data = "";
		// $this->load->view('kamera', $data);
	}

	public function load_photo(){
        echo $npk = $this->uri->segment(4);		

	}
	public function upload_photo()
	{

		$npk = explode("-",$_POST['id_karyawan']) ;

		$b64 = $_POST['foto'];

		if($npk != "" && $b64 != ""){

			$b64 = str_replace("data:image/png;base64,","",$b64); 
			// Obtain the original content (usually binary data)
			$bin = base64_decode($b64);
			// Specify the location where you want to save the image
			$img_file = "./assets/uploads/karyawan/$npk[1].png";
			// Save binary data as raw data (that is, it will not remove metadata or invalid contents)
			// In this case, the PHP backdoor will be stored on the server

			// raw data is not safe and make a security hole, but i don't mind :) 
			file_put_contents($img_file, $bin);

		
		}

		// // Load GD resource from binary data
		// $im = imageCreateFromString($bin);

		// // Make sure that the GD library was able to load the image
		// // This is important, because you should not miss corrupted or unsupported images
		// if (!$im) {
		// 	die('Base64 value is not a valid image');
		// }

		// // Specify the location where you want to save the image
		// $img_file = '/assets/uploads/karyawan/'.$npk[1].'.png';
		// echo '/assets/uploads/karyawan/'.$npk[1].'.png';

		// // Save the GD resource as PNG in the best possible quality (no compression)
		// // This will strip any metadata or invalid contents (including, the PHP backdoor)
		// // To block any possible exploits, consider increasing the compression level
		// imagepng($im, $img_file, 0);


	}
}
