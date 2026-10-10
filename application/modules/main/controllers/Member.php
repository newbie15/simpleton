<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Member extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper(array('url', 'html'));
		$this->load->library(array('ion_auth', 'page_access'));

		if (!$this->ion_auth->logged_in())
		{
			redirect('auth/login', 'refresh');
		}

		if (!$this->page_access->can_access('member_portal'))
		{
			show_error('Anda tidak memiliki akses ke portal anggota.', 403);
		}
	}

	public function index()
	{
		$user = $this->ion_auth->user()->row();
		$npk = isset($user->npk) ? trim($user->npk) : '';
		$member = NULL;
		if ($npk !== '')
		{
			$member = $this->db->where('npk', $npk)->get('karyawan')->row();
		}

		$data = array(
			'member_name' => $member ? $member->nama : trim($user->first_name.' '.$user->last_name),
			'member_npk' => $npk,
			'member_linked' => (bool) $member,
			'savings' => array(),
			'shu_history' => array(),
			'shu_total' => 0,
			'purchases' => array(),
			'purchase_count' => 0,
			'financial_summary' => array('income' => 0, 'expenses' => 0, 'net' => 0),
			'finance_year' => date('Y'),
			'products' => array(),
			'product_query' => trim((string) $this->input->get('q', TRUE)),
			'product_page' => max(1, min(1000, (int) $this->input->get('page'))),
			'product_count' => 0,
		);
		$data['product_query'] = substr($data['product_query'], 0, 100);

		if ($member)
		{
			$member_id = (int) $member->id;
			$data['savings'] = $this->db
				->select('jenis_simpanan, saldo')
				->where('karyawan_id', $member_id)
				->order_by('jenis_simpanan', 'ASC')
				->get('simpanan')
				->result();
			$shu_total = $this->db
				->select('COALESCE(SUM(nominal), 0) AS total', FALSE)
				->where('karyawan_id', $member_id)
				->where('jenis_mutasi', 'shu')
				->get('simpanan_mutasi')
				->row();
			$data['shu_total'] = $shu_total ? (float) $shu_total->total : 0;
			$data['shu_history'] = $this->db
				->select('nomor_bukti, nominal, tgl_transaksi, keterangan')
				->where('karyawan_id', $member_id)
				->where('jenis_mutasi', 'shu')
				->order_by('tgl_transaksi', 'DESC')
				->limit(50)
				->get('simpanan_mutasi')
				->result();
			$data['purchase_count'] = (int) $this->db->query(
				'SELECT COUNT(DISTINCT nota) AS total FROM penjualan WHERE id_karyawan = ?',
				array($npk)
			)->row()->total;

			$data['purchases'] = $this->db->query("
				SELECT sale.nota, MAX(sale.tgl) AS purchased_at, COUNT(*) AS item_count,
					COALESCE(MAX(payment.total_tagihan), SUM(sale.jumlah)) AS amount
				FROM penjualan sale
				LEFT JOIN penjualan_pembayaran payment ON payment.nota = sale.nota
				WHERE sale.id_karyawan = ?
				GROUP BY sale.nota
				ORDER BY MAX(sale.tgl) DESC
				LIMIT 20
			", array($npk))->result();

			if (count($data['purchases']) > 0)
			{
				$receipts = array();
				foreach ($data['purchases'] as $purchase)
				{
					$receipts[] = $purchase->nota;
				}
				$purchase_items = $this->db
					->select('sale.nota, sale.kode, MAX(stock.nama) AS product_name, SUM(sale.qty) AS quantity')
					->from('penjualan sale')
					->join('stok stock', 'stock.barcode = sale.kode', 'left')
					->where_in('sale.nota', $receipts)
					->where('sale.id_karyawan', $npk)
					->group_by(array('sale.nota', 'sale.kode'))
					->order_by('sale.nota', 'DESC')
					->get()
					->result();
				$items_by_receipt = array();
				foreach ($purchase_items as $purchase_item)
				{
					$items_by_receipt[$purchase_item->nota][] = $purchase_item;
				}
				foreach ($data['purchases'] as $purchase)
				{
					$purchase->items = isset($items_by_receipt[$purchase->nota])
						? $items_by_receipt[$purchase->nota]
						: array();
				}
			}

			$data['member_balance'] = array_sum(array_map(function ($saving) {
				return (float) $saving->saldo;
			}, $data['savings']));
		}
		else
		{
			$data['member_balance'] = 0;
		}

		$year_start = date('Y-01-01');
		$today = date('Y-m-d');
		$finance = $this->db->query("
			SELECT
				COALESCE(SUM(CASE WHEN account.type = 'income' THEN item.credit - item.debit ELSE 0 END), 0) AS income,
				COALESCE(SUM(CASE WHEN account.type IN ('cogs', 'expense') THEN item.debit - item.credit ELSE 0 END), 0) AS expenses
			FROM acc_journals journal
			JOIN acc_journal_items item ON item.journal_id = journal.id
			JOIN acc_accounts account ON account.id = item.account_id
			WHERE journal.is_posted = 1 AND journal.date BETWEEN ? AND ?
		", array($year_start, $today))->row();
		if ($finance)
		{
			$data['financial_summary']['income'] = (float) $finance->income;
			$data['financial_summary']['expenses'] = (float) $finance->expenses;
			$data['financial_summary']['net'] = $data['financial_summary']['income'] - $data['financial_summary']['expenses'];
		}

		$stock_query = $this->db->from('stok');
		if ($data['product_query'] !== '')
		{
			$stock_query->group_start()
				->like('nama', $data['product_query'])
				->or_like('barcode', $data['product_query'])
				->group_end();
		}
		$data['product_count'] = $stock_query->count_all_results();
		$data['product_pages'] = max(1, (int) ceil($data['product_count'] / 40));
		$data['product_page'] = min($data['product_page'], $data['product_pages']);
		$stock_query = $this->db->select('barcode, nama, jumlah, harga')->from('stok');
		if ($data['product_query'] !== '')
		{
			$stock_query->group_start()
				->like('nama', $data['product_query'])
				->or_like('barcode', $data['product_query'])
				->group_end();
		}
		$data['products'] = $stock_query
			->order_by('nama', 'ASC')
			->limit(40, ($data['product_page'] - 1) * 40)
			->get()
			->result();

		$this->load->view('member_portal', $data);
	}
}
