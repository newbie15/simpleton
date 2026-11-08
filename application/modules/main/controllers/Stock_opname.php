<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Stock_opname extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'string'));
		$this->load->library(array('session', 'ion_auth'));
	}

	public function index()
	{
		$data['opnames'] = $this->db->query("
			SELECT opname.id, opname.opname_number, opname.created_at, opname.finalized_at,
				opname.status, COUNT(item.id) AS item_count,
				SUM(CASE WHEN item.counted_qty IS NULL THEN 1 ELSE 0 END) AS uncounted_items,
				COALESCE(SUM(item.counted_qty * item.unit_cost), 0) AS inventory_value
			FROM stock_opnames opname
			LEFT JOIN stock_opname_items item ON item.stock_opname_id = opname.id
			GROUP BY opname.id
			ORDER BY opname.id DESC
		")->result();
		$data['notice'] = $this->session->flashdata('stock_opname_notice');
		$data['error'] = $this->session->flashdata('stock_opname_error');
		$data['page'] = 'list';
		$this->_render($data);
	}

	public function create()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$barcode_input = $this->input->post('barcode', TRUE);
		if ($barcode_input !== NULL && !is_string($barcode_input) && !is_numeric($barcode_input)) {
			$this->_redirect_error('Barcode tidak valid.', 'main/stock_opname');
		}
		$barcode = trim((string) $barcode_input);
		if (strlen($barcode) > 64) {
			$this->_redirect_error('Barcode tidak valid.', 'main/stock_opname');
		}

		$this->db->trans_begin();
		$product_query = $this->db
			->select('stock.id, stock.barcode, stock.nama, stock.jumlah, cost.average_cost')
			->from('stok stock')
			->join('inventory_costs cost', 'cost.product_id = stock.id AND cost.barcode = stock.barcode', 'left');
		if ($barcode !== '') {
			$product_query->where('stock.barcode', $barcode);
		} else {
			$product_query->order_by('stock.barcode', 'ASC');
		}
		$products = $product_query->get()->result();
		if (count($products) === 0) {
			$this->db->trans_rollback();
			$message = $barcode === ''
				? 'Tidak ada barang di master stok untuk dihitung.'
				: 'Barcode '.$barcode.' tidak ditemukan di master stok.';
			$this->_redirect_error($message, 'main/stock_opname');
		}

		$created_at = date('Y-m-d H:i:s');
		$opname_number = 'SO-'.date('Ymd-His').'-'.strtoupper(random_string('alnum', 4));
		$this->db->insert('stock_opnames', array(
			'opname_number' => $opname_number,
			'status' => 'draft',
			'created_at' => $created_at,
			'finalized_at' => NULL,
			'notes' => NULL
		));
		$opname_id = (int) $this->db->insert_id();

		foreach (array_chunk($products, 200) as $chunk) {
			$rows = array();
			foreach ($chunk as $product) {
				$rows[] = array(
					'stock_opname_id' => $opname_id,
					'product_id' => (int) $product->id,
					'barcode' => $product->barcode,
					'product_name' => $product->nama,
					'system_qty' => (int) $product->jumlah,
					'counted_qty' => NULL,
					'unit_cost' => $product->average_cost !== NULL && (float) $product->average_cost > 0
						? $product->average_cost
						: NULL
				);
			}
			$this->db->insert_batch('stock_opname_items', $rows);
		}

		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal membuat stok opname '.$opname_number);
			$this->db->trans_rollback();
			show_error('Draft stok opname gagal dibuat.');
		}
		$this->db->trans_commit();
		redirect('main/stock_opname/edit/'.$opname_id);
	}

	public function sync($opname_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$opname = $this->_get_opname($opname_id);
		if (!$opname) {
			show_404();
		}

		$this->db->trans_begin();
		$locked_opname = $this->db->query(
			'SELECT id, status FROM stock_opnames WHERE id = ? FOR UPDATE',
			array((int) $opname_id)
		)->row();
		if (!$locked_opname || $locked_opname->status !== 'draft') {
			$this->db->trans_rollback();
			$this->_redirect_error('Hanya draft opname yang dapat disinkronkan.', 'main/stock_opname');
		}

		$products = $this->db->query("
			SELECT stock.id, stock.barcode, stock.nama, stock.jumlah, cost.average_cost
			FROM stok stock
			LEFT JOIN inventory_costs cost
				ON cost.product_id = stock.id AND cost.barcode = stock.barcode
			LEFT JOIN stock_opname_items item
				ON item.stock_opname_id = ? AND item.product_id = stock.id
			WHERE item.id IS NULL
			ORDER BY stock.barcode ASC
		", array((int) $opname_id))->result();

		foreach (array_chunk($products, 200) as $chunk) {
			$rows = array();
			foreach ($chunk as $product) {
				$rows[] = array(
					'stock_opname_id' => (int) $opname_id,
					'product_id' => (int) $product->id,
					'barcode' => $product->barcode,
					'product_name' => $product->nama,
					'system_qty' => (int) $product->jumlah,
					'counted_qty' => NULL,
					'unit_cost' => $product->average_cost !== NULL && (float) $product->average_cost > 0
						? $product->average_cost
						: NULL
				);
			}
			$this->db->insert_batch('stock_opname_items', $rows);
		}

		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal menyinkronkan barang baru ke draft stok opname '.$opname->opname_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Barang baru gagal disinkronkan ke draft opname.', 'main/stock_opname/edit/'.$opname_id);
		}
		if (!$this->db->trans_commit()) {
			$this->_log_database_error('Gagal commit sinkronisasi draft stok opname '.$opname->opname_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Barang baru gagal disinkronkan ke draft opname.', 'main/stock_opname/edit/'.$opname_id);
		}
		$this->session->set_flashdata(
			'stock_opname_notice',
			count($products) > 0
				? count($products).' barang baru ditambahkan ke draft opname. Snapshot barang yang sudah ada tidak diubah.'
				: 'Tidak ada barang baru untuk disinkronkan. Draft opname sudah memuat semua barang saat ini.'
		);
		redirect('main/stock_opname/edit/'.$opname_id);
	}

	public function cancel($opname_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$opname = $this->_get_opname($opname_id);
		if (!$opname) {
			show_404();
		}

		$this->db->trans_begin();
		$locked_opname = $this->db->query(
			'SELECT id, status FROM stock_opnames WHERE id = ? FOR UPDATE',
			array((int) $opname_id)
		)->row();
		if (!$locked_opname || $locked_opname->status !== 'draft') {
			$this->db->trans_rollback();
			$this->_redirect_error('Hanya draft opname yang dapat dibatalkan.', 'main/stock_opname');
		}

		$this->db->where('id', (int) $opname_id)
			->where('status', 'draft')
			->update('stock_opnames', array('status' => 'cancelled'));
		if ($this->db->trans_status() === FALSE || $this->db->affected_rows() !== 1) {
			$this->_log_database_error('Gagal membatalkan draft stok opname '.$opname->opname_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Draft stok opname gagal dibatalkan.', 'main/stock_opname/edit/'.$opname_id);
		}

		if (!$this->db->trans_commit()) {
			$this->_log_database_error('Gagal commit pembatalan draft stok opname '.$opname->opname_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Draft stok opname gagal dibatalkan.', 'main/stock_opname/edit/'.$opname_id);
		}
		$this->session->set_flashdata(
			'stock_opname_notice',
			'Draft stok opname '.$opname->opname_number.' dibatalkan. Riwayat dan data hitungannya tetap disimpan.'
		);
		redirect('main/stock_opname');
	}

	public function edit($opname_id = NULL)
	{
		$opname = $this->_get_opname($opname_id);
		if (!$opname) {
			show_404();
		}

		$data['opname'] = $opname;
		$data['items'] = $this->db
			->select('stock_opname_items.*, cost.average_cost AS system_average_cost')
			->from('stock_opname_items')
			->join(
				'inventory_costs cost',
				'cost.product_id = stock_opname_items.product_id AND cost.barcode = stock_opname_items.barcode',
				'left'
			)
			->where('stock_opname_id', (int) $opname_id)
			->order_by('stock_opname_items.barcode', 'ASC')
			->get()
			->result();
		$data['can_see_system_stock'] = $this->ion_auth->is_admin() ||
			$this->ion_auth->in_group('pengurus');
		$data['notice'] = $this->session->flashdata('stock_opname_notice');
		$data['error'] = $this->session->flashdata('stock_opname_error');
		$data['page'] = 'edit';
		$this->_render($data);
	}

	public function save($opname_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$opname = $this->_get_opname($opname_id);
		if (!$opname) {
			show_404();
		}
		if ($opname->status !== 'draft') {
			show_error('Stok opname yang sudah diselesaikan tidak dapat diubah.', 400);
		}

		$payload = $this->input->post('items_json', FALSE);
		$posted_items = is_string($payload) ? json_decode($payload, TRUE) : NULL;
		$snapshot_items = $this->db->where('stock_opname_id', (int) $opname_id)
			->get('stock_opname_items')
			->result();
		if (!is_array($posted_items) || count($posted_items) > count($snapshot_items)) {
			$this->_redirect_error('Data hitungan tidak valid. Muat ulang draft lalu coba lagi.', 'main/stock_opname/edit/'.$opname_id);
		}

		$snapshot_by_id = array();
		foreach ($snapshot_items as $snapshot) {
			$snapshot_by_id[(int) $snapshot->id] = $snapshot;
		}
		$validated = array();
		foreach ($posted_items as $row) {
			if (!is_array($row) || count($row) < 2 || !is_numeric($row[0])) {
				$this->_redirect_error('Format baris hitungan tidak valid.', 'main/stock_opname/edit/'.$opname_id);
			}
			$item_id = (int) $row[0];
			if (!isset($snapshot_by_id[$item_id]) || isset($validated[$item_id])) {
				$this->_redirect_error('Daftar barang hitungan tidak sesuai dengan draft opname.', 'main/stock_opname/edit/'.$opname_id);
			}

			$counted_qty = trim($this->_value_string($row[1]));
			if ($counted_qty === '') {
				$validated[$item_id] = array('counted_qty' => NULL);
				continue;
			}
			if (!$this->_is_non_negative_integer($counted_qty)) {
				$this->_redirect_error('Qty fisik harus bilangan bulat nol atau lebih.', 'main/stock_opname/edit/'.$opname_id);
			}
			$validated[$item_id] = array(
				'counted_qty' => (int) $counted_qty
			);
		}

		$this->db->trans_begin();
		foreach ($validated as $item_id => $values) {
			$this->db->where('id', (int) $item_id)
				->where('stock_opname_id', (int) $opname_id)
				->update('stock_opname_items', $values);
		}
		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal menyimpan draft stok opname '.$opname->opname_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Draft stok opname gagal disimpan.', 'main/stock_opname/edit/'.$opname_id);
		}
		$this->db->trans_commit();
		$this->session->set_flashdata('stock_opname_notice', 'Draft stok opname berhasil disimpan.');
		redirect('main/stock_opname/edit/'.$opname_id);
	}

	public function finalize($opname_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}
		$opname = $this->_get_opname($opname_id);
		if (!$opname) {
			show_404();
		}
		if ($opname->status !== 'draft') {
			show_error('Stok opname ini sudah pernah diselesaikan.', 400);
		}

		$this->db->trans_begin();
		$this->db->query('SELECT id FROM stock_opnames WHERE id = ? FOR UPDATE', array((int) $opname_id));
		$locked_opname = $this->_get_opname($opname_id);
		if (!$locked_opname || $locked_opname->status !== 'draft') {
			$this->db->trans_rollback();
			show_error('Status stok opname berubah. Muat ulang halaman.', 409);
		}

		$items = $this->db->query("
			SELECT item.*, stock.jumlah AS current_qty, stock.barcode AS current_barcode,
				cost.average_cost AS current_average_cost
			FROM stock_opname_items item
			JOIN stok stock ON stock.id = item.product_id
			LEFT JOIN inventory_costs cost
				ON cost.product_id = stock.id AND cost.barcode = stock.barcode
			WHERE item.stock_opname_id = ?
			ORDER BY item.product_id ASC
			FOR UPDATE
		", array((int) $opname_id))->result();
		if (count($items) === 0) {
			$this->db->trans_rollback();
			$this->_redirect_error('Opname ini tidak memiliki barang.', 'main/stock_opname/edit/'.$opname_id);
		}

		$stock_gain_value = 0;
		$stock_loss_value = 0;
		foreach ($items as $item) {
			if ($item->counted_qty === NULL) {
				$this->db->trans_rollback();
				$this->_redirect_error('Lengkapi qty fisik semua barang sebelum menyelesaikan opname.', 'main/stock_opname/edit/'.$opname_id);
			}
			$unit_cost = $item->current_average_cost !== NULL && (float) $item->current_average_cost > 0
				? (float) $item->current_average_cost
				: (float) $item->unit_cost;
			if (((int) $item->counted_qty > 0 || (int) $item->system_qty > 0) && $unit_cost <= 0) {
				$this->db->trans_rollback();
				$this->_redirect_error('HPP sistem belum tersedia untuk barcode '.$item->barcode.'. Tetapkan HPP melalui halaman Riwayat HPP sebelum menyelesaikan opname.', 'main/stock_opname/edit/'.$opname_id);
			}
			$adjustment = (int) $item->counted_qty - (int) $item->system_qty;
			$adjustment_value = round(abs($adjustment) * $unit_cost, 2);
			$adjusted_qty = (int) $item->current_qty + $adjustment;
			if ($adjusted_qty < 0) {
				$this->db->trans_rollback();
				$this->_redirect_error('Stok saat ini tidak cukup untuk menerapkan selisih opname pada barcode '.$item->barcode.'. Periksa transaksi yang terjadi setelah opname dimulai.', 'main/stock_opname/edit/'.$opname_id);
			}
			$this->db->set('jumlah', 'jumlah + '.$adjustment, FALSE)
				->where('id', (int) $item->product_id)
				->update('stok');
			$this->db->where('id', (int) $item->id)
				->update('stock_opname_items', array('unit_cost' => $unit_cost));
			if ($adjustment > 0) {
				$stock_gain_value += $adjustment_value;
			} elseif ($adjustment < 0) {
				$stock_loss_value += $adjustment_value;
			}
		}

		$finalized_at = date('Y-m-d H:i:s');
		$this->db->where('id', (int) $opname_id)->update('stock_opnames', array(
			'status' => 'finalized',
			'finalized_at' => $finalized_at
		));
		$journal_lines = array();
		$stock_gain_value = round($stock_gain_value, 2);
		$stock_loss_value = round($stock_loss_value, 2);
		if ($stock_gain_value > 0) {
			$journal_lines[] = array(
				'account' => '1104',
				'debit' => $stock_gain_value,
				'credit' => 0,
				'memo' => 'Penambahan persediaan opname '.$opname->opname_number
			);
			$journal_lines[] = array(
				'account' => '4102',
				'debit' => 0,
				'credit' => $stock_gain_value,
				'memo' => 'Selisih lebih opname '.$opname->opname_number
			);
		}
		if ($stock_loss_value > 0) {
			$journal_lines[] = array(
				'account' => '6101',
				'debit' => $stock_loss_value,
				'credit' => 0,
				'memo' => 'Selisih kurang opname '.$opname->opname_number
			);
			$journal_lines[] = array(
				'account' => '1104',
				'debit' => 0,
				'credit' => $stock_loss_value,
				'memo' => 'Pengurangan persediaan opname '.$opname->opname_number
			);
		}
		if (count($journal_lines) > 0) {
			$this->load->library('accounting');
			if (!$this->accounting->post_journal(
				'STOCK_OPNAME',
				$opname_id,
				substr($finalized_at, 0, 10),
				'Penyesuaian stok opname '.$opname->opname_number,
				'system',
				$journal_lines
			)) {
				$accounting_error = $this->accounting->error();
				$this->db->trans_rollback();
				log_message('error', 'Gagal memposting jurnal opname '.$opname->opname_number.': '.$accounting_error);
				$this->_redirect_error('Opname gagal diselesaikan karena jurnal akuntansi tidak dapat dibuat.', 'main/stock_opname/edit/'.$opname_id);
			}
		}
		if ($this->db->trans_status() === FALSE) {
			$this->_log_database_error('Gagal menyelesaikan stok opname '.$opname->opname_number);
			$this->db->trans_rollback();
			$this->_redirect_error('Opname gagal diselesaikan. Stok tidak diubah.', 'main/stock_opname/edit/'.$opname_id);
		}
		$this->db->trans_commit();
		$this->session->set_flashdata('stock_opname_notice', 'Stok opname '.$opname->opname_number.' selesai. Selisih stok dan nilainya sudah dicatat.');
		redirect('main/stock_opname');
	}

	private function _render($data)
	{
		$data['judul_besar'] = 'Stok Opname';
		$data['judul_kecil'] = 'Hitung stok fisik dan tetapkan nilai pokok persediaan';
		$data['m_stock_opname'] = TRUE;
		if ($data['page'] === 'edit') {
			$data['css_files'] = array(
				base_url('assets/datatables/css/jquery.dataTables.min.css'),
				base_url('assets/jexcel/css/jexcel.css'),
				base_url('assets/jexcel/css/jsuites.css')
			);
		}
		if ($data['page'] === 'edit') {
			$data['js_files'] = array(
				base_url('assets/adminlte/plugins/jQuery/jQuery-2.1.4.min.js'),
				base_url('assets/jexcel/js/jsuites.js'),
				base_url('assets/jexcel/js/jexcel.js')
			);
		}
		if ($data['page'] === 'list') {
			$data['css_files'] = array(
				base_url('assets/datatables/css/jquery.dataTables.min.css')
			);
			$data['js_file'] = array(
				base_url('assets/datatables/js/jquery.dataTables.min.js')
			);
		}
		$data['output'] = $this->load->view('stock_opname', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	private function _get_opname($opname_id)
	{
		if (!is_numeric($opname_id) || (int) $opname_id < 1) {
			return NULL;
		}
		$query = $this->db->get_where('stock_opnames', array('id' => (int) $opname_id));
		return $query->num_rows() === 1 ? $query->row() : NULL;
	}

	private function _value_string($value)
	{
		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}

	private function _is_non_negative_integer($value)
	{
		return is_string($value) && preg_match('/^\d+$/', $value) && (float) $value <= 2147483647;
	}

	private function _is_valid_cost($value)
	{
		return is_string($value) && preg_match('/^\d{1,11}(?:\.\d{1,4})?$/', $value);
	}

	private function _redirect_error($message, $uri)
	{
		$this->session->set_flashdata('stock_opname_error', $message);
		redirect($uri);
	}

	private function _log_database_error($context)
	{
		$error = $this->db->error();
		log_message('error', $context.': '.$error['code'].' '.$error['message']);
	}
}
