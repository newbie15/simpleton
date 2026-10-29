<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sales_tax
{
	private $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->database();
	}

	public function current_rate($lock = FALSE)
	{
		if (!$this->CI->db->table_exists('sales_tax_settings')) {
			return FALSE;
		}
		$sql = 'SELECT rate_percent FROM sales_tax_settings WHERE id = 1'.($lock ? ' FOR UPDATE' : '');
		$query = $this->CI->db->query($sql);
		if ($query === FALSE) {
			return FALSE;
		}
		$row = $query->row();
		if (!$row || !is_numeric($row->rate_percent) ||
			(float) $row->rate_percent <= 0 || (float) $row->rate_percent > 100) {
			return FALSE;
		}
		return (float) $row->rate_percent;
	}

	public function inclusive_amounts($gross, $rate_percent)
	{
		if (!is_numeric($gross) || !is_numeric($rate_percent) ||
			(float) $gross < 0 || (float) $rate_percent <= 0 || (float) $rate_percent > 100) {
			throw new InvalidArgumentException('Harga bruto atau tarif PPN tidak valid.');
		}
		$gross = (float) $gross;
		$dpp = round($gross * 100 / (100 + (float) $rate_percent), 0);
		return array(
			'dpp' => $dpp,
			'ppn' => $gross - $dpp
		);
	}

	public function sale_amounts($sale)
	{
		$has_dpp_snapshot = isset($sale->dpp_satuan) && $sale->dpp_satuan !== NULL;
		$has_ppn_snapshot = isset($sale->ppn_satuan) && $sale->ppn_satuan !== NULL;
		if ($has_dpp_snapshot !== $has_ppn_snapshot) {
			throw new UnexpectedValueException('Snapshot DPP/PPN penjualan tidak lengkap.');
		}
		if ($has_dpp_snapshot) {
			$qty = isset($sale->qty) ? (int) $sale->qty : 1;
			return array(
				'dpp' => (float) $sale->dpp_satuan * $qty,
				'ppn' => (float) $sale->ppn_satuan * $qty,
				'gross' => isset($sale->jumlah) ? (float) $sale->jumlah :
					((float) $sale->harga * $qty)
			);
		}

		$net = isset($sale->jumlah) ? (float) $sale->jumlah :
			((float) $sale->harga * (int) $sale->qty);
		$tax = $net * (isset($sale->ppn) ? (float) $sale->ppn : 0);
		return array(
			'dpp' => $net,
			'ppn' => $tax,
			'gross' => $net + $tax
		);
	}
}
