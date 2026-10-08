<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Stock_opname extends CI_Controller
{
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

		$this->db->trans_begin();
		$products = $this->db->select('id, barcode, nama, jumlah')
			->from('stok')
			->order_by('barcode', 'ASC')
			->get()
			->result();
		if (count($products) === 0) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('stock_opname_error', 'Tidak ada barang di master stok untuk dihitung.');
			redirect('main/stock_opname');
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
					'unit_cost' => NULL
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

	public function edit($opname_id = NULL)
	{
		$opname = $this->_get_opname($opname_id);
		if (!$opname) {
			show_404();
		}

		$data['opname'] = $opname;
		$data['items'] = $this->db->where('stock_opname_id', (int) $opname_id)
			->order_by('barcode', 'ASC')
			->get('stock_opname_items')
			->result();
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
			if (!is_array($row) || count($row) < 3 || !is_numeric($row[0])) {
				$this->_redirect_error('Format baris hitungan tidak valid.', 'main/stock_opname/edit/'.$opname_id);
			}
			$item_id = (int) $row[0];
			if (!isset($snapshot_by_id[$item_id]) || isset($validated[$item_id])) {
				$this->_redirect_error('Daftar barang hitungan tidak sesuai dengan draft opname.', 'main/stock_opname/edit/'.$opname_id);
			}

			$counted_qty = trim($this->_value_string($row[1]));
			$unit_cost = trim($this->_value_string($row[2]));
			if ($counted_qty === '' && $unit_cost === '') {
				$validated[$item_id] = array('counted_qty' => NULL, 'unit_cost' => NULL);
				continue;
			}
			if (!$this->_is_non_negative_integer($counted_qty)) {
				$this->_redirect_error('Qty fisik harus bilangan bulat nol atau lebih.', 'main/stock_opname/edit/'.$opname_id);
			}
			if ((int) $counted_qty > 0 && !$this->_is_valid_cost($unit_cost)) {
				$this->_redirect_error('Isi harga pokok per unit dengan angka nol atau lebih (maksimal 4 desimal) untuk barang yang memiliki stok fisik.', 'main/stock_opname/edit/'.$opname_id);
			}
			if ($unit_cost !== '' && !$this->_is_valid_cost($unit_cost)) {
				$this->_redirect_error('Format harga pokok tidak valid. Gunakan angka dengan titik untuk desimal, maksimal 4 digit.', 'main/stock_opname/edit/'.$opname_id);
			}
			$validated[$item_id] = array(
				'counted_qty' => (int) $counted_qty,
				'unit_cost' => $unit_cost === '' ? NULL : $unit_cost
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
			SELECT item.*, stock.jumlah AS current_qty, stock.barcode AS current_barcode
			FROM stock_opname_items item
			JOIN stok stock ON stock.id = item.product_id
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
			if ($item->counted_qty === NULL ||
				(((int) $item->counted_qty > 0 || (int) $item->system_qty > 0) && $item->unit_cost === NULL)) {
				$this->db->trans_rollback();
				$this->_redirect_error('Lengkapi qty fisik dan harga pokok barang yang memiliki stok sebelum menyelesaikan opname.', 'main/stock_opname/edit/'.$opname_id);
			}
			$adjustment = (int) $item->counted_qty - (int) $item->system_qty;
			$adjustment_value = round(abs($adjustment) * (float) $item->unit_cost, 2);
			$adjusted_qty = (int) $item->current_qty + $adjustment;
			if ($adjusted_qty < 0) {
				$this->db->trans_rollback();
				$this->_redirect_error('Stok saat ini tidak cukup untuk menerapkan selisih opname pada barcode '.$item->barcode.'. Periksa transaksi yang terjadi setelah opname dimulai.', 'main/stock_opname/edit/'.$opname_id);
			}
			$this->db->set('jumlah', 'jumlah + '.$adjustment, FALSE)
				->where('id', (int) $item->product_id)
				->update('stok');
			$unit_cost = $item->unit_cost === NULL ? '0.0000' : $item->unit_cost;
			$this->db->query("
				INSERT INTO inventory_costs (product_id, barcode, average_cost, updated_at)
				VALUES (?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE barcode = VALUES(barcode), average_cost = VALUES(average_cost), updated_at = VALUES(updated_at)
			", array((int) $item->product_id, $item->current_barcode, $unit_cost, date('Y-m-d H:i:s')));
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
		$this->session->set_flashdata('stock_opname_notice', 'Stok opname '.$opname->opname_number.' selesai. Selisih stok diterapkan dan nilai pokok persediaan awal dicatat.');
		redirect('main/stock_opname');
	}

	private function _render($data)
	{
		$data['judul_besar'] = 'Stok Opname';
		$data['judul_kecil'] = 'Hitung stok fisik dan tetapkan nilai pokok persediaan';
		$data['m_stock_opname'] = TRUE;
		if ($data['page'] === 'edit') {
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
