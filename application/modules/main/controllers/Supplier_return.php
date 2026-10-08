<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Supplier_return extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form', 'string'));
		$this->load->library(array('session', 'ion_auth'));

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->ion_auth->in_group(array('admin', 'management', 'pengurus'))) {
			show_error('Anda tidak memiliki akses ke retur supplier.', 403);
		}
	}

	public function index()
	{
		$data['receipts'] = $this->db->query("
			SELECT receipt.id, receipt.receipt_number, receipt.received_at,
				receipt.supplier_document, supplier.name AS supplier_name,
				COALESCE(received.total_qty, 0) AS total_qty
			FROM goods_receipts receipt
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			LEFT JOIN (
				SELECT goods_receipt_id, SUM(qty_received) AS total_qty
				FROM goods_receipt_items
				GROUP BY goods_receipt_id
			) received ON received.goods_receipt_id = receipt.id
			ORDER BY receipt.id DESC
			LIMIT 100
		")->result();
		$data['drafts'] = $this->db->query("
			SELECT return_header.id, return_header.nomor_retur, return_header.goods_receipt_id,
				return_header.tgl_retur, return_header.total_nominal, return_header.id_user,
				supplier.name AS supplier_name, receipt.receipt_number
			FROM retur_supplier return_header
			JOIN suppliers supplier ON supplier.id = return_header.supplier_id
			LEFT JOIN goods_receipts receipt ON receipt.id = return_header.goods_receipt_id
			WHERE return_header.status = 'draft'
			ORDER BY return_header.id DESC
		")->result();
		$data['history'] = $this->db->query("
			SELECT return_header.id, return_header.nomor_retur, return_header.goods_receipt_id,
				return_header.tgl_retur, return_header.total_nominal, return_header.id_user,
				supplier.name AS supplier_name, receipt.receipt_number
			FROM retur_supplier return_header
			JOIN suppliers supplier ON supplier.id = return_header.supplier_id
			LEFT JOIN goods_receipts receipt ON receipt.id = return_header.goods_receipt_id
			WHERE return_header.status = 'final'
			ORDER BY return_header.id DESC
			LIMIT 50
		")->result();
		$data['can_create'] = $this->ion_auth->in_group(array('admin', 'management'));
		$data['can_approve'] = $this->ion_auth->in_group(array('admin', 'pengurus'));
		$data['notice'] = $this->session->flashdata('supplier_return_notice');
		$data['error'] = $this->session->flashdata('supplier_return_error');
		$data['judul_besar'] = 'Retur Supplier';
		$data['judul_kecil'] = 'Draft retur menunggu persetujuan admin atau pengurus';
		$data['m_supplier_return'] = TRUE;
		$data['output'] = $this->load->view('supplier_return', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function create($receipt_id = NULL)
	{
		if (!$this->ion_auth->in_group(array('admin', 'management'))) {
			show_error('Hanya admin atau management yang dapat mengajukan retur supplier.', 403);
		}
		if (!$this->_is_positive_integer($receipt_id)) {
			show_404();
		}

		$data['receipt'] = $this->_get_receipt((int) $receipt_id);
		if (!$data['receipt']) {
			show_404();
		}
		$data['items'] = $this->_get_receipt_items((int) $receipt_id);
		$data['error'] = $this->session->flashdata('supplier_return_error');
		$data['judul_besar'] = 'Draft Retur Supplier';
		$data['judul_kecil'] = 'Pilih barang dari penerimaan yang dipilih';
		$data['m_supplier_return'] = TRUE;
		$data['output'] = $this->load->view('supplier_return_create', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function save_draft($receipt_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		if (!$this->ion_auth->in_group(array('admin', 'management'))) {
			show_error('Hanya admin atau management yang dapat mengajukan retur supplier.', 403);
		}
		if (!$this->_is_positive_integer($receipt_id)) {
			show_404();
		}

		$reason = trim((string) $this->input->post('alasan', TRUE));
		$quantities = $this->input->post('qty_retur');
		$line_reasons = $this->input->post('alasan_detail');
		if ($reason === '' || strlen($reason) > 2000 || !is_array($quantities)) {
			$this->_redirect_error('Alasan retur dan jumlah barang wajib diisi.', 'main/supplier_return/create/'.$receipt_id);
		}
		if (!is_array($line_reasons)) {
			$line_reasons = array();
		}

		$selected = array();
		foreach ($quantities as $barcode => $quantity) {
			if (!is_string($barcode) && !is_int($barcode)) {
				$this->_redirect_error('Data barang retur tidak valid.', 'main/supplier_return/create/'.$receipt_id);
			}
			$barcode = (string) $barcode;
			if ($barcode === '' || strlen($barcode) > 64 ||
				(!is_string($quantity) && !is_numeric($quantity))) {
				$this->_redirect_error('Data barang retur tidak valid.', 'main/supplier_return/create/'.$receipt_id);
			}
			$quantity = trim((string) $quantity);
			if ($quantity === '' || $quantity === '0') {
				continue;
			}
			if (!$this->_is_positive_integer($quantity) || (float) $quantity > 2147483647) {
				$this->_redirect_error('Jumlah retur harus berupa bilangan bulat positif.', 'main/supplier_return/create/'.$receipt_id);
			}
			$detail_reason = isset($line_reasons[$barcode]) && is_string($line_reasons[$barcode])
				? trim($line_reasons[$barcode])
				: '';
			if (strlen($detail_reason) > 255) {
				$this->_redirect_error('Alasan per barang maksimal 255 karakter.', 'main/supplier_return/create/'.$receipt_id);
			}
			$selected[$barcode] = array('qty' => (int) $quantity, 'reason' => $detail_reason);
		}
		if (count($selected) === 0 || count($selected) > 500) {
			$this->_redirect_error('Pilih minimal satu barang (maksimal 500 baris).', 'main/supplier_return/create/'.$receipt_id);
		}

		$receipt = $this->_get_receipt((int) $receipt_id);
		if (!$receipt) {
			show_404();
		}
		$receipt_items = $this->_get_receipt_items((int) $receipt_id);
		$items_by_barcode = array();
		foreach ($receipt_items as $item) {
			$items_by_barcode[$item->barcode] = $item;
		}

		$validated = array();
		$total_amount = 0;
		foreach ($selected as $barcode => $selection) {
			if (!isset($items_by_barcode[$barcode])) {
				$this->_redirect_error('Barcode '.$barcode.' tidak terdapat pada penerimaan ini.', 'main/supplier_return/create/'.$receipt_id);
			}
			$item = $items_by_barcode[$barcode];
			if ($selection['qty'] > (int) $item->qty_available) {
				$this->_redirect_error(
					'Jumlah retur '.$barcode.' melebihi sisa yang dapat diretur ('.(int) $item->qty_available.').',
					'main/supplier_return/create/'.$receipt_id
				);
			}
			$line_total = round((float) $item->unit_price * $selection['qty'], 2);
			$validated[] = array(
				'barcode' => $barcode,
				'qty' => $selection['qty'],
				'unit_price' => (float) $item->unit_price,
				'subtotal' => $line_total,
				'reason' => $selection['reason']
			);
			$total_amount += $line_total;
		}

		$operator_id = (string) $this->ion_auth->get_user_id();
		if ($operator_id === '' || strlen($operator_id) > 32) {
			show_error('Identitas petugas tidak valid.', 500);
		}
		$return_number = 'RTS-'.date('Ymd-His').'-'.strtoupper(random_string('alnum', 4));
		$this->db->trans_begin();
		$this->db->insert('retur_supplier', array(
			'nomor_retur' => $return_number,
			'supplier_id' => (int) $receipt->supplier_id,
			'goods_receipt_id' => (int) $receipt_id,
			'id_user' => $operator_id,
			'tgl_retur' => date('Y-m-d H:i:s'),
			'total_nominal' => $total_amount,
			'alasan' => $reason,
			'status' => 'draft'
		));
		$return_id = (int) $this->db->insert_id();
		foreach ($validated as $item) {
			$this->db->insert('retur_supplier_items', array(
				'retur_supplier_id' => $return_id,
				'barcode' => $item['barcode'],
				'qty_retur' => $item['qty'],
				'harga_beli' => $item['unit_price'],
				'subtotal' => $item['subtotal'],
				'alasan_detail' => $item['reason'] !== '' ? $item['reason'] : NULL
			));
		}
		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal membuat draft retur supplier '.$return_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Draft retur gagal disimpan. Stok tidak berubah.');
		}
		if (!$this->db->trans_commit()) {
			$this->_log_database_error('Gagal commit draft retur supplier '.$return_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Draft retur gagal disimpan. Stok tidak berubah.');
		}

		$this->session->set_flashdata('supplier_return_notice', 'Draft '.$return_number.' berhasil dibuat dan menunggu persetujuan.');
		redirect('main/supplier_return');
	}

	public function approve($return_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		if (!$this->ion_auth->in_group(array('admin', 'pengurus'))) {
			show_error('Hanya admin atau pengurus yang dapat menyetujui retur supplier.', 403);
		}
		if (!$this->_is_positive_integer($return_id)) {
			show_404();
		}

		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$this->db->trans_begin();
		$return_header = $this->db->query(
			'SELECT * FROM retur_supplier WHERE id = ? FOR UPDATE',
			array((int) $return_id)
		)->row();
		if (!$return_header) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			show_404();
		}
		if ($return_header->status !== 'draft') {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Retur ini sudah pernah difinalisasi.');
		}

		$receipt = $this->db->query(
			'SELECT id FROM goods_receipts WHERE id = ? FOR UPDATE',
			array((int) $return_header->goods_receipt_id)
		)->row();
		if (!$receipt) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Penerimaan asal retur tidak ditemukan.');
		}

		$return_items = $this->db->where('retur_supplier_id', (int) $return_id)
			->order_by('barcode', 'ASC')
			->get('retur_supplier_items')
			->result();
		if (count($return_items) === 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Draft retur tidak memiliki detail barang.');
		}

		$errors = array();
		$return_qty_by_barcode = array();
		foreach ($return_items as $item) {
			$barcode = (string) $item->barcode;
			if (!isset($return_qty_by_barcode[$barcode])) {
				$return_qty_by_barcode[$barcode] = 0;
			}
			$return_qty_by_barcode[$barcode] += (int) $item->qty_retur;
		}
		foreach ($return_qty_by_barcode as $barcode => $return_qty) {
			$availability = $this->db->query("
				SELECT COALESCE(SUM(receipt_item.qty_received), 0) AS qty_received,
					COALESCE((
						SELECT SUM(return_item.qty_retur)
						FROM retur_supplier_items return_item
						JOIN retur_supplier return_header
							ON return_header.id = return_item.retur_supplier_id
						WHERE return_header.goods_receipt_id = ?
							AND return_header.status = 'final'
							AND return_item.barcode = ?
					), 0) AS qty_returned
				FROM goods_receipt_items receipt_item
				JOIN purchase_order_items order_item
					ON order_item.id = receipt_item.purchase_order_item_id
				WHERE receipt_item.goods_receipt_id = ? AND order_item.barcode = ?
			", array(
				(int) $return_header->goods_receipt_id,
				$barcode,
				(int) $return_header->goods_receipt_id,
				$barcode
			))->row();
			$available_qty = (int) $availability->qty_received - (int) $availability->qty_returned;
			if ($return_qty > $available_qty) {
				$errors[] = 'Qty retur '.$barcode.' melampaui sisa penerimaan ('.$available_qty.').';
				continue;
			}

			$stock = $this->db->query(
				'SELECT id, jumlah FROM stok WHERE barcode = ? FOR UPDATE',
				array($barcode)
			)->row();
			if (!$stock || (int) $stock->jumlah < $return_qty) {
				$errors[] = 'Stok '.$barcode.' tidak cukup untuk retur supplier.';
			}
		}
		if (count($errors) > 0) {
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error(implode(' ', $errors));
		}

		foreach ($return_qty_by_barcode as $barcode => $return_qty) {
			$this->db->set('jumlah', 'jumlah - '.(int) $return_qty, FALSE)
				->where('barcode', $barcode)
				->where('jumlah >=', (int) $return_qty)
				->update('stok');
			if ($this->db->affected_rows() !== 1) {
				$errors[] = 'Stok '.$barcode.' berubah saat retur diproses.';
				break;
			}
		}
		if (count($errors) === 0) {
			$this->db->where('id', (int) $return_id)
				->where('status', 'draft')
				->update('retur_supplier', array('status' => 'final'));
		}
		if (count($errors) === 0) {
			$return_amount = round((float) $return_header->total_nominal, 2);
			$journal_lines = array();
			if ($return_amount > 0) {
				$journal_lines[] = array(
					'account' => '2101',
					'debit' => $return_amount,
					'credit' => 0,
					'memo' => 'Pengurangan hutang retur supplier '.$return_header->nomor_retur
				);
				$journal_lines[] = array(
					'account' => '1104',
					'debit' => 0,
					'credit' => $return_amount,
					'memo' => 'Pengurangan persediaan retur supplier '.$return_header->nomor_retur
				);
			}
			$this->load->library('accounting');
			if (!$this->accounting->post_journal(
				'SUPPLIER_RETURN',
				$return_id,
				substr($return_header->tgl_retur, 0, 10),
				'Retur supplier '.$return_header->nomor_retur,
				(string) $return_header->id_user,
				$journal_lines
			)) {
				log_message('error', 'Gagal memposting jurnal retur supplier '.$return_header->nomor_retur.': '.$this->accounting->error());
				$errors[] = 'Retur supplier gagal difinalisasi karena jurnal akuntansi tidak dapat dibuat.';
			}
		}
		if (count($errors) > 0 || $this->db->trans_status() === FALSE) {
			$database_error = $this->db->error();
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			if (!empty($database_error['code'])) {
				$this->_log_database_error('Gagal memfinalisasi retur supplier '.$return_header->nomor_retur);
			}
			$this->_redirect_error(count($errors) > 0
				? implode(' ', $errors)
				: 'Retur supplier gagal difinalisasi. Stok tidak berubah.');
		}
		if (!$this->db->trans_commit()) {
			$this->_log_database_error('Gagal commit retur supplier '.$return_header->nomor_retur);
			$this->db->trans_rollback();
			$this->db->db_debug = $previous_db_debug;
			$this->_redirect_error('Retur supplier gagal difinalisasi. Periksa status dan stok sebelum mencoba kembali.');
		}
		$this->db->db_debug = $previous_db_debug;
		$this->session->set_flashdata('supplier_return_notice', 'Retur '.$return_header->nomor_retur.' disetujui. Stok telah dikurangi sesuai jumlah retur.');
		redirect('main/supplier_return');
	}

	private function _get_receipt($receipt_id)
	{
		return $this->db->query("
			SELECT receipt.id, receipt.receipt_number, receipt.received_at,
				receipt.supplier_document, purchase_order.supplier_id,
				supplier.name AS supplier_name
			FROM goods_receipts receipt
			JOIN purchase_orders purchase_order ON purchase_order.id = receipt.purchase_order_id
			JOIN suppliers supplier ON supplier.id = purchase_order.supplier_id
			WHERE receipt.id = ?
		", array($receipt_id))->row();
	}

	private function _get_receipt_items($receipt_id)
	{
		return $this->db->query("
			SELECT order_item.barcode, MAX(order_item.product_name) AS product_name,
				SUM(receipt_item.qty_received) AS qty_received,
				SUM(CASE WHEN receipt_item.subtotal > 0 THEN receipt_item.subtotal
					ELSE receipt_item.qty_received * COALESCE(receipt_item.unit_price, 0) END)
					/ NULLIF(SUM(receipt_item.qty_received), 0) AS unit_price,
				COALESCE((
					SELECT SUM(return_item.qty_retur)
					FROM retur_supplier_items return_item
					JOIN retur_supplier return_header
						ON return_header.id = return_item.retur_supplier_id
					WHERE return_header.goods_receipt_id = ?
						AND return_header.status = 'final'
						AND return_item.barcode = order_item.barcode
				), 0) AS qty_returned,
				SUM(receipt_item.qty_received) - COALESCE((
					SELECT SUM(return_item.qty_retur)
					FROM retur_supplier_items return_item
					JOIN retur_supplier return_header
						ON return_header.id = return_item.retur_supplier_id
					WHERE return_header.goods_receipt_id = ?
						AND return_header.status = 'final'
						AND return_item.barcode = order_item.barcode
				), 0) AS qty_available
			FROM goods_receipt_items receipt_item
			JOIN purchase_order_items order_item
				ON order_item.id = receipt_item.purchase_order_item_id
			WHERE receipt_item.goods_receipt_id = ?
			GROUP BY order_item.barcode
			ORDER BY order_item.product_name ASC, order_item.barcode ASC
		", array($receipt_id, $receipt_id, $receipt_id))->result();
	}

	private function _is_positive_integer($value)
	{
		return (is_string($value) || is_numeric($value)) &&
			preg_match('/^[1-9][0-9]*$/', (string) $value);
	}

	private function _redirect_error($message, $uri = 'main/supplier_return')
	{
		$this->session->set_flashdata('supplier_return_error', $message);
		redirect($uri);
	}

	private function _log_database_error($context)
	{
		$error = $this->db->error();
		log_message('error', $context.': '.$error['code'].' '.$error['message']);
	}
}
