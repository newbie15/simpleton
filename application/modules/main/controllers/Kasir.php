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
		$allowed_methods = array('potong_gaji', 'tunai', 'qris', 'edc', 'transfer');

		// Data pembayaran tambahan
		$nominal_bayar  = (float) $this->input->post('nominal_bayar', TRUE);
		$kode_voucher   = trim((string) $this->input->post('kode_voucher', TRUE));
		$nomor_ref      = trim((string) $this->input->post('nomor_referensi', TRUE));
		$bank_tujuan    = trim((string) $this->input->post('bank_tujuan', TRUE));

		if ($nota === '' || strlen($nota) > 64 ||
			$id_user === '' || strlen($id_user) > 32 ||
			$id_karyawan === '' || strlen($id_karyawan) > 32 ||
			!in_array($payment_method, $allowed_methods, TRUE) ||
			!is_array($items) || count($items) === 0) {
			$this->_checkout_json(FALSE, 'Data checkout tidak valid. Periksa NPK, kasir, metode pembayaran, dan keranjang.');
			return;
		}

		// Validasi field spesifik per metode pembayaran
		if ($payment_method === 'tunai' && $nominal_bayar <= 0) {
			$this->_checkout_json(FALSE, 'Pembayaran tunai memerlukan nominal yang dibayarkan.');
			return;
		}
		if ($payment_method === 'edc' && $nomor_ref === '') {
			$this->_checkout_json(FALSE, 'Pembayaran EDC memerlukan nomor referensi/approval.');
			return;
		}
		if ($payment_method === 'transfer' && $bank_tujuan === '') {
			$this->_checkout_json(FALSE, 'Pembayaran transfer memerlukan nama bank tujuan.');
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

		if ($this->_respond_existing_checkout($nota, $id_user, $id_karyawan)) {
			return;
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

			$qty_needed = $item['qty'];
			$stok_pcs = (int) $product->jumlah;

			// Cek konversi dus->pcs jika stok pcs tidak cukup
			$unbox_actions = array();
			if ($stok_pcs < $qty_needed) {
				$conversions = $this->db->query("
					SELECT pc.barcode_dus, pc.rasio,
						s.id AS dus_id, s.jumlah AS stok_dus, s.barcode AS dus_barcode
					FROM product_conversions pc
					JOIN stok s ON s.barcode = pc.barcode_dus
					WHERE pc.barcode_pcs = ?
					ORDER BY pc.rasio DESC, s.id ASC
					FOR UPDATE
				", array($product->barcode))->result();
				$remaining_pcs = $qty_needed - $stok_pcs;
				foreach ($conversions as $conversion) {
					$ratio = (int) $conversion->rasio;
					$package_stock = (int) $conversion->stok_dus;
					if ($ratio < 1 || $package_stock < 1 || $remaining_pcs < 1) {
						continue;
					}
					$packages_needed = (int) ceil($remaining_pcs / $ratio);
					$packages_to_open = min($package_stock, $packages_needed);
					$unbox_actions[] = array(
						'dus_id' => (int) $conversion->dus_id,
						'dus_dipotong' => $packages_to_open,
						'rasio' => $ratio,
					);
					$remaining_pcs -= $packages_to_open * $ratio;
				}
				if ($remaining_pcs > 0) {
					$errors[] = $product->nama.' stok tidak cukup untuk memenuhi '.$qty_needed.' pcs. Stok pcs tersedia: '.$stok_pcs.'.';
					continue;
				}
			}

			$resolved_items[] = array(
				'product'      => $product,
				'qty'          => $item['qty'],
				'ppn'          => $item['ppn'],
				'unbox_actions' => $unbox_actions,
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

		// Hitung total tagihan & siapkan record pembayaran
		$total_tagihan = 0;
		foreach ($resolved_items as $item) {
			$subtotal = (int) $item['product']->harga * $item['qty'];
			if ($item['ppn'] > 0) {
				$subtotal += $subtotal * (float) $item['ppn'];
			}
			$total_tagihan += $subtotal;
		}

		// Validasi voucher (opsional)
		$diskon_voucher = 0.0;
		$voucher_id_used = NULL;
		if ($kode_voucher !== '') {
			$voucher_result = $this->_apply_voucher($kode_voucher, $total_tagihan);
			if (!$voucher_result['valid']) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_checkout_json(FALSE, $voucher_result['message']);
				return;
			}
			$diskon_voucher = $voucher_result['diskon'];
			$voucher_id_used = $voucher_result['voucher_id'];
			$total_tagihan = max(0, $total_tagihan - $diskon_voucher);
		}

		// Validasi kembalian untuk tunai
		if ($payment_method === 'tunai' && $nominal_bayar < $total_tagihan) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Uang tunai yang dibayarkan ('.number_format($nominal_bayar).')'
				.' kurang dari total tagihan ('.number_format($total_tagihan).').');
			return;
		}

		$kembalian = ($payment_method === 'tunai') ? max(0, $nominal_bayar - $total_tagihan) : 0;

		// Simpan tiap baris penjualan & kurangi stok
		foreach ($resolved_items as $item) {
			$product = $item['product'];
			$qty = $item['qty'];

			if (count($item['unbox_actions']) > 0) {
				foreach ($item['unbox_actions'] as $unbox_action) {
					$this->db->set('jumlah', 'jumlah - '.(int) $unbox_action['dus_dipotong'], FALSE)
						->where('id', $unbox_action['dus_id'])
						->where('jumlah >=', (int) $unbox_action['dus_dipotong'])
						->update('stok');
					if ($this->db->affected_rows() !== 1) {
						$errors[] = $product->nama.' stok kemasan berubah selama checkout. Muat ulang keranjang dan coba lagi.';
						break;
					}
				}
				if (count($errors) > 0) {
					break;
				}
				$remaining_pcs = (int) $product->jumlah;
				foreach ($item['unbox_actions'] as $unbox_action) {
					$remaining_pcs += (int) $unbox_action['rasio'] * (int) $unbox_action['dus_dipotong'];
				}
				$remaining_pcs -= $qty;
				$this->db->set('jumlah', $remaining_pcs, TRUE)
					->where('id', (int) $product->id)
					->update('stok');
			} else {
				$this->db->set('jumlah', 'jumlah - '.$qty, FALSE)
					->where('id', (int) $product->id)
					->where('jumlah >=', $qty)
					->update('stok');
				if ($this->db->affected_rows() !== 1) {
					$errors[] = $product->nama.' stok berubah selama checkout. Muat ulang keranjang dan coba lagi.';
					break;
				}
			}

			$this->db->insert('penjualan', array(
				'nota'             => $nota,
				'id_user'          => $id_user,
				'id_karyawan'      => $id_karyawan,
				'tgl'              => $sale_time,
				'kode'             => $product->barcode,
				'harga'            => (int) $product->harga,
				'qty'              => $qty,
				'jumlah'           => (int) $product->harga * $qty,
				'ppn'              => $item['ppn'],
				'hpp'              => $product->average_cost,
				'metode_pembayaran' => $payment_method,
			));
		}

		if (count($errors) > 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat.', $errors);
			return;
		}

		// Simpan header pembayaran ke penjualan_pembayaran
		$this->db->insert('penjualan_pembayaran', array(
			'nota'              => $nota,
			'metode_pembayaran' => $payment_method,
			'total_tagihan'     => $total_tagihan,
			'nominal_bayar'     => ($payment_method === 'tunai') ? $nominal_bayar : $total_tagihan,
			'kembalian'         => $kembalian,
			'kode_voucher'      => $kode_voucher !== '' ? $kode_voucher : NULL,
			'diskon_voucher'    => $diskon_voucher,
			'nomor_referensi'   => $nomor_ref !== '' ? $nomor_ref : NULL,
			'bank_tujuan'       => $bank_tujuan !== '' ? $bank_tujuan : NULL,
			'id_user'           => $id_user,
			'id_karyawan'       => $id_karyawan,
			'waktu_transaksi'   => $sale_time,
		));

		// Catat penggunaan voucher jika ada
		if ($voucher_id_used !== NULL) {
			$this->db->query(
				'UPDATE `vouchers` SET `terpakai` = `terpakai` + 1 WHERE `id` = ?',
				array($voucher_id_used)
			);
			$this->db->insert('voucher_usages', array(
				'voucher_id'     => $voucher_id_used,
				'nota'           => $nota,
				'npk_anggota'    => $id_karyawan,
				'nominal_diskon' => $diskon_voucher,
				'used_at'        => $sale_time,
			));
		}

		$this->load->library('accounting');
		$sale_totals = $this->db->query("
			SELECT COALESCE(SUM(jumlah), 0) AS gross_sales,
				COALESCE(SUM(jumlah * ppn), 0) AS tax_total,
				COALESCE(SUM(hpp * qty), 0) AS cogs_total
			FROM penjualan
			WHERE nota = ?
		", array($nota))->row();
		$payment_account = $payment_method === 'tunai' ? '1101' :
			($payment_method === 'potong_gaji' ? '1103' : '1102');
		$journal_lines = array();
		$payment_amount = round((float) $total_tagihan, 2);
		$discount_amount = round((float) $diskon_voucher, 2);
		$tax_amount = round((float) $sale_totals->tax_total, 2);
		$gross_amount = round((float) $sale_totals->gross_sales, 2);
		if ($payment_amount > 0) {
			$journal_lines[] = array(
				'account' => $payment_account,
				'debit' => $payment_amount,
				'credit' => 0,
				'memo' => 'Pembayaran nota '.$nota
			);
		}
		if ($discount_amount > 0) {
			$journal_lines[] = array(
				'account' => '4202',
				'debit' => $discount_amount,
				'credit' => 0,
				'memo' => 'Voucher '.$kode_voucher.' untuk nota '.$nota
			);
		}
		if ($gross_amount > 0) {
			$journal_lines[] = array(
				'account' => '4101',
				'debit' => 0,
				'credit' => $gross_amount,
				'memo' => 'Penjualan nota '.$nota
			);
		}
		if ($tax_amount > 0) {
			$journal_lines[] = array(
				'account' => '2103',
				'debit' => 0,
				'credit' => $tax_amount,
				'memo' => 'PPN keluaran nota '.$nota
			);
		}
		$cogs_amount = round((float) $sale_totals->cogs_total, 2);
		if ($cogs_amount > 0) {
			$journal_lines[] = array(
				'account' => '5101',
				'debit' => $cogs_amount,
				'credit' => 0,
				'memo' => 'HPP nota '.$nota
			);
			$journal_lines[] = array(
				'account' => '1104',
				'debit' => 0,
				'credit' => $cogs_amount,
				'memo' => 'Pengurangan persediaan nota '.$nota
			);
		}
		if (!$this->accounting->post_journal(
			'POS',
			$nota,
			substr($sale_time, 0, 10),
			'Penjualan POS '.$nota,
			$id_user,
			$journal_lines
		)) {
			$accounting_error = $this->accounting->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal memposting jurnal POS '.$nota.': '.$accounting_error);
			$this->_checkout_json(FALSE, 'Transaksi gagal dicatat karena jurnal akuntansi tidak dapat dibuat.');
			return;
		}

		if ($this->db->trans_status() === FALSE) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			if ($this->_respond_existing_checkout($nota, $id_user, $id_karyawan)) {
				return;
			}
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
			if ($this->_respond_existing_checkout($nota, $id_user, $id_karyawan)) {
				return;
			}
			$error_code = !empty($database_error['code']) ? $database_error['code'] : 'unknown';
			$error_message = !empty($database_error['message']) ? $database_error['message'] : 'Database commit failed.';
			error_log('Checkout database error '.$error_code.': '.$error_message);
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat (database error '.$error_code.').');
			return;
		}
		$this->db->db_debug = $previous_db_debug;
		$this->_checkout_json(TRUE, 'Transaksi berhasil dicatat.', array(), array(
			'kembalian'      => $kembalian,
			'total_tagihan'  => $total_tagihan,
			'diskon_voucher' => $diskon_voucher,
		));
	}

	private function _respond_existing_checkout($nota, $id_user, $id_karyawan)
	{
		$payment = $this->db->get_where('penjualan_pembayaran', array('nota' => $nota))->row();
		if (!$payment) {
			return FALSE;
		}

		$same_request_owner = $payment->id_user === $id_user && $payment->id_karyawan === $id_karyawan;
		if (!$same_request_owner) {
			$this->output->set_status_header(409);
			$this->_checkout_json(FALSE, 'Nomor transaksi sudah digunakan untuk transaksi lain.');
			return TRUE;
		}

		$this->_checkout_json(TRUE, 'Transaksi sudah tercatat.', array(), array(
			'total_tagihan' => (float) $payment->total_tagihan,
			'diskon_voucher' => (float) $payment->diskon_voucher,
			'kembalian' => (float) $payment->kembalian,
			'already_recorded' => TRUE,
		));
		return TRUE;
	}

	/**
	 * Validasi dan hitung diskon voucher.
	 * Return array: ['valid'=>bool, 'message'=>string, 'diskon'=>float, 'voucher_id'=>int|null]
	 */
	private function _apply_voucher($kode, $total_belanja)
	{
		$v = $this->db->query("
			SELECT id, tipe, nilai, min_belanja, max_diskon, kuota, terpakai
			FROM vouchers
			WHERE kode = ? AND is_active = 1
				AND tgl_mulai <= NOW() AND tgl_kadaluarsa >= NOW()
			FOR UPDATE
		", array($kode))->row();

		if (!$v) {
			return array('valid' => FALSE, 'message' => 'Voucher tidak valid atau sudah kedaluwarsa.', 'diskon' => 0, 'voucher_id' => NULL);
		}
		if ((int)$v->kuota > 0 && (int)$v->terpakai >= (int)$v->kuota) {
			return array('valid' => FALSE, 'message' => 'Kuota voucher sudah habis.', 'diskon' => 0, 'voucher_id' => NULL);
		}
		if ($total_belanja < (float)$v->min_belanja) {
			return array('valid' => FALSE, 'message' => 'Minimum belanja untuk voucher ini Rp '.number_format($v->min_belanja).'.', 'diskon' => 0, 'voucher_id' => NULL);
		}

		$diskon = 0.0;
		if ($v->tipe === 'diskon_nominal') {
			$diskon = (float) $v->nilai;
		} elseif ($v->tipe === 'diskon_persen') {
			$diskon = $total_belanja * (float) $v->nilai / 100.0;
			if ($v->max_diskon !== NULL && $diskon > (float) $v->max_diskon) {
				$diskon = (float) $v->max_diskon;
			}
		} elseif ($v->tipe === 'cashback') {
			$diskon = (float) $v->nilai; // Cashback dicatat sebagai potongan pada sesi ini
		}

		return array('valid' => TRUE, 'message' => 'OK', 'diskon' => $diskon, 'voucher_id' => (int) $v->id);
	}

	/**
	 * Endpoint: Validasi kode voucher sebelum checkout (AJAX GET)
	 * URL: main/kasir/validate_voucher/[kode]/[total]
	 */
	public function validate_voucher($kode = '', $total = 0)
	{
		if ($this->input->method(TRUE) !== 'GET') { show_404(); }
		$kode = trim((string) $kode);
		$total = (float) $total;
		if ($kode === '') {
			$this->output->set_content_type('application/json')->set_output(json_encode(array('valid' => FALSE, 'message' => 'Kode voucher kosong.')));
			return;
		}
		$result = $this->_apply_voucher($kode, $total);
		$this->output->set_content_type('application/json')->set_output(json_encode(array(
			'valid'   => $result['valid'],
			'message' => $result['message'],
			'diskon'  => $result['diskon'],
		)));
	}

	private function _checkout_json($success, $message, $errors = array(), $data = array())
	{
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'success' => (bool) $success,
				'message' => $message,
				'errors'  => $errors,
				'data'    => $data,
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
