<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_conversion extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->database();
		$this->load->helper(array('url', 'form'));
		$this->load->library(array('session', 'ion_auth'));
		$this->load->library('page_access');

		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('product_conversion')) {
			show_error('Anda tidak memiliki akses untuk mengelola konversi kemasan.', 403);
		}
	}

	public function index()
	{
		$data['conversions'] = $this->db->query("
			SELECT conversion.id, conversion.barcode_dus, package.nama AS nama_kemasan_barang,
				conversion.barcode_pcs, piece.nama AS nama_barang,
				conversion.nama_kemasan, conversion.rasio
			FROM product_conversions conversion
			LEFT JOIN stok package ON package.barcode = conversion.barcode_dus
			LEFT JOIN stok piece ON piece.barcode = conversion.barcode_pcs
			ORDER BY piece.nama ASC, conversion.rasio DESC, conversion.barcode_dus ASC
		")->result();
		$data['notice'] = $this->session->flashdata('conversion_notice');
		$data['error'] = $this->session->flashdata('conversion_error');
		$data['judul_besar'] = 'Konversi Kemasan';
		$data['judul_kecil'] = 'Hubungkan barcode kemasan dengan stok satuan pcs';
		$data['m_product_conversion'] = TRUE;
		$data['output'] = $this->load->view('product_conversion', $data, TRUE);
		$this->load->view('welcome_message', $data);
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
			->select('barcode, nama')
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

	public function save($conversion_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			show_404();
		}

		$is_edit = $conversion_id !== NULL;
		if ($is_edit && (!$this->_is_positive_integer($conversion_id) ||
			!$this->db->get_where('product_conversions', array('id' => (int) $conversion_id))->row())) {
			show_404();
		}

		$barcode_dus = $this->input->post('barcode_dus');
		$barcode_pcs = $this->input->post('barcode_pcs');
		$nama_kemasan = $this->input->post('nama_kemasan');
		$rasio = $this->input->post('rasio');
		if (!is_string($barcode_dus) || !is_string($barcode_pcs) ||
			!is_string($nama_kemasan) || !is_string($rasio)) {
			$this->_redirect_error('Lengkapi data konversi kemasan.');
		}

		$barcode_dus = trim($barcode_dus);
		$barcode_pcs = trim($barcode_pcs);
		$nama_kemasan = trim($nama_kemasan);
		$rasio = trim($rasio);
		if ($barcode_dus === '' || strlen($barcode_dus) > 64 ||
			$barcode_pcs === '' || strlen($barcode_pcs) > 64 ||
			$barcode_dus === $barcode_pcs || $nama_kemasan === '' ||
			strlen($nama_kemasan) > 64 || !$this->_is_positive_integer($rasio) ||
			(int) $rasio < 2 || (float) $rasio > 2147483647) {
			$this->_redirect_error('Barcode wajib berbeda, nama kemasan maksimal 64 karakter, dan rasio harus bilangan bulat minimal 2.');
		}

		$product_rows = $this->db->query("
			SELECT id, barcode FROM stok
			WHERE barcode IN (?, ?)
			ORDER BY barcode ASC
		", array($barcode_dus, $barcode_pcs))->result();
		$products_by_barcode = array();
		foreach ($product_rows as $product) {
			if (isset($products_by_barcode[$product->barcode])) {
				$this->_redirect_error('Barcode kemasan terduplikasi di master stok. Perbaiki data barang terlebih dahulu.');
			}
			$products_by_barcode[$product->barcode] = $product;
		}
		if (!isset($products_by_barcode[$barcode_dus]) || !isset($products_by_barcode[$barcode_pcs])) {
			$this->_redirect_error('Barcode kemasan dan barcode pcs harus sudah terdaftar di master stok.');
		}
		if ((int) $products_by_barcode[$barcode_dus]->id === (int) $products_by_barcode[$barcode_pcs]->id) {
			$this->_redirect_error('Kemasan dus dan produk pcs harus merupakan barang yang berbeda di master stok.');
		}

		$duplicate_query = $this->db->where('barcode_dus', $barcode_dus);
		if ($is_edit) {
			$duplicate_query->where('id !=', (int) $conversion_id);
		}
		if ($duplicate_query->count_all_results('product_conversions') > 0) {
			$this->_redirect_error('Barcode dus tersebut sudah memiliki relasi konversi.');
		}

		$record = array(
			'barcode_dus' => $barcode_dus,
			'barcode_pcs' => $barcode_pcs,
			'nama_kemasan' => $nama_kemasan,
			'rasio' => (int) $rasio,
			'updated_at' => date('Y-m-d H:i:s')
		);
		$previous_db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		if ($is_edit) {
			$saved = $this->db->where('id', (int) $conversion_id)
				->update('product_conversions', $record);
		} else {
			$record['created_at'] = date('Y-m-d H:i:s');
			$saved = $this->db->insert('product_conversions', $record);
		}
		$error = $this->db->error();
		$this->db->db_debug = $previous_db_debug;
		if (!$saved) {
			log_message('error', 'Gagal menyimpan konversi kemasan: '.$error['code'].' '.$error['message']);
			$this->_redirect_error('Konversi kemasan gagal disimpan. Pastikan barcode dus belum digunakan.');
		}

		$this->session->set_flashdata('conversion_notice', 'Konversi kemasan berhasil disimpan.');
		redirect('main/product_conversion');
	}

	public function delete($conversion_id = NULL)
	{
		if ($this->input->method(TRUE) !== 'POST' || !$this->_is_positive_integer($conversion_id)) {
			show_404();
		}
		if (!$this->db->get_where('product_conversions', array('id' => (int) $conversion_id))->row()) {
			show_404();
		}

		if (!$this->db->delete('product_conversions', array('id' => (int) $conversion_id))) {
			$error = $this->db->error();
			log_message('error', 'Gagal menghapus relasi konversi kemasan '.$conversion_id.': '.$error['code'].' '.$error['message']);
			$this->_redirect_error('Relasi konversi kemasan gagal dihapus.');
		}
		$this->session->set_flashdata('conversion_notice', 'Relasi konversi dihapus. Data barang dan jumlah stok tidak berubah.');
		redirect('main/product_conversion');
	}

	private function _is_positive_integer($value)
	{
		return (is_string($value) || is_numeric($value)) &&
			preg_match('/^[1-9][0-9]*$/', (string) $value);
	}

	private function _json_response($payload, $status = 200)
	{
		$this->output->set_status_header($status);
		$this->output->set_content_type('application/json', 'utf-8');
		echo json_encode($payload);
		exit;
	}

	private function _redirect_error($message)
	{
		$this->session->set_flashdata('conversion_error', $message);
		redirect('main/product_conversion');
	}
}
