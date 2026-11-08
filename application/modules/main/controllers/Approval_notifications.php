<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Approval_notifications extends CI_Controller
{
	private $offset = 0;

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper('url');
		$this->load->library(array('ion_auth', 'page_access'));
	}

	public function index()
	{
		if (!$this->ion_auth->logged_in()) {
			$this->_respond(array('error' => 'Sesi login berakhir. Silakan login kembali.'), 401);
			return;
		}
		$user_id = (string) $this->ion_auth->get_user_id();
		$page = $this->input->get('page', TRUE);
		if ($page !== NULL && (!is_string($page) || !ctype_digit($page) || (int) $page < 1 || (int) $page > 100000)) {
			$this->_respond(array('error' => 'Halaman notifikasi tidak valid.'), 400);
			return;
		}
		$this->offset = ($page === NULL ? 0 : (int) $page - 1) * 10;
		$groups = array();
		$errors = array();
		$is_cash_approver = $this->ion_auth->in_group(array('admin', 'management', 'pengurus'));

		if ($is_cash_approver && $this->page_access->can_access('petty_cash')) {
			$this->_add_group($groups, $errors, 'Kas dan pembayaran', array('acc_cash_transactions', 'acc_cash_funds', 'users'),
				"FROM acc_cash_transactions t JOIN acc_cash_funds f ON f.id = t.fund_id JOIN users u ON u.id = t.created_by
				WHERE t.status = 'pending' AND t.created_by <> ?",
				array($user_id),
				"SELECT t.id, t.transaction_number AS reference, t.description AS details, t.amount, t.created_at AS created",
				't.id', 'main/petty_cash_new?tab=requests&transaction_id=', 'main/petty_cash_new?tab=requests');
		}
		if ($this->ion_auth->in_group(array('admin', 'management')) && $this->page_access->can_access('sales_return')) {
			$this->_add_group($groups, $errors, 'Retur penjualan', array('retur_penjualan'),
				"FROM retur_penjualan t WHERE t.status = 'pending'", array(),
				"SELECT t.id, t.nomor_retur AS reference, CONCAT('Nota ', t.nota_penjualan) AS details, t.total_retur AS amount, t.tgl_retur AS created",
				't.id', 'main/sales_return/view/', 'main/sales_return');
		}
		if ($this->ion_auth->in_group(array('admin', 'pengurus')) && $this->page_access->can_access('supplier_return')) {
			$this->_add_group($groups, $errors, 'Retur supplier', array('retur_supplier', 'suppliers'),
				"FROM retur_supplier t JOIN suppliers s ON s.id = t.supplier_id WHERE t.status = 'draft'", array(),
				"SELECT t.id, t.nomor_retur AS reference, s.name AS details, t.total_nominal AS amount, t.tgl_retur AS created",
				't.id', 'main/supplier_return/preview/', 'main/supplier_return');
		}
		if ($is_cash_approver && $this->page_access->can_access('accounting')) {
			$self_filter = $this->ion_auth->in_group('admin') ? '' : ' AND t.revised_by <> ?';
			$params = $self_filter === '' ? array() : array($user_id);
			$this->_add_group($groups, $errors, 'Revisi tagihan', array('sales_billing_adjustments', 'sales_billing_periods', 'sales_validation_runs', 'sales_validation_items'),
				"FROM sales_billing_adjustments t JOIN sales_billing_periods p ON p.id = t.period_id
				WHERE t.status = 'pending' AND p.status = 'open'".$self_filter."
				AND EXISTS (SELECT 1 FROM sales_validation_items vi
					WHERE vi.nota = t.nota AND vi.run_id = (
						SELECT r.id FROM sales_validation_runs r WHERE r.period_id = t.period_id
						AND r.validation_mode IN ('period', 'cutoff') ORDER BY r.id DESC LIMIT 1))",
				$params,
				"SELECT t.id, t.nota AS reference, p.period_name AS details, t.correct_total AS amount, t.revised_at AS created, t.period_id",
				't.id', 'main/sales_billing/revisions/', 'main/sales_billing', TRUE);
		}
		$this->_respond(array('groups' => $groups, 'errors' => $errors), 200);
	}

	private function _add_group(&$groups, &$errors, $label, $tables, $from, $params, $select, $order, $link, $list_link, $billing = FALSE)
	{
		foreach ($tables as $table) {
			if (!$this->db->table_exists($table)) {
				$errors[] = $label.': skema belum tersedia.';
				return;
			}
		}
		$previous_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$count_query = $this->db->query('SELECT COUNT(*) AS total '.$from, $params);
		$items_query = $count_query ? $this->db->query($select.' '.$from.' ORDER BY '.$order.' DESC LIMIT 10 OFFSET '.$this->offset, $params) : FALSE;
		$this->db->db_debug = $previous_debug;
		if (!$count_query || !$items_query) {
			$error = $this->db->error();
			log_message('error', 'Gagal memuat notifikasi persetujuan '.$label.': '.$error['code'].' '.$error['message']);
			$errors[] = $label.': tidak dapat memuat data.';
			return;
		}
		$items = array();
		foreach ($items_query->result() as $row) {
			$path = $billing ? $link.(int) $row->period_id.'?adjustment_id='.(int) $row->id : $link.(int) $row->id;
			$items[] = array(
				'reference' => $row->reference,
				'details' => $row->details,
				'amount' => $row->amount,
				'created' => $row->created,
				'url' => site_url($path)
			);
		}
		$count = $count_query->row();
		$groups[] = array('label' => $label, 'count' => (int) $count->total,
			'items' => $items, 'list_url' => site_url($list_link));
	}

	private function _respond($data, $status)
	{
		$this->output->set_status_header($status)
			->set_content_type('application/json', 'utf-8')
			->set_header('Cache-Control: private, no-store')
			->set_output(json_encode($data));
	}
}
