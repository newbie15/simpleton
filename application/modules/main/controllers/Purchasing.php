<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchasing extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'string'));
		$this->load->library('session');
	}

	public function index()
	{
		$this->load->library('grocery_CRUD');
		$crud = new grocery_CRUD();
		$crud->set_table('purchase_order_list');
		$crud->set_primary_key('id');
		$crud->order_by('id', 'desc');
		$crud->set_subject('Purchase Order');
		$crud->columns(
			'order_number',
			'ordered_at',
			'supplier_name',
			'ordered_qty',
			'received_qty',
			'receipt_status',
			'printed_at',
			'id'
		);
		$crud->display_as('order_number', 'No. PO');
		$crud->display_as('ordered_at', 'Tanggal');
		$crud->display_as('supplier_name', 'Supplier');
		$crud->display_as('ordered_qty', 'Qty PO');
		$crud->display_as('received_qty', 'Qty diterima');
		$crud->display_as('receipt_status', 'Status penerimaan');
		$crud->display_as('printed_at', 'Dokumen');
		$crud->display_as('id', 'Aksi');
		$crud->unset_operations();
		$crud->callback_column('printed_at', array($this, '_callback_order_document'));
		$crud->callback_column('id', array($this, '_callback_order_actions'));

		$output = $crud->render();
		$notice = $this->session->flashdata('purchasing_notice');
		$error = $this->session->flashdata('purchasing_error');
		$toolbar = '<div class="row"><div class="col-md-12"><div class="box box-default"><div class="box-body">'
			.'<a class="btn btn-primary" href="'.site_url('main/purchasing/create').'">Buat Purchase Order</a> '
			.'<a class="btn btn-default" href="'.site_url('main/purchasing/suppliers').'">Kelola Supplier</a>'
			.'</div></div></div></div>';
		if (!empty($notice)) {
			$toolbar = '<div class="alert alert-success">'.html_escape($notice).'</div>'.$toolbar;
		}
		if (!empty($error)) {
			$toolbar = '<div class="alert alert-danger">'.html_escape($error).'</div>'.$toolbar;
		}
		$output->output = $toolbar.$output->output;
		$output->judul_besar = 'Purchasing';
		$output->judul_kecil = 'Daftar purchase order';
		$output->m_purchasing = TRUE;
		$this->load->view('welcome_message', $output);
	}

	public function _callback_order_document($value, $row)
	{
		$status = empty($value) ? 'Draft' : 'Sudah dicetak';
		$url = site_url('main/purchasing/print_order/'.(int) $row->id);
		return html_escape($status)
			.'<br><form method="post" action="'.$url.'" style="display:inline">'
			.'<button class="btn btn-xs btn-primary" type="submit">'
			.(empty($value) ? 'Cetak PDF' : 'Cetak Ulang PDF')
			.'</button></form>';
	}

	public function _callback_order_actions($value, $row)
	{
		$order_id = (int) $row->id;
		$actions = '';
		if ((int) $row->received_qty === 0) {
			$actions .= '<a class="btn btn-xs btn-default" href="'
				.site_url('main/purchasing/edit/'.$order_id).'">Edit</a> ';
		}
		$actions .= '<a class="btn btn-xs btn-info" href="'
			.site_url('main/purchasing/receive/'.$order_id).'">Bandingkan / Terima</a>';
		return $actions;
	}

	public function create()
	{
		$this->_render_order_form(NULL);
	}

	public function edit($order_id = NULL)
	{
		$order = $this->_get_order($order_id);
		if (!$order) {
			show_404();
		}
		if ($this->_order_has_receipts((int) $order_id)) {
			$this->session->set_flashdata('purchasing_error', 'Purchase order tidak dapat diedit setelah penerimaan barang mulai dicatat.');
			redirect('main/purchasing');
		}
		$this->_render_order_form($order);
	}

	private function _render_order_form($order)
	{
		$data['suppliers'] = $this->db
			->select('id, name')
			->from('suppliers')
			->order_by('name', 'ASC')
			->get()
			->result();
		$data['order'] = $order;
		$data['items'] = $order ? $this->_get_order_items((int) $order->id) : array();
		$data['notice'] = $this->session->flashdata('purchasing_notice');
		$data['error'] = $this->session->flashdata('purchasing_error');
		$data['page'] = 'create';
		$this->_render_page($data);
	}

	public function print_order($order_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$order = $this->_get_order($order_id);
		if (!$order) {
			show_404();
		}
		if (!$this->db->where('id', (int) $order_id)
			->update('purchase_orders', array('printed_at' => date('Y-m-d H:i:s')))) {
			$this->_log_database_error('Gagal menandai purchase order '.$order->order_number.' sudah dicetak');
			show_error('Status cetak purchase order gagal diperbarui.');
		}

		$data['order'] = $order;
		$data['items'] = $this->_get_order_items((int) $order_id);
		$this->load->view('purchase_order_print', $data);
	}

	public function product_search()
	{
		$term = $this->input->get('q');
		if (!is_string($term)) {
			$this->_json_response(array('products' => array()));
		}
		$term = trim($term);
		if ($term === '' || strlen($term) > 100) {
			$this->_json_response(array('products' => array()));
		}

		$exact_barcode_order = 'CASE WHEN barcode = '.$this->db->escape($term).' THEN 0 ELSE 1 END';
		$products = $this->db
			->select('barcode, nama, harga')
			->from('stok')
			->group_start()
				->like('barcode', $term)
				->or_like('nama', $term)
			->group_end()
			->order_by($exact_barcode_order, '', FALSE)
			->order_by('nama', 'ASC')
			->limit(20)
			->get()
			->result();
		$this->_json_response(array('products' => $products));
	}

	public function import_excel()
	{
		if ($this->input->method(TRUE) !== 'POST' || !isset($_FILES['file']) ||
			!is_array($_FILES['file']) ||
			!isset($_FILES['file']['tmp_name'], $_FILES['file']['name'], $_FILES['file']['error'], $_FILES['file']['size']) ||
			!is_string($_FILES['file']['tmp_name']) || !is_string($_FILES['file']['name']) ||
			!is_uploaded_file($_FILES['file']['tmp_name'])) {
			$this->_json_response(array('error' => 'Pilih file Excel yang valid.'), 400);
		}

		$file = $_FILES['file'];
		if (!is_numeric($file['error']) || !is_numeric($file['size']) ||
			(int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 5242880) {
			$this->_json_response(array('error' => 'File harus berformat .xls, .xlsx, atau .csv dan maksimal 5 MB.'), 400);
		}
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($extension, array('xls', 'xlsx', 'csv'), TRUE)) {
			$this->_json_response(array('error' => 'File harus berformat .xls, .xlsx, atau .csv dan maksimal 5 MB.'), 400);
		}

		try {
			$this->load->library('excel');
			$workbook = PHPExcel_IOFactory::load($file['tmp_name']);
			$sheet = $workbook->getActiveSheet();
			$last_row = $sheet->getHighestRow();
			if ($last_row > 501) {
				$this->_json_response(array('error' => 'File berisi lebih dari 500 baris barang.'), 400);
			}

			$rows = array();
			for ($row_number = 1; $row_number <= $last_row; $row_number++) {
				$row = array();
				for ($column = 0; $column < 3; $column++) {
					$row[] = trim((string) $sheet->getCellByColumnAndRow($column, $row_number)->getFormattedValue());
				}
				if (count($rows) === 0 && strtolower($row[0]) === 'barcode') {
					continue;
				}
				if (implode('', $row) === '') {
					continue;
				}
				$rows[] = $row;
			}
			if (count($rows) === 0) {
				$this->_json_response(array('error' => 'File tidak berisi baris barang.'), 400);
			}
			$this->_json_response(array('rows' => $rows));
		} catch (Exception $exception) {
			log_message('error', 'Gagal membaca file purchasing: '.$exception->getMessage());
			$this->_json_response(array('error' => 'File tidak dapat dibaca. Pastikan file Excel tidak rusak dan memakai format kolom yang ditentukan.'), 400);
		}
	}

	private function _json_response($payload, $status = 200)
	{
		$this->output->set_status_header($status);
		$this->output->set_content_type('application/json', 'utf-8');
		echo json_encode($payload);
		exit;
	}

	public function suppliers()
	{
		$this->load->library('grocery_CRUD');
		$crud = new grocery_CRUD();
		$crud->set_table('suppliers');
		$crud->set_subject('Supplier');
		$crud->columns('name', 'phone', 'address', 'created_at');
		$crud->fields('name', 'phone', 'address');
		$crud->display_as('name', 'Nama Supplier');
		$crud->display_as('phone', 'No. Telepon');
		$crud->display_as('address', 'Alamat');
		$crud->display_as('created_at', 'Tanggal Dibuat');
		$crud->set_rules('name', 'Nama Supplier', 'required|max_length[64]');
		$crud->set_rules('phone', 'No. Telepon', 'max_length[32]');
		$crud->callback_before_insert(array($this, '_set_supplier_created_at'));
		$crud->callback_before_delete(array($this, '_can_delete_supplier'));

		$output = $crud->render();
		$output->judul_besar = 'Supplier';
		$output->judul_kecil = 'Tambah, ubah, dan hapus data supplier';
		$output->m_supplier = TRUE;
		$this->load->view('welcome_message', $output);
	}

	public function _set_supplier_created_at($post_array)
	{
		$post_array['created_at'] = date('Y-m-d H:i:s');
		return $post_array;
	}

	public function _can_delete_supplier($supplier_id)
	{
		$has_orders = $this->db
			->where('supplier_id', (int) $supplier_id)
			->count_all_results('purchase_orders') > 0;

		if ($has_orders) {
			log_message('error', 'Supplier '.$supplier_id.' tidak dapat dihapus karena sudah digunakan pada purchase order.');
			return FALSE;
		}

		return TRUE;
	}

	public function save()
	{
		$this->_save_order(NULL);
	}

	public function update($order_id = NULL)
	{
		if (!is_numeric($order_id) || (int) $order_id < 1 || !$this->_get_order($order_id)) {
			show_404();
		}
		$this->_save_order((int) $order_id);
	}

	private function _save_order($order_id)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$return_uri = $order_id ? 'main/purchasing/edit/'.$order_id : 'main/purchasing/create';
		$supplier_id = $this->input->post('supplier_id');
		$notes = $this->input->post('notes');
		if (!is_string($supplier_id) || !is_string($notes) ||
			!$this->_is_positive_integer($supplier_id)) {
			$this->_redirect_with_error('Data supplier atau catatan PO tidak valid.', $return_uri);
		}
		$supplier_id = (int) $supplier_id;
		$notes = trim($notes);
		$items_json = $this->input->post('items_json', FALSE);
		$posted_items = is_string($items_json) ? json_decode($items_json, TRUE) : NULL;
		if (!is_array($posted_items) || count($posted_items) > 500) {
			$this->_redirect_with_error('Detail barang tidak valid atau melebihi 500 baris.', $return_uri);
		}

		$items = array();
		$seen_barcodes = array();
		foreach ($posted_items as $row) {
			if (!is_array($row) || count($row) < 3) {
				$this->_redirect_with_error('Format baris barang tidak valid.', $return_uri);
			}
			$barcode = trim($this->_order_value_string($row[0]));
			$name = trim($this->_order_value_string($row[1]));
			$qty = trim($this->_order_value_string($row[2]));

			if ($barcode === '' && $name === '' && $qty === '') {
				continue;
			}

			if ($barcode === '' || strlen($barcode) > 64 ||
				!$this->_is_positive_integer($qty)) {
				$this->_redirect_with_error('Barcode atau jumlah permintaan tidak valid.', $return_uri);
			}

			if (isset($seen_barcodes[$barcode])) {
				$this->_redirect_with_error('Barcode yang sama hanya boleh dicantumkan sekali dalam satu PO.', $return_uri);
			}
			$seen_barcodes[$barcode] = TRUE;

			$product_query = $this->db->get_where('stok', array('barcode' => $barcode));
			if ($product_query->num_rows() > 1) {
				$this->_redirect_with_error('Barcode '.$barcode.' terduplikasi di master stok. Perbaiki data stok terlebih dahulu.', $return_uri);
			}

			$is_new_product = $product_query->num_rows() === 0;
			if ($is_new_product && ($name === '' || strlen($name) > 64)) {
				$this->_redirect_with_error('Barang baru harus memiliki nama (maksimal 64 karakter).', $return_uri);
			}

			$items[] = array(
				'barcode' => $barcode,
				'name' => $is_new_product ? $name : $product_query->row()->nama,
				'qty' => (int) $qty,
				'is_new' => $is_new_product,
				'product_id' => $is_new_product ? NULL : (int) $product_query->row()->id
			);
		}

		if (count($items) === 0) {
			$this->_redirect_with_error('Tambahkan minimal satu barang ke purchase order.', $return_uri);
		}

		$supplier_query = $this->db->get_where('suppliers', array('id' => $supplier_id));
		if ($supplier_query->num_rows() !== 1) {
			$this->_redirect_with_error('Supplier tidak ditemukan. Pilih supplier yang terdaftar.', $return_uri);
		}
		$supplier_name = $supplier_query->row()->name;

		$this->db->trans_begin();
		if ($order_id) {
			$this->db->query('SELECT id FROM purchase_orders WHERE id = ? FOR UPDATE', array($order_id));
			if ($this->_order_has_receipts($order_id)) {
				$this->db->trans_rollback();
				$this->_redirect_with_error('Purchase order tidak dapat diedit setelah penerimaan barang mulai dicatat.', 'main/purchasing');
			}
			$order = $this->_get_order($order_id);
			if (!$order) {
				$this->db->trans_rollback();
				show_404();
			}
			$order_number = $order->order_number;
			$this->db->where('id', $order_id)->update('purchase_orders', array(
				'supplier_id' => $supplier_id,
				'notes' => $notes,
				'printed_at' => NULL
			));
			$this->db->where('purchase_order_id', $order_id)->delete('purchase_order_items');
		} else {
			$order_number = 'PO-'.date('Ymd-His').'-'.strtoupper(random_string('alnum', 4));
			$this->db->insert('purchase_orders', array(
				'order_number' => $order_number,
				'supplier_id' => $supplier_id,
				'ordered_at' => date('Y-m-d H:i:s'),
				'printed_at' => NULL,
				'notes' => $notes
			));
			$order_id = (int) $this->db->insert_id();
		}

		foreach ($items as $item) {
			if ($item['is_new']) {
				$this->db->insert('stok', array(
					'barcode' => $item['barcode'],
					'nama' => $item['name'],
					'jumlah' => 0,
					'harga' => 0,
					'distributor' => $supplier_name
				));
				$item['product_id'] = (int) $this->db->insert_id();
			}

			$this->db->insert('purchase_order_items', array(
				'purchase_order_id' => $order_id,
				'product_id' => $item['product_id'],
				'barcode' => $item['barcode'],
				'product_name' => $item['name'],
				'qty_ordered' => $item['qty'],
				'purchase_price' => 0
			));
		}

		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal menyimpan purchase order '.$order_number);
			$this->db->trans_rollback();
			$this->_redirect_with_error('Purchase order gagal disimpan. Tidak ada stok yang ditambahkan.', $return_uri);
		}

		$this->db->trans_commit();
		$this->session->set_flashdata('purchasing_notice', 'Purchase order '.$order_number.' berhasil disimpan. Stok belum bertambah sampai barang diterima.');
		redirect('main/purchasing/edit/'.$order_id);
	}

	private function _order_value_string($value)
	{
		return is_string($value) || is_numeric($value)
			? html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8')
			: '';
	}

	public function receive($order_id = NULL)
	{
		$order = $this->_get_order($order_id);
		if (!$order) {
			show_404();
		}

		$data['order'] = $order;
		$data['items'] = $this->_get_order_items((int) $order_id);
		$receipt_rows = $this->db
			->select('gr.id, gr.receipt_number, gr.received_at, gr.supplier_document, gr.notes, '
				.'gr.ppn_nominal, gri.qty_received, gri.unit_price, gri.discount_type, '
				.'gri.discount_value, gri.discount_amount, gri.subtotal, '
				.'poi.id AS order_item_id, poi.barcode, poi.product_name, poi.qty_ordered')
			->from('goods_receipts gr')
			->join('goods_receipt_items gri', 'gri.goods_receipt_id = gr.id')
			->join('purchase_order_items poi', 'poi.id = gri.purchase_order_item_id')
			->where('gr.purchase_order_id', (int) $order_id)
			->order_by('gr.id', 'ASC')
			->order_by('poi.id', 'ASC')
			->get()
			->result();
		$receipts = array();
		$cumulative_by_item = array();
		foreach ($receipt_rows as $receipt_row) {
			$receipt_id = (int) $receipt_row->id;
			if (!isset($receipts[$receipt_id])) {
				$receipt = new stdClass();
				$receipt->id = $receipt_id;
				$receipt->receipt_number = $receipt_row->receipt_number;
				$receipt->received_at = $receipt_row->received_at;
				$receipt->supplier_document = $receipt_row->supplier_document;
				$receipt->notes = $receipt_row->notes;
				$receipt->total_qty = 0;
				$receipt->subtotal_amount = 0;
				$receipt->ppn_nominal = (float) $receipt_row->ppn_nominal;
				$receipt->total_amount = 0;
				$receipt->has_unpriced_items = FALSE;
				$receipt->items = array();
				$receipts[$receipt_id] = $receipt;
			}

			$order_item_id = (int) $receipt_row->order_item_id;
			$qty_before = isset($cumulative_by_item[$order_item_id])
				? $cumulative_by_item[$order_item_id]
				: 0;
			$qty_this_receipt = (int) $receipt_row->qty_received;
			$qty_after = $qty_before + $qty_this_receipt;
			$cumulative_by_item[$order_item_id] = $qty_after;

			$item = new stdClass();
			$item->barcode = $receipt_row->barcode;
			$item->product_name = $receipt_row->product_name;
			$item->qty_ordered = (int) $receipt_row->qty_ordered;
			$item->qty_before = $qty_before;
			$item->qty_received = $qty_this_receipt;
			$item->qty_after = $qty_after;
			$item->unit_price = $receipt_row->unit_price === NULL ? NULL : (int) $receipt_row->unit_price;
			$item->discount_type = $receipt_row->discount_type;
			$item->discount_value = (float) $receipt_row->discount_value;
			$item->discount_amount = (float) $receipt_row->discount_amount;
			$gross_total = $item->unit_price === NULL ? NULL : $qty_this_receipt * $item->unit_price;
			$item->line_total = $gross_total === NULL ? NULL :
				((float) $receipt_row->subtotal > 0 || $gross_total === 0
					? (float) $receipt_row->subtotal
					: max(0, $gross_total - $item->discount_amount));
			$receipts[$receipt_id]->items[] = $item;
			$receipts[$receipt_id]->total_qty += $qty_this_receipt;
			if ($item->line_total === NULL) {
				$receipts[$receipt_id]->has_unpriced_items = TRUE;
			} else {
				$receipts[$receipt_id]->subtotal_amount += $item->line_total;
			}
		}
		foreach ($receipts as $receipt) {
			$receipt->total_amount = $receipt->subtotal_amount + $receipt->ppn_nominal;
		}
		$data['receipts'] = array_reverse(array_values($receipts));
		$data['notice'] = $this->session->flashdata('purchasing_notice');
		$data['error'] = $this->session->flashdata('purchasing_error');
		$data['page'] = 'receive';
		$this->_render_page($data);
	}

	public function save_receipt($order_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$order = $this->_get_order($order_id);
		if (!$order) {
			show_404();
		}

		$quantities = $this->input->post('qty_received');
		$unit_prices = $this->input->post('unit_price');
		$discount_types = $this->input->post('discount_type');
		$discount_values = $this->input->post('discount_value');
		$ppn_nominal = $this->input->post('ppn_nominal');
		$supplier_document = $this->input->post('supplier_document');
		$notes = $this->input->post('receipt_notes');
		if (!is_array($quantities) || !is_array($unit_prices) || !is_string($supplier_document) ||
			!is_array($discount_types) || !is_array($discount_values) ||
			!is_string($ppn_nominal) || !$this->_is_non_negative_amount(trim($ppn_nominal)) ||
			!is_string($notes) || strlen($supplier_document) > 64) {
			$this->_redirect_with_error('Data penerimaan tidak valid.', 'main/purchasing/receive/'.$order_id);
		}
		$ppn_nominal = round((float) trim($ppn_nominal), 2);
		$supplier_document = trim($supplier_document);
		$notes = trim($notes);
		if ($supplier_document === '') {
			$this->_redirect_with_error('Nomor surat jalan wajib diisi sebelum penerimaan disimpan.', 'main/purchasing/receive/'.$order_id);
		}

		$order_items = $this->_get_order_items((int) $order_id);
		$received_items = array();
		foreach ($order_items as $item) {
			$key = (string) $item->id;
			$qty = isset($quantities[$key]) ? trim((string) $quantities[$key]) : '0';
			if (!$this->_is_non_negative_integer($qty)) {
				$this->_redirect_with_error('Jumlah diterima harus berupa bilangan bulat nol atau lebih.', 'main/purchasing/receive/'.$order_id);
			}
			if ((int) $qty > 0) {
				$unit_price = isset($unit_prices[$key]) && is_string($unit_prices[$key])
					? trim($unit_prices[$key])
					: '';
				if (!$this->_is_non_negative_integer($unit_price)) {
					$this->_redirect_with_error('Harga satuan harus diisi dengan bilangan bulat nol atau lebih untuk setiap barang yang diterima.', 'main/purchasing/receive/'.$order_id);
				}
				$discount_type = isset($discount_types[$key]) && is_string($discount_types[$key])
					? $discount_types[$key]
					: '';
				$discount_value = isset($discount_values[$key]) && is_string($discount_values[$key])
					? trim($discount_values[$key])
					: '';
				if (!in_array($discount_type, array('none', 'percent', 'nominal'), TRUE) ||
					!$this->_is_non_negative_amount($discount_value)) {
					$this->_redirect_with_error('Jenis atau nilai diskon barang tidak valid.', 'main/purchasing/receive/'.$order_id);
				}
				$discount_value = round((float) $discount_value, 2);
				$gross_total = (float) $qty * (int) $unit_price;
				if ($gross_total > 9999999999999.99) {
					$this->_redirect_with_error('Subtotal barang melampaui batas nilai yang dapat disimpan.', 'main/purchasing/receive/'.$order_id);
				}
				if ($discount_type === 'none') {
					$discount_value = 0;
					$discount_amount = 0;
				} elseif ($discount_type === 'percent') {
					if ($discount_value > 100) {
						$this->_redirect_with_error('Diskon persen harus berada antara 0 dan 100.', 'main/purchasing/receive/'.$order_id);
					}
					$discount_amount = round($gross_total * $discount_value / 100, 2);
				} else {
					$discount_amount = $discount_value;
					if ($discount_amount > $gross_total) {
						$this->_redirect_with_error('Diskon nominal tidak boleh melebihi nilai kotor barang.', 'main/purchasing/receive/'.$order_id);
					}
				}
				$line_subtotal = round($gross_total - $discount_amount, 2);
				$received_items[] = array(
					'item' => $item,
					'qty' => (int) $qty,
					'unit_price' => (int) $unit_price,
					'discount_type' => $discount_type,
					'discount_value' => $discount_value,
					'discount_amount' => $discount_amount,
					'subtotal' => $line_subtotal
				);
			}
		}

		if (count($received_items) === 0) {
			$this->_redirect_with_error('Masukkan jumlah barang yang diterima minimal untuk satu item.', 'main/purchasing/receive/'.$order_id);
		}
		$receipt_subtotal = 0;
		foreach ($received_items as $received) {
			$receipt_subtotal += $received['subtotal'];
		}
		if ($receipt_subtotal + $ppn_nominal > 9999999999999.99) {
			$this->_redirect_with_error('Total faktur melampaui batas nilai yang dapat disimpan.', 'main/purchasing/receive/'.$order_id);
		}

		$this->db->trans_begin();
		$this->db->query('SELECT id FROM purchase_orders WHERE id = ? FOR UPDATE', array((int) $order_id));

		$received_timestamp = time();
		$received_at = date('Y-m-d H:i:s', $received_timestamp);
		$receipt_number = 'GR-'.date('Ymd-His', $received_timestamp).'-'.strtoupper(random_string('alnum', 4));
		$receipt_subtotal = 0;
		$journal_lines = array();
		$this->db->insert('goods_receipts', array(
			'receipt_number' => $receipt_number,
			'purchase_order_id' => (int) $order_id,
			'supplier_document' => $supplier_document === '' ? NULL : $supplier_document,
			'ppn_nominal' => $ppn_nominal,
			'received_at' => $received_at,
			'notes' => $notes
		));
		$receipt_id = (int) $this->db->insert_id();

		foreach ($received_items as $received) {
			$item = $received['item'];
			$qty = $received['qty'];
			$stock = $this->db->query(
				'SELECT jumlah FROM stok WHERE id = ? FOR UPDATE',
				array((int) $item->product_id)
			)->row();
			$cost = $this->db->query(
				'SELECT average_cost FROM inventory_costs WHERE product_id = ? AND barcode = ? FOR UPDATE',
				array((int) $item->product_id, $item->barcode)
			)->row();
			if (!$stock || !$cost) {
				$this->db->trans_rollback();
				$this->_redirect_with_error('Data stok atau HPP tidak ditemukan. Penerimaan tidak disimpan.', 'main/purchasing/receive/'.$order_id);
			}
			$this->db->insert('goods_receipt_items', array(
				'goods_receipt_id' => $receipt_id,
				'purchase_order_item_id' => (int) $item->id,
				'qty_received' => $qty,
				'unit_price' => $received['unit_price'],
				'discount_type' => $received['discount_type'],
				'discount_value' => $received['discount_value'],
				'discount_amount' => $received['discount_amount'],
				'subtotal' => $received['subtotal']
			));
			$old_quantity = (int) $stock->jumlah;
			$old_cost_total = $old_quantity * (float) $cost->average_cost;
			$new_average_cost = round(
				($old_cost_total + (float) $received['subtotal']) / ($old_quantity + $qty),
				4
			);
			$this->db->query("
				UPDATE inventory_costs
				SET average_cost = ?, updated_at = ?
				WHERE product_id = ? AND barcode = ?
			", array($new_average_cost, $received_at, (int) $item->product_id, $item->barcode));
			$this->db->set('jumlah', 'jumlah + '.$qty, FALSE)
				->where('id', (int) $item->product_id)
				->update('stok');
			$receipt_subtotal += (float) $received['subtotal'];
			if ($received['subtotal'] > 0) {
				$journal_lines[] = array(
					'account' => '1104',
					'debit' => $received['subtotal'],
					'credit' => 0,
					'memo' => 'Persediaan penerimaan '.$receipt_number.' '.$item->barcode
				);
			}
		}
		if ($ppn_nominal > 0) {
			$journal_lines[] = array(
				'account' => '1105',
				'debit' => $ppn_nominal,
				'credit' => 0,
				'memo' => 'PPN masukan penerimaan '.$receipt_number
			);
		}
		$invoice_total = round($receipt_subtotal + $ppn_nominal, 2);
		if ($invoice_total > 0) {
			$journal_lines[] = array(
				'account' => '2101',
				'debit' => 0,
				'credit' => $invoice_total,
				'memo' => 'Hutang supplier penerimaan '.$receipt_number
			);
		}
		$this->load->library('accounting');
		if (!$this->accounting->post_journal(
			'PURCHASING',
			$receipt_id,
			substr($received_at, 0, 10),
			'Penerimaan supplier '.$supplier_document,
			'system',
			$journal_lines
		)) {
			$accounting_error = $this->accounting->error();
			$this->db->trans_rollback();
			log_message('error', 'Gagal memposting jurnal penerimaan '.$receipt_number.': '.$accounting_error);
			$this->_redirect_with_error('Penerimaan gagal disimpan karena jurnal akuntansi tidak dapat dibuat.', 'main/purchasing/receive/'.$order_id);
		}

		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal menyimpan penerimaan '.$receipt_number);
			$this->db->trans_rollback();
			$this->_redirect_with_error('Penerimaan gagal disimpan. Stok tidak berubah.', 'main/purchasing/receive/'.$order_id);
		}

		$this->db->trans_commit();
		$this->session->set_flashdata('purchasing_notice', 'Penerimaan '.$receipt_number.' berhasil dicatat dan stok diperbarui sesuai jumlah aktual.');
		redirect('main/purchasing/receive/'.$order_id);
	}

	private function _get_order($order_id)
	{
		if (!is_numeric($order_id) || (int) $order_id < 1) {
			return NULL;
		}

		$query = $this->db
			->select('po.*, s.name AS supplier_name, s.phone AS supplier_phone, s.address AS supplier_address')
			->from('purchase_orders po')
			->join('suppliers s', 's.id = po.supplier_id')
			->where('po.id', (int) $order_id)
			->get();
		return $query->num_rows() === 1 ? $query->row() : NULL;
	}

	private function _get_order_items($order_id)
	{
		return $this->db->query("
			SELECT poi.id, poi.product_id, poi.barcode, poi.product_name,
				poi.qty_ordered,
				COALESCE(received.total_qty, 0) AS qty_received
			FROM purchase_order_items poi
			JOIN stok ON stok.id = poi.product_id
			LEFT JOIN (
				SELECT gri.purchase_order_item_id, SUM(gri.qty_received) AS total_qty
				FROM goods_receipt_items gri
				GROUP BY gri.purchase_order_item_id
			) received ON received.purchase_order_item_id = poi.id
			WHERE poi.purchase_order_id = ?
			ORDER BY poi.id ASC
		", array($order_id))->result();
	}

	private function _order_has_receipts($order_id)
	{
		return $this->db
			->where('purchase_order_id', (int) $order_id)
			->count_all_results('goods_receipts') > 0;
	}

	private function _render_page($data)
	{
		$page_titles = array(
			'orders' => array('Purchasing', 'Daftar purchase order'),
			'create' => array(isset($data['order']) ? 'Edit Purchase Order' : 'Buat Purchase Order', 'Simpan draft dan siapkan dokumen untuk supplier'),
			'receive' => array('Penerimaan Barang', 'Bandingkan barang datang dengan purchase order')
		);
		$data['judul_besar'] = $page_titles[$data['page']][0];
		$data['judul_kecil'] = $page_titles[$data['page']][1];
		$data['m_purchasing'] = TRUE;
		if ($data['page'] === 'create') {
			$data['css_files'] = array(
				base_url('assets/jexcel/css/jexcel.css'),
				base_url('assets/jexcel/css/jsuites.css')
			);
			$data['js_files'] = array(
				base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
				base_url('assets/jexcel/js/jsuites.js'),
				base_url('assets/jexcel/js/jexcel.js')
			);
		}
		$data['output'] = $this->load->view('purchasing', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	private function _redirect_with_error($message, $uri = 'main/purchasing/create')
	{
		$this->session->set_flashdata('purchasing_error', $message);
		redirect($uri);
	}

	private function _is_positive_integer($value)
	{
		return $this->_is_non_negative_integer($value) && (int) $value > 0;
	}

	private function _is_non_negative_integer($value)
	{
		return is_string($value) && preg_match('/^\d+$/', $value) &&
			(int) $value <= 2147483647;
	}

	private function _is_non_negative_amount($value)
	{
		return is_string($value) && preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value);
	}

	private function _log_database_error($context)
	{
		$error = $this->db->error();
		log_message('error', $context.': '.$error['code'].' '.$error['message']);
	}
}
