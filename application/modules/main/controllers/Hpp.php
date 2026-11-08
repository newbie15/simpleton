<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hpp extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper('url');
		$this->load->library(array('session', 'ion_auth', 'page_access'));
		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('purchasing')) {
			show_error('Anda tidak memiliki akses ke riwayat HPP.', 403);
		}
		if (!$this->db->table_exists('inventory_costs') ||
			!$this->db->table_exists('inventory_cost_history') ||
			!$this->db->field_exists('average_cost_after', 'goods_receipt_items')) {
			show_error('Skema riwayat HPP belum tersedia. Jalankan application/sql/inventory_cost_history.sql.', 500);
		}
	}

	public function index()
	{
		$barcode_input = $this->input->get('barcode', TRUE);
		if ($barcode_input !== NULL && !is_string($barcode_input) && !is_numeric($barcode_input)) {
			show_error('Barcode tidak valid.', 400);
		}
		$barcode = trim((string) $barcode_input);
		if (strlen($barcode) > 64) {
			show_error('Barcode tidak valid.', 400);
		}

		$data['product'] = NULL;
		$data['purchase_history'] = array();
		$data['manual_history'] = array();
		$data['can_manage_hpp'] = $this->ion_auth->in_group(array('admin', 'management', 'manager', 'pengurus'));
		$data['notice'] = $this->session->flashdata('hpp_notice');
		$data['error'] = $this->session->flashdata('hpp_error');

		if ($barcode !== '') {
			$data['product'] = $this->db
				->select('stock.id, stock.barcode, stock.nama, stock.jumlah, cost.average_cost, cost.updated_at')
				->from('stok stock')
				->join('inventory_costs cost', 'cost.product_id = stock.id AND cost.barcode = stock.barcode', 'left')
				->where('stock.barcode', $barcode)
				->get()
				->row();
			if ($data['product']) {
				$data['purchase_history'] = $this->db->query("
					SELECT receipt.received_at, receipt.receipt_number, receipt.supplier_document,
						po.order_number, supplier.name AS supplier_name, item.qty_received,
						item.unit_price, item.discount_type, item.discount_value,
						item.discount_amount, item.subtotal, item.average_cost_after
					FROM goods_receipt_items item
					JOIN goods_receipts receipt ON receipt.id = item.goods_receipt_id
					JOIN purchase_order_items po_item ON po_item.id = item.purchase_order_item_id
					JOIN purchase_orders po ON po.id = receipt.purchase_order_id
					JOIN suppliers supplier ON supplier.id = po.supplier_id
					WHERE po_item.product_id = ? AND po_item.barcode = ?
					ORDER BY receipt.received_at DESC, item.id DESC
				", array((int) $data['product']->id, $barcode))->result();
				$data['manual_history'] = $this->db->query("
					SELECT history.recorded_at, history.unit_cost, history.previous_average_cost,
						history.notes, COALESCE(user.username, history.created_by) AS created_by_name
					FROM inventory_cost_history history
					LEFT JOIN users user ON user.id = history.created_by
					WHERE history.product_id = ? AND history.barcode = ?
					ORDER BY history.recorded_at DESC, history.id DESC
				", array((int) $data['product']->id, $barcode))->result();
			} else {
				$data['error'] = 'Barcode tidak ditemukan di master stok.';
			}
		}

		$data['judul_besar'] = 'Riwayat HPP';
		$data['judul_kecil'] = 'Riwayat harga beli dan biaya pokok per barang';
		$data['m_hpp'] = TRUE;
		$data['css_file'] = array(base_url('assets/datatables/css/jquery.dataTables.min.css'));
		$data['js_file'] = array(
			base_url('assets/datatables/js/jquery.dataTables.min.js'),
			base_url('assets/purchasing/hpp_inventory.js?v=20261101-server-side')
		);
		$data['output'] = $this->load->view('hpp', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function inventory_data()
	{
		if ($this->input->method(TRUE) !== 'GET') {
			show_404();
		}

		$draw = $this->_datatable_integer($this->input->get('draw'), 0, 0, 2147483647);
		$start = $this->_datatable_integer($this->input->get('start'), 0, 0, 10000000);
		$length = $this->_datatable_integer($this->input->get('length'), 25, 1, 100);
		$search_data = $this->input->get('search');
		$search = is_array($search_data) && isset($search_data['value']) && is_string($search_data['value'])
			? substr(trim($search_data['value']), 0, 100)
			: '';
		$order_data = $this->input->get('order');
		$order_column = is_array($order_data) && isset($order_data[0]) &&
			is_array($order_data[0]) && isset($order_data[0]['column'])
			? $this->_datatable_integer($order_data[0]['column'], 0, 0, 4)
			: 0;
		$order_direction = is_array($order_data) && isset($order_data[0]) &&
			is_array($order_data[0]) && isset($order_data[0]['dir']) &&
			is_string($order_data[0]['dir']) && strtolower($order_data[0]['dir']) === 'asc'
			? 'ASC'
			: 'DESC';
		$order_columns = array(
			0 => 'stock.barcode',
			1 => 'stock.nama',
			2 => 'stock.jumlah',
			3 => 'cost.average_cost',
			4 => 'cost.updated_at'
		);

		$total = $this->_inventory_query()->count_all_results();
		$filtered_query = $this->_inventory_query();
		if ($search !== '') {
			$filtered_query->group_start()
				->like('stock.barcode', $search)
				->or_like('stock.nama', $search)
				->group_end();
		}
		$filtered = $filtered_query->count_all_results();

		$row_query = $this->_inventory_query();
		if ($search !== '') {
			$row_query->group_start()
				->like('stock.barcode', $search)
				->or_like('stock.nama', $search)
				->group_end();
		}
		$rows = $row_query
			->select('stock.id, stock.barcode, stock.nama, stock.jumlah, cost.average_cost, cost.updated_at')
			->order_by($order_columns[$order_column], $order_direction)
			->order_by('stock.id', 'ASC')
			->limit($length, $start)
			->get()
			->result_array();

		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: no-store, private')
			->set_output(json_encode(array(
				'draw' => $draw,
				'recordsTotal' => (int) $total,
				'recordsFiltered' => (int) $filtered,
				'data' => $rows
			)));
	}

	public function save_manual()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		if (!$this->ion_auth->in_group(array('admin', 'management', 'manager', 'pengurus'))) {
			show_error('Hanya admin, management, manager, atau pengurus yang dapat mengubah HPP manual.', 403);
		}

		$barcode_input = $this->input->post('barcode', TRUE);
		$unit_cost_input = $this->input->post('unit_cost', TRUE);
		$notes_input = $this->input->post('notes', TRUE);
		if ((!is_string($barcode_input) && !is_numeric($barcode_input)) ||
			(!is_string($unit_cost_input) && !is_numeric($unit_cost_input)) ||
			($notes_input !== NULL && !is_string($notes_input))) {
			show_error('Data HPP manual tidak valid.', 400);
		}
		$barcode = trim((string) $barcode_input);
		$unit_cost = trim((string) $unit_cost_input);
		$notes = trim((string) $notes_input);
		$redirect_uri = 'main/hpp?barcode='.rawurlencode($barcode);
		if ($barcode === '' || strlen($barcode) > 64 ||
			!preg_match('/^\d{1,11}(?:\.\d{1,4})?$/', $unit_cost) ||
			(float) $unit_cost <= 0 || strlen($notes) > 500) {
			$this->session->set_flashdata('hpp_error', 'Barcode, HPP, atau catatan tidak valid. HPP harus lebih dari nol dan maksimal 4 angka desimal.');
			redirect($redirect_uri);
		}

		$this->db->trans_begin();
		$product = $this->db->query(
			'SELECT id, barcode FROM stok WHERE barcode = ? FOR UPDATE',
			array($barcode)
		)->row();
		if (!$product) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('hpp_error', 'Barcode tidak ditemukan di master stok.');
			redirect('main/hpp');
		}

		$current = $this->db->query(
			'SELECT average_cost FROM inventory_costs WHERE product_id = ? AND barcode = ? FOR UPDATE',
			array((int) $product->id, $barcode)
		)->row();
		$previous_cost = $current ? (float) $current->average_cost : NULL;
		$recorded_at = date('Y-m-d H:i:s');
		$this->db->query("
			INSERT INTO inventory_costs (product_id, barcode, average_cost, updated_at)
			VALUES (?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE barcode = VALUES(barcode),
				average_cost = VALUES(average_cost), updated_at = VALUES(updated_at)
		", array((int) $product->id, $barcode, $unit_cost, $recorded_at));
		$this->db->insert('inventory_cost_history', array(
			'product_id' => (int) $product->id,
			'barcode' => $barcode,
			'unit_cost' => $unit_cost,
			'previous_average_cost' => $previous_cost,
			'recorded_at' => $recorded_at,
			'created_by' => (string) $this->ion_auth->get_user_id(),
			'notes' => $notes === '' ? NULL : $notes
		));

		if ($this->db->trans_status() === FALSE) {
			$error = $this->db->error();
			$this->db->trans_rollback();
			log_message('error', 'Gagal menyimpan HPP manual barcode '.$barcode.': '.$error['code'].' '.$error['message']);
			$this->session->set_flashdata('hpp_error', 'HPP manual gagal disimpan. Tidak ada perubahan diterapkan.');
			redirect($redirect_uri);
		}

		$this->db->trans_commit();
		$this->session->set_flashdata('hpp_notice', 'HPP manual berhasil disimpan. Sumber dicatat sebagai manual, bukan vendor/supplier.');
		redirect($redirect_uri);
	}

	private function _inventory_query()
	{
		return $this->db
			->from('stok stock')
			->join('inventory_costs cost', 'cost.product_id = stock.id AND cost.barcode = stock.barcode', 'left');
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
}
