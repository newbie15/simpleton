<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sales_return extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form', 'string'));
		$this->load->library(array('session', 'ion_auth'));
		$this->load->library('page_access');

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('sales_return')) {
			show_error('Anda tidak memiliki akses untuk menyelesaikan retur penjualan.', 403);
		}
	}

	public function index()
	{
		$nota = trim((string) $this->input->get('nota', TRUE));
		$data['nota'] = $nota;
		$data['sale'] = NULL;
		$data['items'] = array();
		if ($nota !== '' && strlen($nota) <= 64) {
			$data['sale'] = $this->db->query("
				SELECT sale.nota, sale.id_karyawan, MIN(sale.tgl) AS tgl,
					COALESCE(payment.metode_pembayaran, sale.metode_pembayaran) AS metode_pembayaran
				FROM penjualan sale
				LEFT JOIN penjualan_pembayaran payment ON payment.nota = sale.nota
				WHERE sale.nota = ?
				GROUP BY sale.nota, sale.id_karyawan, payment.metode_pembayaran, sale.metode_pembayaran
			", array($nota))->row();
			if ($data['sale']) {
				$data['items'] = $this->db->query("
					SELECT sale.id, sale.kode AS barcode, stock.nama,
						sale.qty AS qty_terjual, sale.harga, sale.ppn,
						COALESCE((
							SELECT SUM(return_item.qty_retur)
							FROM retur_penjualan_items return_item
							JOIN retur_penjualan return_header ON return_header.id = return_item.retur_id
							WHERE return_item.penjualan_id = sale.id
								AND return_header.status IN ('pending', 'approved', 'completed')
						), 0) AS qty_sudah_diretur
					FROM penjualan sale
					LEFT JOIN stok stock ON stock.barcode = sale.kode
					WHERE sale.nota = ?
					ORDER BY sale.id ASC
				", array($nota))->result();
			}
		}
		$data['notice'] = $this->session->flashdata('sales_return_notice');
		$data['error'] = $this->session->flashdata('sales_return_error');
		$data['form_token'] = $this->_form_token();
		$data['is_manager'] = $this->_is_manager();
		$data['judul_besar'] = 'Retur Penjualan';
		$data['judul_kecil'] = 'Pengajuan retur menunggu persetujuan manager';
		$data['m_sales_return'] = TRUE;
		$data['css_file'] = array(
			base_url('assets/adminlte/plugins/datatables/dataTables.bootstrap.css')
		);
		$data['js_file'] = array(
			base_url('assets/adminlte/plugins/datatables/jquery.dataTables.min.js'),
			base_url('assets/adminlte/plugins/datatables/dataTables.bootstrap.min.js'),
			base_url('assets/purchasing/sales_return.js?v=20261029-server-side')
		);
		$data['output'] = $this->load->view('sales_return', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function returns_data()
	{
		if ($this->input->method(TRUE) !== 'GET') {
			show_error('Metode permintaan tidak diizinkan.', 405);
		}

		$draw = $this->_datatable_integer($this->input->get('draw'), 0, 0, 2147483647);
		$start = $this->_datatable_integer($this->input->get('start'), 0, 0, 10000000);
		$length = $this->_datatable_integer($this->input->get('length'), 25, 1, 100);
		$search_data = $this->input->get('search');
		$search = is_array($search_data) && isset($search_data['value']) &&
			is_string($search_data['value']) ? substr(trim($search_data['value']), 0, 100) : '';
		$order_data = $this->input->get('order');
		$order_column = is_array($order_data) && isset($order_data[0]) &&
			is_array($order_data[0]) && isset($order_data[0]['column'])
			? $this->_datatable_integer($order_data[0]['column'], 0, 0, 7)
			: 0;
		$order_direction = is_array($order_data) && isset($order_data[0]) &&
			is_array($order_data[0]) && isset($order_data[0]['dir']) &&
			is_string($order_data[0]['dir']) && strtolower($order_data[0]['dir']) === 'asc'
			? 'ASC'
			: 'DESC';

		$base_where = " WHERE return_header.status IN ('pending', 'approved', 'rejected', 'completed')";
		$total = $this->db->query(
			'SELECT COUNT(*) AS total FROM retur_penjualan return_header'.$base_where
		)->row();
		$search_sql = '';
		$params = array();
		if ($search !== '') {
			$search_columns = array(
				'return_header.nomor_retur',
				'return_header.nota_penjualan',
				'return_header.tgl_retur',
				'return_header.id_user',
				'return_header.status',
				'return_header.rejection_reason',
				'return_header.approved_by',
				'return_header.rejected_by'
			);
			$search_sql = ' AND ('.implode(' LIKE ? OR ', $search_columns).' LIKE ?)';
			foreach ($search_columns as $column) {
				$params[] = '%'.$search.'%';
			}
		}
		$filtered = $search === ''
			? $total
			: $this->db->query(
				'SELECT COUNT(*) AS total FROM retur_penjualan return_header'.$base_where.$search_sql,
				$params
			)->row();

		$order_columns = array(
			0 => 'return_header.nomor_retur',
			1 => 'return_header.nota_penjualan',
			2 => 'return_header.tgl_retur',
			3 => 'return_header.id',
			4 => 'return_header.total_retur',
			5 => 'return_header.id_user',
			6 => 'return_header.status'
		);
		$order_by = isset($order_columns[$order_column])
			? $order_columns[$order_column]
			: 'return_header.id';
		$rows = $this->db->query("
			SELECT return_header.id, return_header.nomor_retur, return_header.nota_penjualan,
				return_header.tgl_retur, return_header.total_retur, return_header.id_user,
				return_header.status, return_header.rejection_reason,
				return_header.approved_by, return_header.rejected_by,
				(SELECT COUNT(*) FROM retur_penjualan_items return_item
					WHERE return_item.retur_id = return_header.id) AS item_count
			FROM retur_penjualan return_header
			".$base_where.$search_sql."
			ORDER BY ".$order_by.' '.$order_direction.', return_header.id DESC'.
			' LIMIT '.$length.' OFFSET '.$start,
			$params
		)->result();

		$data = array();
		foreach ($rows as $return) {
			$data[] = array(
				'id' => (int) $return->id,
				'nomor_retur' => $return->nomor_retur,
				'nota_penjualan' => $return->nota_penjualan,
				'tgl_retur' => $return->tgl_retur,
				'item_count' => (int) $return->item_count,
				'total_retur' => (float) $return->total_retur,
				'id_user' => $return->id_user,
				'status' => $return->status,
				'rejection_reason' => $return->rejection_reason,
				'approved_by' => $return->approved_by,
				'rejected_by' => $return->rejected_by,
				'action_html' => $this->_return_action_html($return)
			);
		}
		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: no-store, private')
			->set_output(json_encode(array(
				'draw' => $draw,
				'recordsTotal' => (int) $total->total,
				'recordsFiltered' => (int) $filtered->total,
				'data' => $data
			)));
	}

	public function view($return_id = NULL)
	{
		if (!$this->_is_positive_integer($return_id)) {
			show_404();
		}
		$return = $this->db->get_where('retur_penjualan', array('id' => (int) $return_id))->row();
		if (!$return) {
			show_404();
		}
		$items = $this->db->query("
			SELECT return_item.barcode, stock.nama AS product_name,
				return_item.qty_retur, return_item.harga_satuan, return_item.subtotal,
				return_item.kondisi, return_item.kembali_ke_stok
			FROM retur_penjualan_items return_item
			LEFT JOIN stok stock ON stock.barcode = return_item.barcode
			WHERE return_item.retur_id = ?
			ORDER BY return_item.id ASC
		", array((int) $return_id))->result();
		$data = array(
			'return' => $return,
			'items' => $items,
			'judul_besar' => 'Detail retur penjualan',
			'judul_kecil' => $return->nomor_retur,
			'm_sales_return' => TRUE
		);
		$data['output'] = $this->load->view('sales_return_view', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function save()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$this->_check_form_token();

		$nota = trim((string) $this->input->post('nota_penjualan', TRUE));
		$reason = trim((string) $this->input->post('alasan', TRUE));
		$posted_qty = $this->input->post('qty_retur');
		$posted_condition = $this->input->post('kondisi');
		$posted_restock = $this->input->post('kembali_ke_stok');
		if ($nota === '' || strlen($nota) > 64 || $reason === '' || strlen($reason) > 2000 ||
			!is_array($posted_qty) || !is_array($posted_condition)) {
			$this->_redirect_error('Nota, alasan, dan detail retur wajib diisi.');
		}
		if (!is_array($posted_restock)) {
			$posted_restock = array();
		}

		$selected = array();
		foreach ($posted_qty as $sale_id => $quantity) {
			if (!$this->_is_positive_integer($sale_id) ||
				(!is_string($quantity) && !is_numeric($quantity))) {
				$this->_redirect_error('Detail qty retur tidak valid.');
			}
			$quantity = trim((string) $quantity);
			if ($quantity === '0' || $quantity === '') {
				continue;
			}
			if (!$this->_is_positive_integer($quantity) || (float) $quantity > 2147483647 ||
				!isset($posted_condition[$sale_id]) ||
				!in_array($posted_condition[$sale_id], array('layak_jual', 'rusak'), TRUE)) {
				$this->_redirect_error('Qty retur atau kondisi barang tidak valid.');
			}
			$selected[(int) $sale_id] = array(
				'qty' => (int) $quantity,
				'condition' => $posted_condition[$sale_id],
				'restock' => $posted_condition[$sale_id] === 'layak_jual' &&
					isset($posted_restock[$sale_id]) && $posted_restock[$sale_id] === '1'
			);
		}
		if (count($selected) === 0 || count($selected) > 500) {
			$this->_redirect_error('Pilih minimal satu barang untuk diretur (maksimal 500 baris).');
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Database tidak dapat memulai penyimpanan retur.');
		}

		$sale_rows = $this->db->query("
			SELECT id, nota, id_karyawan, kode, harga, qty, ppn, hpp
			FROM penjualan
			WHERE nota = ?
			ORDER BY id ASC
			FOR UPDATE
		", array($nota))->result();
		$sale_by_id = array();
		foreach ($sale_rows as $sale_row) {
			$sale_by_id[(int) $sale_row->id] = $sale_row;
		}
		if (count($sale_by_id) === 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Nota penjualan tidak ditemukan.');
		}

		$return_items = array();
		$total_return = 0;
		$errors = array();
		foreach ($selected as $sale_id => $selection) {
			if (!isset($sale_by_id[$sale_id])) {
				$errors[] = 'Barang retur tidak berasal dari nota yang dipilih.';
				continue;
			}
			$sale_row = $sale_by_id[$sale_id];
			$returned_qty = $this->db->query("
				SELECT COALESCE(SUM(return_item.qty_retur), 0) AS qty_returned
				FROM retur_penjualan_items return_item
				JOIN retur_penjualan return_header ON return_header.id = return_item.retur_id
				WHERE return_item.penjualan_id = ?
					AND return_header.status IN ('pending', 'approved', 'completed')
			", array($sale_id))->row();
			$remaining_qty = (int) $sale_row->qty - (int) $returned_qty->qty_returned;
			if ($selection['qty'] > $remaining_qty) {
				$errors[] = 'Qty retur barcode '.$sale_row->kode.' melebihi sisa yang dapat diretur ('.$remaining_qty.').';
				continue;
			}

			$subtotal = round((float) $sale_row->harga * $selection['qty'] * (1 + (float) $sale_row->ppn), 2);
			$return_items[] = array(
				'sale' => $sale_row,
				'qty' => $selection['qty'],
				'condition' => $selection['condition'],
				'restock' => $selection['restock'],
				'subtotal' => $subtotal
			);
			$total_return += $subtotal;
		}
		if (count($errors) > 0 || count($return_items) !== count($selected)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error(implode(' ', $errors));
		}

		$first_sale = reset($sale_by_id);
		$operator_id = (string) $this->ion_auth->get_user_id();
		if ($operator_id === '' || strlen($operator_id) > 32) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			show_error('Identitas petugas tidak valid.', 500);
		}
		$return_time = date('Y-m-d H:i:s');
		$return_number = 'RET-'.date('Ymd-His').'-'.strtoupper(random_string('alnum', 4));
		$this->db->insert('retur_penjualan', array(
			'nomor_retur' => $return_number,
			'nota_penjualan' => $nota,
			'id_user' => $operator_id,
			'id_karyawan' => $first_sale->id_karyawan,
			'tgl_retur' => $return_time,
			'total_retur' => $total_return,
			'alasan' => $reason,
			'status' => 'pending'
		));
		$return_id = (int) $this->db->insert_id();

		foreach ($return_items as $return_item) {
			$sale_row = $return_item['sale'];
			if ($return_item['restock'] && ($sale_row->hpp === NULL || (float) $sale_row->hpp <= 0)) {
				$errors[] = 'HPP barcode '.$sale_row->kode.' tidak tersedia untuk mencatat nilai stok retur.';
				break;
			}
			$this->db->insert('retur_penjualan_items', array(
				'retur_id' => $return_id,
				'penjualan_id' => (int) $sale_row->id,
				'barcode' => $sale_row->kode,
				'qty_retur' => $return_item['qty'],
				'harga_satuan' => $sale_row->harga,
				'subtotal' => $return_item['subtotal'],
				'kondisi' => $return_item['condition'],
				'kembali_ke_stok' => $return_item['restock'] ? 1 : 0
			));
		}

		if (count($errors) > 0 || $this->db->trans_status() === FALSE) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			if (!empty($database_error['code'])) {
				log_message('error', 'Gagal menyimpan retur '.$return_number.': '.$database_error['code'].' '.$database_error['message']);
				$this->_redirect_error('Database gagal menyimpan retur. Tidak ada stok yang berubah.');
			}
			$this->_redirect_error(count($errors) > 0 ? implode(' ', $errors) : 'Retur gagal disimpan. Tidak ada stok yang berubah.');
		}
		if (!$this->db->trans_commit()) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal commit retur '.$return_number.': '.$database_error['code'].' '.$database_error['message']);
			$this->_redirect_error('Retur gagal disimpan. Periksa riwayat transaksi sebelum mencoba kembali.');
		}
		$this->db->db_debug = $previous_db_debug;
		$this->session->set_flashdata('sales_return_notice', 'Retur '.$return_number.' diajukan untuk persetujuan manager. Total pengajuan: Rp '.number_format($total_return, 0, ',', '.').'. Stok dan jurnal belum berubah.');
		redirect('main/sales_return?nota='.rawurlencode($nota));
	}

	public function approve($return_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$this->_check_form_token();
		if (!$this->_is_manager()) {
			show_error('Hanya admin atau management yang dapat menyetujui retur penjualan.', 403);
		}
		if (!$this->_is_positive_integer($return_id)) {
			show_404();
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if (!$this->db->trans_begin()) {
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Database tidak dapat memulai persetujuan retur.');
		}
		$return = $this->db->query('SELECT * FROM retur_penjualan WHERE id = ? FOR UPDATE', array((int) $return_id))->row();
		if (!$return || $return->status !== 'pending') {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Retur tidak ditemukan atau sudah diproses.');
		}

		$items = $this->db->query("
			SELECT return_item.id, return_item.penjualan_id, return_item.barcode,
				return_item.qty_retur, return_item.subtotal, return_item.kondisi,
				return_item.kembali_ke_stok, sale.nota, sale.id_karyawan,
				sale.harga, sale.qty AS qty_terjual, sale.ppn, sale.hpp, sale.metode_pembayaran
			FROM retur_penjualan_items return_item
			JOIN penjualan sale ON sale.id = return_item.penjualan_id
			WHERE return_item.retur_id = ? AND sale.nota = ?
			ORDER BY sale.id ASC
			FOR UPDATE
		", array((int) $return_id, $return->nota_penjualan))->result();
		if (empty($items)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Detail retur tidak ditemukan atau tidak cocok dengan nota asal.');
		}

		$errors = array();
		$total_return = 0;
		$tax_return = 0;
		$restocked_cost = 0;
		$approval_time = date('Y-m-d H:i:s');
		foreach ($items as $item) {
			$other_returns = $this->db->query("
				SELECT COALESCE(SUM(return_item.qty_retur), 0) AS qty_returned
				FROM retur_penjualan_items return_item
				JOIN retur_penjualan return_header ON return_header.id = return_item.retur_id
				WHERE return_item.penjualan_id = ? AND return_header.id <> ?
					AND return_header.status IN ('pending', 'approved', 'completed')
			", array((int) $item->penjualan_id, (int) $return_id))->row();
			$remaining = (int) $item->qty_terjual - (int) $other_returns->qty_returned;
			if ((int) $item->qty_retur > $remaining) {
				$errors[] = 'Qty retur barcode '.$item->barcode.' melebihi sisa setelah pengajuan lain ('.$remaining.').';
				continue;
			}
			if ((int) $item->kembali_ke_stok === 1 &&
				($item->kondisi !== 'layak_jual' || $item->hpp === NULL || (float) $item->hpp <= 0)) {
				$errors[] = 'HPP atau kondisi barang '.$item->barcode.' tidak valid untuk dikembalikan ke stok.';
				continue;
			}
			$total_return += (float) $item->subtotal;
			$tax_return += round((float) $item->harga * (int) $item->qty_retur * (float) $item->ppn, 2);
			if ((int) $item->kembali_ke_stok === 1) {
				$stock = $this->db->query('SELECT id FROM stok WHERE barcode = ? FOR UPDATE', array($item->barcode))->row();
				if (!$stock) {
					$errors[] = 'Barang '.$item->barcode.' tidak ditemukan di stok.';
					continue;
				}
				$updated = $this->db->set('jumlah', 'jumlah + '.(int) $item->qty_retur, FALSE)
					->where('id', (int) $stock->id)->update('stok');
				if (!$updated) {
					$errors[] = 'Stok barang '.$item->barcode.' gagal diperbarui.';
					continue;
				}
				$restocked_cost += (float) $item->hpp * (int) $item->qty_retur;
			}
		}
		if (!empty($errors)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error(implode(' ', $errors));
		}

		$payment = $this->db->get_where('penjualan_pembayaran', array('nota' => $return->nota_penjualan))->row();
		$payment_method = $payment ? $payment->metode_pembayaran : $items[0]->metode_pembayaran;
		if ($payment_method === 'cash') {
			$payment_method = 'tunai';
		}
		if (!in_array($payment_method, array('potong_gaji', 'tunai', 'qris', 'edc', 'transfer'), TRUE)) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Metode pembayaran nota asal tidak tersedia untuk jurnal retur.');
		}
		if ($payment_method === 'tunai' && $this->db->table_exists('acc_cash_funds') &&
			$this->db->table_exists('acc_cash_closings')) {
			$cashier_fund = $this->db->query("SELECT id FROM acc_cash_funds WHERE fund_slot = 'cashier' AND is_active = 1 FOR UPDATE")->row();
			$cashier_closed = $cashier_fund ? $this->db->get_where('acc_cash_closings', array(
				'fund_id' => (int) $cashier_fund->id,
				'closing_date' => substr($approval_time, 0, 10)
			))->row() : NULL;
			if ($cashier_closed) {
				$this->db->trans_rollback();
				$this->db->db_debug = $previous_db_debug;
				$this->_redirect_error('Kasir sudah ditutup hari ini; retur tunai belum dapat diposting.');
			}
		}

		$payment_account = $payment_method === 'tunai' ? '1101' :
			($payment_method === 'potong_gaji' ? '1103' : '1102');
		$journal_lines = array();
		$net_return = round((float) $total_return - $tax_return, 2);
		if ($net_return > 0) {
			$journal_lines[] = array('account' => '4201', 'debit' => $net_return, 'credit' => 0,
				'memo' => 'Retur penjualan '.$return->nomor_retur);
		}
		if ($tax_return > 0) {
			$journal_lines[] = array('account' => '2103', 'debit' => $tax_return, 'credit' => 0,
				'memo' => 'Pembalikan PPN keluaran retur '.$return->nomor_retur);
		}
		if ($total_return > 0) {
			$journal_lines[] = array('account' => $payment_account, 'debit' => 0,
				'credit' => round($total_return, 2), 'memo' => 'Pengembalian pembayaran retur '.$return->nomor_retur);
		}
		$restocked_cost = round($restocked_cost, 2);
		if ($restocked_cost > 0) {
			$journal_lines[] = array('account' => '1104', 'debit' => $restocked_cost, 'credit' => 0,
				'memo' => 'Barang layak jual retur '.$return->nomor_retur);
			$journal_lines[] = array('account' => '5101', 'debit' => 0, 'credit' => $restocked_cost,
				'memo' => 'Pembalikan HPP retur '.$return->nomor_retur);
		}
		$this->load->library('accounting');
		if (!$this->accounting->post_journal('SALES_RETURN', (string) $return_id,
			substr($approval_time, 0, 10), 'Retur penjualan nota '.$return->nota_penjualan,
			(string) $this->ion_auth->get_user_id(), $journal_lines)) {
			$error = $this->accounting->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal menyetujui retur '.$return->nomor_retur.': '.$error);
			$this->_redirect_error('Retur gagal disetujui: '.$error);
		}
		$updated = $this->db->where('id', (int) $return_id)->where('status', 'pending')->update('retur_penjualan', array(
			'status' => 'approved',
			'approved_by' => (string) $this->ion_auth->get_user_id(),
			'approved_at' => $approval_time
		));
		if (!$updated || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			log_message('error', 'Gagal commit persetujuan retur '.$return->nomor_retur.': '.$database_error['code'].' '.$database_error['message']);
			$this->_redirect_error('Retur tidak berhasil disetujui; perubahan stok dan jurnal dibatalkan.');
		}
		$this->db->db_debug = $previous_db_debug;
		$this->session->set_flashdata('sales_return_notice', 'Retur '.$return->nomor_retur.' disetujui; stok dan jurnal telah diperbarui.');
		redirect('main/sales_return?nota='.rawurlencode($return->nota_penjualan));
	}

	public function reject($return_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$this->_check_form_token();
		if (!$this->_is_manager()) {
			show_error('Hanya admin atau management yang dapat menolak retur penjualan.', 403);
		}
		if (!$this->_is_positive_integer($return_id)) {
			show_404();
		}
		$reason = trim((string) $this->input->post('rejection_reason', TRUE));
		if ($reason === '' || strlen($reason) > 500) {
			$this->_redirect_error('Alasan penolakan wajib diisi, maksimal 500 karakter.');
		}
		$this->db->trans_begin();
		$return = $this->db->query('SELECT * FROM retur_penjualan WHERE id = ? FOR UPDATE', array((int) $return_id))->row();
		if (!$return || $return->status !== 'pending') {
			$this->db->trans_rollback();
			$this->_redirect_error('Retur tidak ditemukan atau sudah diproses.');
		}
		$updated = $this->db->where('id', (int) $return_id)->where('status', 'pending')->update('retur_penjualan', array(
			'status' => 'rejected',
			'rejected_by' => (string) $this->ion_auth->get_user_id(),
			'rejected_at' => date('Y-m-d H:i:s'),
			'rejection_reason' => $reason
		));
		if (!$updated || $this->db->trans_status() === FALSE || !$this->db->trans_commit()) {
			$this->db->trans_rollback();
			$this->_redirect_error('Retur gagal ditolak.');
		}
		$this->session->set_flashdata('sales_return_notice', 'Retur '.$return->nomor_retur.' ditolak; tidak ada stok atau jurnal yang berubah.');
		redirect('main/sales_return?nota='.rawurlencode($return->nota_penjualan));
	}

	private function _is_positive_integer($value)
	{
		return (is_string($value) || is_numeric($value)) &&
			preg_match('/^[1-9][0-9]*$/', (string) $value);
	}

	private function _datatable_integer($value, $default, $minimum, $maximum)
	{
		if (!is_string($value) && !is_int($value)) {
			return $default;
		}
		$value = (string) $value;
		if (!ctype_digit($value)) {
			return $default;
		}
		return max($minimum, min($maximum, (int) $value));
	}

	private function _return_action_html($return)
	{
		$view_url = site_url('main/sales_return/view/'.(int) $return->id);
		$view_button = '<a class="btn btn-xs btn-info" href="'.html_escape($view_url).'"><i class="fa fa-eye" aria-hidden="true"></i> Lihat</a>';
		if ($return->status === 'pending' && $this->_is_manager()) {
			$token = html_escape($this->_form_token());
			$id = (int) $return->id;
			$approve_url = site_url('main/sales_return/approve/'.$id);
			$reject_url = site_url('main/sales_return/reject/'.$id);
			return $view_button.'<div style="margin-top:5px"></div><form method="post" action="'.html_escape($approve_url).'" style="margin-bottom:5px;" onsubmit="return window.confirm(\'Setujui retur ini? Stok dan jurnal akan diperbarui.\');">'.
				'<input type="hidden" name="form_token" value="'.$token.'">'.
				'<button class="btn btn-xs btn-success" type="submit">Setujui</button></form>'.
				'<form method="post" action="'.html_escape($reject_url).'">'.
				'<input type="hidden" name="form_token" value="'.$token.'">'.
				'<div class="input-group input-group-sm"><input class="form-control" name="rejection_reason" maxlength="500" placeholder="Alasan penolakan" required><span class="input-group-btn"><button class="btn btn-danger" type="submit">Tolak</button></span></div></form>';
		}
		if ($return->status === 'pending') {
			return $view_button.'<div style="margin-top:5px"></div><span class="text-muted">Menunggu manager</span>';
		}
		if ($return->approved_by) {
			return $view_button.'<br><small>Disetujui oleh '.html_escape($return->approved_by).'</small>';
		}
		if ($return->rejected_by) {
			return $view_button.'<br><small>Ditolak oleh '.html_escape($return->rejected_by).'</small>';
		}
		return $view_button;
	}

	private function _is_manager()
	{
		return $this->ion_auth->in_group(array('admin', 'management'));
	}

	private function _form_token()
	{
		$token = $this->session->userdata('sales_return_form_token');
		if (!is_string($token) || $token === '') {
			$token = random_string('alnum', 32);
			$this->session->set_userdata('sales_return_form_token', $token);
		}
		return $token;
	}

	private function _check_form_token()
	{
		$expected = $this->session->userdata('sales_return_form_token');
		$posted = $this->input->post('form_token', TRUE);
		if (!is_string($expected) || $expected === '' || !is_string($posted) || $posted !== $expected) {
			show_error('Sesi formulir retur tidak valid. Muat ulang halaman sebelum mencoba lagi.', 403);
		}
	}

	private function _redirect_error($message)
	{
		$this->session->set_flashdata('sales_return_error', $message);
		redirect('main/sales_return');
	}
}
