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
								AND return_header.status = 'completed'
						), 0) AS qty_sudah_diretur
					FROM penjualan sale
					LEFT JOIN stok stock ON stock.barcode = sale.kode
					WHERE sale.nota = ?
					ORDER BY sale.id ASC
				", array($nota))->result();
			}
		}
		$data['returns'] = $this->db->query("
			SELECT return_header.nomor_retur, return_header.nota_penjualan,
				return_header.tgl_retur, return_header.total_retur,
				return_header.id_user, COUNT(return_item.id) AS item_count
			FROM retur_penjualan return_header
			LEFT JOIN retur_penjualan_items return_item ON return_item.retur_id = return_header.id
			WHERE return_header.status = 'completed'
			GROUP BY return_header.id
			ORDER BY return_header.id DESC
			LIMIT 50
		")->result();
		$data['notice'] = $this->session->flashdata('sales_return_notice');
		$data['error'] = $this->session->flashdata('sales_return_error');
		$data['judul_besar'] = 'Retur Penjualan';
		$data['judul_kecil'] = 'Catat pengembalian barang berdasarkan nota asli';
		$data['m_sales_return'] = TRUE;
		$data['output'] = $this->load->view('sales_return', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function save()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

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
				WHERE return_item.penjualan_id = ? AND return_header.status = 'completed'
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
			'status' => 'completed'
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

			if ($return_item['restock']) {
				$stock = $this->db->query(
					'SELECT id FROM stok WHERE barcode = ? FOR UPDATE',
					array($sale_row->kode)
				)->row();
				if (!$stock) {
					$errors[] = 'Barang '.$sale_row->kode.' tidak ditemukan di stok; retur dibatalkan.';
					break;
				}
				$this->db->set('jumlah', 'jumlah + '.(int) $return_item['qty'], FALSE)
					->where('id', (int) $stock->id)
					->update('stok');
			}
		}

		if (count($errors) === 0) {
			$payment = $this->db->get_where('penjualan_pembayaran', array('nota' => $nota))->row();
			$payment_method = $payment
				? $payment->metode_pembayaran
				: $first_sale->metode_pembayaran;
			if (!in_array($payment_method, array('potong_gaji', 'tunai', 'qris', 'edc', 'transfer'), TRUE)) {
				$errors[] = 'Metode pembayaran nota asal tidak tersedia; retur dibatalkan agar jurnal tidak salah klasifikasi.';
			}
			$payment_account = $payment_method === 'tunai' ? '1101' :
				($payment_method === 'potong_gaji' ? '1103' : '1102');
			$net_return = 0;
			$tax_return = 0;
			$restocked_cost = 0;
			foreach ($return_items as $return_item) {
				$sale_row = $return_item['sale'];
				$line_tax = round(
					(float) $sale_row->harga * $return_item['qty'] * (float) $sale_row->ppn,
					2
				);
				$tax_return += $line_tax;
				$net_return += (float) $return_item['subtotal'] - $line_tax;
				if ($return_item['restock']) {
					$restocked_cost += (float) $sale_row->hpp * $return_item['qty'];
				}
			}
			$journal_lines = array();
			$net_return = round($net_return, 2);
			$tax_return = round($tax_return, 2);
			$net_return = round((float) $total_return - $tax_return, 2);
			if ($net_return > 0) {
				$journal_lines[] = array(
					'account' => '4201',
					'debit' => $net_return,
					'credit' => 0,
					'memo' => 'Retur penjualan '.$return_number
				);
			}
			if ($tax_return > 0) {
				$journal_lines[] = array(
					'account' => '2103',
					'debit' => $tax_return,
					'credit' => 0,
					'memo' => 'Pembalikan PPN keluaran retur '.$return_number
				);
			}
			if ($total_return > 0) {
				$journal_lines[] = array(
					'account' => $payment_account,
					'debit' => 0,
					'credit' => round($total_return, 2),
					'memo' => 'Pengembalian pembayaran retur '.$return_number
				);
			}
			$restocked_cost = round($restocked_cost, 2);
			if ($restocked_cost > 0) {
				$journal_lines[] = array(
					'account' => '1104',
					'debit' => $restocked_cost,
					'credit' => 0,
					'memo' => 'Barang layak jual retur '.$return_number
				);
				$journal_lines[] = array(
					'account' => '5101',
					'debit' => 0,
					'credit' => $restocked_cost,
					'memo' => 'Pembalikan HPP retur '.$return_number
				);
			}
			$this->load->library('accounting');
			if (count($errors) === 0 && !$this->accounting->post_journal(
				'SALES_RETURN',
				$return_id,
				substr($return_time, 0, 10),
				'Retur penjualan nota '.$nota,
				$operator_id,
				$journal_lines
			)) {
				log_message('error', 'Gagal memposting jurnal retur '.$return_number.': '.$this->accounting->error());
				$errors[] = 'Retur gagal disimpan karena jurnal akuntansi tidak dapat dibuat.';
			}
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
		$this->session->set_flashdata('sales_return_notice', 'Retur '.$return_number.' berhasil dicatat. Total pengembalian: Rp '.number_format($total_return, 0, ',', '.').'.');
		redirect('main/sales_return?nota='.rawurlencode($nota));
	}

	private function _is_positive_integer($value)
	{
		return (is_string($value) || is_numeric($value)) &&
			preg_match('/^[1-9][0-9]*$/', (string) $value);
	}

	private function _redirect_error($message)
	{
		$this->session->set_flashdata('sales_return_error', $message);
		redirect('main/sales_return');
	}
}
