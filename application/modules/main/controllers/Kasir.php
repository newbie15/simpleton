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

	public function reprint()
	{
		$this->load->database();
		$this->load->helper('url');
		$this->load->library(array('session', 'ion_auth', 'page_access'));
		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('penjualan')) {
			show_error('Anda tidak memiliki akses untuk mencetak ulang nota.', 403);
		}

		$nota = trim((string) $this->input->get('nota', TRUE));
		$receipt = NULL;
		if ($nota !== '') {
			if (strlen($nota) > 64) {
				show_error('Nomor nota tidak valid.', 400);
			}
			$receipt = $this->db->query("
				SELECT sale.nota, sale.id_karyawan AS npk, MIN(sale.tgl) AS transaction_date,
					COALESCE(employee.nama, sale.id_karyawan) AS employee_name,
					COALESCE(NULLIF(CONCAT_WS(' ', cashier.first_name, cashier.last_name), ''), cashier.username, payment.id_user, sale.id_user) AS cashier_name,
					COALESCE(payment.metode_pembayaran, sale.metode_pembayaran, '') AS payment_method,
					COALESCE(payment.total_tagihan, SUM(sale.jumlah + sale.jumlah * sale.ppn), SUM(sale.jumlah)) AS total_paid,
					COALESCE(payment.diskon_voucher, 0) AS voucher_discount,
					COALESCE(payment.kode_voucher, '') AS voucher_code,
					COALESCE(payment.nominal_bayar, 0) AS amount_paid,
					COALESCE(payment.kembalian, 0) AS change_amount
				FROM penjualan sale
				LEFT JOIN penjualan_pembayaran payment ON payment.nota = sale.nota
				LEFT JOIN karyawan employee ON employee.npk = sale.id_karyawan
				LEFT JOIN users cashier ON cashier.id = payment.id_user
				WHERE sale.nota = ?
				GROUP BY sale.nota, sale.id_karyawan, employee.nama, cashier.first_name,
					cashier.last_name, cashier.username, payment.id_user, sale.id_user,
					payment.metode_pembayaran, sale.metode_pembayaran, payment.total_tagihan,
					payment.diskon_voucher, payment.kode_voucher, payment.nominal_bayar, payment.kembalian
			", array($nota))->row();
			if (!$receipt) {
				$this->session->set_flashdata('receipt_error', 'Nota tidak ditemukan. Periksa kembali nomor nota.');
				redirect('main/kasir/reprint');
			}
			$receipt->items = $this->db->query("
				SELECT sale.kode AS barcode, COALESCE(stock.nama, sale.kode) AS product_name,
					sale.qty, sale.harga, sale.jumlah, sale.ppn
				FROM penjualan sale
				LEFT JOIN stok stock ON stock.barcode = sale.kode
				WHERE sale.nota = ?
				ORDER BY sale.id ASC
			", array($nota))->result();
		}

		$data = array(
			'nota' => $nota,
			'receipt' => $receipt,
			'error' => $this->session->flashdata('receipt_error')
		);
		$this->load->view('pos_receipt_reprint', $data);
	}

	public function checkout(){
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$input = $this->_checkout_input();
		if ($input === FALSE) {
			return;
		}
		$request_id = $input['request_id'];
		$id_user = $input['id_user'];
		$id_karyawan = $input['id_karyawan'];
		$payment_method = $input['payment_method'];
		$nominal_bayar = $input['nominal_bayar'];
		$kode_voucher = $input['kode_voucher'];
		$nomor_ref = $input['nomor_referensi'];
		$bank_tujuan = $input['bank_tujuan'];
		if (!$this->db->table_exists('pos_receipt_sequences') ||
			!$this->db->field_exists('checkout_request_id', 'penjualan_pembayaran')) {
			$this->_checkout_json(FALSE, 'Skema nomor nota pendek belum tersedia. Jalankan application/sql/pos_receipt_sequence.sql.');
			return;
		}

		$clean_items = $this->_clean_cart_items($input['items']);
		if ($clean_items === FALSE) {
			return;
		}

		if ($this->_respond_existing_checkout($request_id, $id_user, $id_karyawan)) {
			return;
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat. Database tidak dapat memulai penyimpanan.');
			return;
		}

		$resolution = $this->_resolve_checkout_items($clean_items);
		if (count($resolution['errors']) > 0) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			if (!empty($database_error['code'])) {
				error_log('Checkout database error '.$database_error['code'].': '.$database_error['message']);
				$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat (database error '.$database_error['code'].').');
				return;
			}
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat.', $resolution['errors']);
			return;
		}
		$resolved_items = $resolution['items'];

		date_default_timezone_set('Asia/Jakarta');
		$sale_time = date('Y-m-d H:i:s');
		if (($payment_method === 'tunai' || $payment_method === 'cash') &&
			$this->db->table_exists('acc_cash_funds') &&
			$this->db->table_exists('acc_cash_closings')) {
			$cashier_fund = $this->db->query("
				SELECT id FROM acc_cash_funds
				WHERE fund_slot = 'cashier' AND is_active = 1
				FOR UPDATE
			")->row();
			$cashier_closed = $cashier_fund ? $this->db->get_where('acc_cash_closings', array(
				'fund_id' => (int) $cashier_fund->id,
				'closing_date' => substr($sale_time, 0, 10)
			))->row() : NULL;
			if ($cashier_closed) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_checkout_json(FALSE, 'Laci kasir sudah ditutup untuk hari ini. Hubungi pengurus untuk membuka layanan kasir berikutnya.');
				return;
			}
		}

		// Hitung total tagihan & siapkan record pembayaran
		$total_tagihan = $this->_checkout_subtotal($resolved_items);

		// Validasi voucher (opsional)
		$diskon_voucher = 0.0;
		$voucher_id_used = NULL;
		$voucher_coupon_id_used = NULL;
		if ($kode_voucher !== '') {
			$voucher_result = $this->_apply_voucher($kode_voucher, $total_tagihan, $id_karyawan);
			if (!$voucher_result['valid']) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_checkout_json(FALSE, $voucher_result['message']);
				return;
			}
			$diskon_voucher = $voucher_result['diskon'];
			$voucher_id_used = $voucher_result['voucher_id'];
			$voucher_coupon_id_used = $voucher_result['coupon_id'];
			$total_tagihan = max(0, $total_tagihan - $diskon_voucher);
		}

		$is_cash = ($payment_method === 'tunai' || $payment_method === 'cash');

		// Validasi kembalian untuk tunai
		if ($is_cash && $nominal_bayar < $total_tagihan) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Uang tunai yang dibayarkan ('.number_format($nominal_bayar, 0, ',', '.').')'
				.' kurang dari total tagihan ('.number_format($total_tagihan, 0, ',', '.').').');
			return;
		}

		$kembalian = $is_cash ? max(0, $nominal_bayar - $total_tagihan) : 0;
		$nota = $this->_next_receipt_number($id_karyawan, $sale_time);
		if ($nota === FALSE) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Nomor nota tidak dapat dibuat. Transaksi belum disimpan.');
			return;
		}

		// Simpan tiap baris penjualan & kurangi stok
		$errors = $this->_persist_sale_lines($resolved_items, $nota, $id_user, $id_karyawan, $sale_time, $payment_method);

		if (count($errors) > 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_checkout_json(FALSE, 'Transaksi gagal dan belum tercatat.', $errors);
			return;
		}

		// Simpan header pembayaran ke penjualan_pembayaran
		$this->db->insert('penjualan_pembayaran', array(
			'nota'              => $nota,
			'checkout_request_id' => $request_id,
			'metode_pembayaran' => $payment_method,
			'total_tagihan'     => $total_tagihan,
			'nominal_bayar'     => $is_cash ? $nominal_bayar : $total_tagihan,
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
			$usage_record = array(
				'voucher_id'     => $voucher_id_used,
				'nota'           => $nota,
				'npk_anggota'    => $id_karyawan,
				'nominal_diskon' => $diskon_voucher,
				'used_at'        => $sale_time,
			);
			if ($this->db->field_exists('voucher_coupon_id', 'voucher_usages')) {
				$usage_record['voucher_coupon_id'] = $voucher_coupon_id_used;
			}
			if (!$this->db->insert('voucher_usages', $usage_record)) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_checkout_json(FALSE, 'Riwayat penggunaan voucher gagal disimpan. Transaksi tidak disimpan.');
				return;
			}
			if ($voucher_coupon_id_used !== NULL) {
				$updated_coupon = $this->db->where('id', (int) $voucher_coupon_id_used)
					->where('status', 'available')->update('voucher_coupons', array(
						'status' => 'used',
						'used_nota' => $nota,
						'used_at' => $sale_time
					));
				if (!$updated_coupon || $this->db->affected_rows() !== 1) {
					$this->db->trans_rollback();
					$this->db->db_debug = $previous_db_debug;
					$this->_checkout_json(FALSE, 'Kupon sudah dipakai atau gagal ditandai. Transaksi tidak disimpan.');
					return;
				}
			}
		}

		$journal_error = $this->_post_checkout_journal($nota, $payment_method, $id_user, $sale_time, $total_tagihan, $diskon_voucher, $kode_voucher);
		if ($journal_error !== NULL) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal memposting jurnal POS '.$nota.': '.$journal_error);
			$this->_checkout_json(FALSE, 'Transaksi gagal dicatat karena jurnal akuntansi tidak dapat dibuat.');
			return;
		}

		if ($this->db->trans_status() === FALSE) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			if ($this->_respond_existing_checkout($request_id, $id_user, $id_karyawan)) {
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
			if ($this->_respond_existing_checkout($request_id, $id_user, $id_karyawan)) {
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
			'nota'           => $nota,
			'kembalian'      => $kembalian,
			'total_tagihan'  => $total_tagihan,
			'diskon_voucher' => $diskon_voucher,
		));
	}

	/**
	 * Ambil & validasi input checkout. Return array data siap pakai, atau FALSE jika respons error sudah dikirim.
	 */
	private function _checkout_input()
	{
		$request_id = trim((string) $this->input->post('request_id', TRUE));
		if ($request_id === '') {
			$request_id = trim((string) $this->input->post('no', TRUE));
		}
		$id_user = trim((string) $this->input->post('id_user', TRUE));
		$id_karyawan = trim((string) $this->input->post('id_karyawan', TRUE));
		$payment_method = trim((string) $this->input->post('metode_pembayaran', TRUE));
		$items_json = $this->input->post('items_json', FALSE);
		$items = is_string($items_json) ? json_decode($items_json, TRUE) : NULL;
		$allowed_methods = array('potong_gaji', 'tunai', 'cash', 'qris', 'edc', 'transfer');

		// Data pembayaran tambahan
		$nominal_bayar  = (float) $this->input->post('nominal_bayar', TRUE);
		$kode_voucher   = trim((string) $this->input->post('kode_voucher', TRUE));
		$nomor_ref      = trim((string) $this->input->post('nomor_referensi', TRUE));
		$bank_tujuan    = trim((string) $this->input->post('bank_tujuan', TRUE));

		if ($request_id === '' || strlen($request_id) > 64 ||
			!preg_match('/^[A-Za-z0-9_-]+$/D', $request_id) ||
			$id_user === '' || strlen($id_user) > 32 ||
			$id_karyawan === '' || strlen($id_karyawan) > 32 ||
			!in_array($payment_method, $allowed_methods, TRUE) ||
			!is_array($items) || count($items) === 0) {
			$this->_checkout_json(FALSE, 'Data checkout tidak valid. Periksa NPK, kasir, metode pembayaran, dan keranjang.');
			return FALSE;
		}

		// Validasi field spesifik per metode pembayaran
		$is_cash = ($payment_method === 'tunai' || $payment_method === 'cash');
		if ($is_cash && $nominal_bayar <= 0) {
			$this->_checkout_json(FALSE, 'Pembayaran tunai memerlukan nominal yang dibayarkan.');
			return FALSE;
		}
		if ($payment_method === 'edc' && $nomor_ref === '') {
			$this->_checkout_json(FALSE, 'Pembayaran EDC memerlukan nomor referensi/approval.');
			return FALSE;
		}
		if ($payment_method === 'transfer' && $bank_tujuan === '') {
			$this->_checkout_json(FALSE, 'Pembayaran transfer memerlukan nama bank tujuan.');
			return FALSE;
		}

		return array(
			'request_id'       => $request_id,
			'id_user'          => $id_user,
			'id_karyawan'      => $id_karyawan,
			'payment_method'   => $payment_method,
			'nominal_bayar'    => $nominal_bayar,
			'kode_voucher'     => $kode_voucher,
			'nomor_referensi'  => $nomor_ref,
			'bank_tujuan'      => $bank_tujuan,
			'items'            => $items,
		);
	}

	/**
	 * Bersihkan & validasi baris keranjang. Return array item bersih, atau FALSE jika respons error sudah dikirim.
	 */
	private function _clean_cart_items($items)
	{
		$clean_items = array();
		$seen_barcodes = array();
		foreach ($items as $item) {
			if (!is_array($item) || !isset($item['barcode'], $item['qty'])) {
				$this->_checkout_json(FALSE, 'Data barang pada keranjang tidak valid.');
				return FALSE;
			}
			$barcode = trim((string) $item['barcode']);
			$qty = (string) $item['qty'];
			$ppn = isset($item['ppn']) ? $item['ppn'] : 0;
			if ($barcode === '' || strlen($barcode) > 64 ||
				!preg_match('/^[1-9][0-9]*$/', $qty) ||
				!is_numeric($ppn) || (float) $ppn < 0 ||
				isset($seen_barcodes[$barcode])) {
				$this->_checkout_json(FALSE, 'Barcode atau qty barang pada keranjang tidak valid.');
				return FALSE;
			}
			$seen_barcodes[$barcode] = TRUE;
			$clean_items[] = array(
				'barcode' => $barcode,
				'qty' => (int) $qty,
				'ppn' => (float) $ppn
			);
		}
		return $clean_items;
	}

	/**
	 * Resolve tiap item ke produk & stok (termasuk rencana buka dus). Return ['items'=>[], 'errors'=>[]].
	 */
	private function _resolve_checkout_items($clean_items)
	{
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

		return array('items' => $resolved_items, 'errors' => $errors);
	}

	/**
	 * Hitung total tagihan (harga * qty + PPN per baris).
	 */
	private function _checkout_subtotal($resolved_items)
	{
		$total_tagihan = 0;
		foreach ($resolved_items as $item) {
			$subtotal = (int) $item['product']->harga * $item['qty'];
			if ($item['ppn'] > 0) {
				$subtotal += $subtotal * (float) $item['ppn'];
			}
			$total_tagihan += $subtotal;
		}
		return $total_tagihan;
	}

	/**
	 * Kurangi stok (termasuk buka dus) dan tulis baris penjualan. Return daftar error.
	 */
	private function _persist_sale_lines($resolved_items, $nota, $id_user, $id_karyawan, $sale_time, $payment_method)
	{
		$errors = array();
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
		return $errors;
	}

	/**
	 * Posting jurnal POS. Return pesan error, atau NULL jika sukses.
	 */
	private function _post_checkout_journal($nota, $payment_method, $id_user, $sale_time, $total_tagihan, $diskon_voucher, $kode_voucher)
	{
		$this->load->library('accounting');
		$sale_totals = $this->db->query("
			SELECT COALESCE(SUM(jumlah), 0) AS gross_sales,
				COALESCE(SUM(jumlah * ppn), 0) AS tax_total,
				COALESCE(SUM(hpp * qty), 0) AS cogs_total
			FROM penjualan
			WHERE nota = ?
		", array($nota))->row();
		$payment_account = self::_payment_account($payment_method);
		$journal_lines = array();
		$payment_amount = round((float) $total_tagihan, 2);
		$discount_amount = round((float) $diskon_voucher, 2);
		$tax_amount = round((float) $sale_totals->tax_total, 2);
		$gross_amount = round((float) $sale_totals->gross_sales, 2);
		if ($discount_amount > 0) {
			$marketing_account = $this->db->get_where('acc_accounts', array(
				'code' => '6104',
				'type' => 'expense',
				'normal_balance' => 'debit',
				'is_active' => 1
			))->row();
			if (!$marketing_account) {
				return 'Akun beban pemasaran voucher 6104 belum tersedia. Jalankan application/sql/voucher_marketing_account.sql.';
			}
		}
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
				'account' => '6104',
				'debit' => $discount_amount,
				'credit' => 0,
				'memo' => 'Beban pemasaran voucher '.$kode_voucher.' untuk nota '.$nota
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
			return $this->accounting->error();
		}
		return NULL;
	}

	private function _respond_existing_checkout($request_id, $id_user, $id_karyawan)
	{
		$payment = $this->db->get_where('penjualan_pembayaran', array(
			'checkout_request_id' => $request_id
		))->row();
		if (!$payment) {
			$payment = $this->db->get_where('penjualan_pembayaran', array('nota' => $request_id))->row();
		}
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
			'nota' => $payment->nota,
			'total_tagihan' => (float) $payment->total_tagihan,
			'diskon_voucher' => (float) $payment->diskon_voucher,
			'kembalian' => (float) $payment->kembalian,
			'already_recorded' => TRUE,
		));
		return TRUE;
	}

	private function _next_receipt_number($npk, $sale_time)
	{
		$business_date = substr($sale_time, 0, 10);
		$updated = $this->db->query("
			INSERT INTO pos_receipt_sequences (business_date, npk, last_number)
			VALUES (?, ?, 1)
			ON DUPLICATE KEY UPDATE last_number = last_number + 1
		", array($business_date, $npk));
		if ($updated === FALSE) {
			return FALSE;
		}
		$sequence = $this->db->query("
			SELECT last_number FROM pos_receipt_sequences
			WHERE business_date = ? AND npk = ? FOR UPDATE
		", array($business_date, $npk))->row();
		if (!$sequence || (int) $sequence->last_number < 1) {
			return FALSE;
		}
		return $this->_format_receipt_number($npk, $business_date, (int) $sequence->last_number);
	}

	private function _format_receipt_number($npk, $business_date, $sequence)
	{
		$timestamp = strtotime($business_date);
		if ($timestamp === FALSE || (int) $sequence < 1) {
			return FALSE;
		}
		return (string) $npk.'-'.date('ymd', $timestamp).'-'.str_pad((string) (int) $sequence, 3, '0', STR_PAD_LEFT);
	}

	/**
	 * Validasi dan hitung diskon voucher.
	 * Return array: ['valid'=>bool, 'message'=>string, 'diskon'=>float, 'voucher_id'=>int|null]
	 */
	private function _apply_voucher($kode, $total_belanja, $npk = '')
	{
		$coupon_id = NULL;
		$coupon = NULL;
		if ($this->db->table_exists('voucher_coupons')) {
			$coupon = $this->db->get_where('voucher_coupons', array('serial_code' => strtoupper($kode)))->row();
		}
		if ($coupon) {
			if ($coupon->status !== 'available') {
				return array('valid' => FALSE, 'message' => 'Kupon ini sudah dipakai atau dibatalkan.', 'diskon' => 0, 'voucher_id' => NULL, 'coupon_id' => NULL);
			}
			if ($coupon->assigned_npk !== NULL && strtoupper((string) $coupon->assigned_npk) !== strtoupper((string) $npk)) {
				return array('valid' => FALSE, 'message' => 'Kupon ini diterbitkan untuk NPK lain.', 'diskon' => 0, 'voucher_id' => NULL, 'coupon_id' => NULL);
			}
			$v = $this->db->query("
				SELECT id, tipe, nilai, min_belanja, max_diskon, kuota, terpakai
				FROM vouchers
				WHERE id = ? AND is_active = 1
					AND tgl_mulai <= NOW() AND tgl_kadaluarsa >= NOW()
				FOR UPDATE
			", array((int) $coupon->voucher_id))->row();
			$coupon_id = (int) $coupon->id;
		} else {
			$v = $this->db->query("
				SELECT id, tipe, nilai, min_belanja, max_diskon, kuota, terpakai
				FROM vouchers
				WHERE kode = ? AND is_active = 1
					AND tgl_mulai <= NOW() AND tgl_kadaluarsa >= NOW()
				FOR UPDATE
			", array($kode))->row();
		}

		if (!$v) {
			return array('valid' => FALSE, 'message' => 'Voucher tidak valid atau sudah kedaluwarsa.', 'diskon' => 0, 'voucher_id' => NULL, 'coupon_id' => NULL);
		}
		if ((int)$v->kuota > 0 && (int)$v->terpakai >= (int)$v->kuota) {
			return array('valid' => FALSE, 'message' => 'Kuota voucher sudah habis.', 'diskon' => 0, 'voucher_id' => NULL, 'coupon_id' => NULL);
		}
		if (!$coupon && (int) $v->kuota > 0 && $this->db->table_exists('voucher_coupons')) {
			$reserved = $this->db->where('voucher_id', (int) $v->id)
				->where('status', 'available')->count_all_results('voucher_coupons');
			if ((int) $v->terpakai + (int) $reserved >= (int) $v->kuota) {
				return array('valid' => FALSE, 'message' => 'Sisa kuota voucher sudah dialokasikan ke kupon berserial.', 'diskon' => 0, 'voucher_id' => NULL, 'coupon_id' => NULL);
			}
		}
		if ($total_belanja < (float)$v->min_belanja) {
			return array('valid' => FALSE, 'message' => 'Minimum belanja untuk voucher ini Rp '.number_format($v->min_belanja).'.', 'diskon' => 0, 'voucher_id' => NULL, 'coupon_id' => NULL);
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

		return array('valid' => TRUE, 'message' => 'OK', 'diskon' => $diskon, 'voucher_id' => (int) $v->id, 'coupon_id' => $coupon_id);
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
		$npk = trim((string) $this->input->get('npk', TRUE));
		if ($kode === '') {
			$this->output->set_content_type('application/json')->set_output(json_encode(array('valid' => FALSE, 'message' => 'Kode voucher kosong.')));
			return;
		}
		$result = $this->_apply_voucher($kode, $total, $npk);
		$this->output->set_content_type('application/json')->set_output(json_encode(array(
			'valid'   => $result['valid'],
			'message' => $result['message'],
			'diskon'  => $result['diskon'],
		)));
	}

	public function eligible_vouchers()
	{
		if ($this->input->method(TRUE) !== 'GET') {
			show_404();
		}
		$this->load->database();
		$this->load->library(array('ion_auth', 'page_access'));
		if (!$this->ion_auth->logged_in() || !$this->page_access->can_access('penjualan')) {
			show_error('Anda tidak memiliki akses untuk melihat voucher penjualan.', 403);
		}
		$npk = $this->input->get('npk', TRUE);
		$npk = is_string($npk) ? trim($npk) : '';
		if ($npk === '' || strlen($npk) > 32 || !preg_match('/^[A-Za-z0-9_-]+$/D', $npk)) {
			$this->output->set_status_header(400);
			$this->output->set_content_type('application/json', 'utf-8')->set_output(json_encode(array('data' => array())));
			return;
		}
		$coupons = $this->db->query("
			SELECT coupon.serial_code, voucher.nama, voucher.nilai
			FROM voucher_coupons AS coupon
			JOIN vouchers AS voucher ON voucher.id = coupon.voucher_id
			WHERE coupon.assigned_npk = ? AND coupon.status = 'available'
				AND voucher.is_active = 1
				AND voucher.tgl_mulai <= NOW() AND voucher.tgl_kadaluarsa >= NOW()
				AND (voucher.kuota = 0 OR voucher.terpakai < voucher.kuota)
			ORDER BY coupon.id ASC
		", array($npk))->result_array();
		$this->output->set_content_type('application/json', 'utf-8')->set_output(json_encode(array('data' => $coupons)));
	}

	/**
	 * Peta kode akun kas/bank berdasarkan metode pembayaran.
	 * 1101 = kas tunai, 1102 = bank/EDC, 1103 = piutang karyawan (potong gaji).
	 */
	private static function _payment_account($payment_method)
	{
		switch ($payment_method) {
			case 'tunai':
			case 'cash':
				return '1101';
			case 'potong_gaji':
				return '1103';
			case 'edc':
			case 'transfer':
			case 'qris':
				return '1102';
			default:
				return '1102';
		}
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
