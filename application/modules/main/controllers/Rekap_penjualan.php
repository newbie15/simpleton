<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rekap_penjualan extends CI_Controller
{
	public function index()
	{
		$this->load->library('page_access');
		if (!$this->page_access->can_access('rekap_penjualan')) {
			show_error('Anda tidak memiliki akses ke rekap penjualan.', 403);
		}
		$this->load->helper('url');
		$data['css_file'] = array(
			base_url('assets/jexcel/css/jexcel.css'),
			base_url('assets/jexcel/css/jsuites.css'),
			base_url('assets/easyautocomplete/easy-autocomplete.min.css')
		);
		$data['js_file'] = array(
			base_url('assets/adminlte/plugins/chartjs/Chart.min.js'),
			base_url('assets/easyautocomplete/jquery.easy-autocomplete.min.js'),
			base_url('assets/jexcel/js/jexcel.js'),
			base_url('assets/jexcel/js/jsuites.js'),
			base_url('assets/papaparse/papaparse.min.js'),
			base_url('assets/kasir/rekap.js?v=' . filemtime(FCPATH . 'assets/kasir/rekap.js'))
		);
		$data['ajax_base'] = site_url('main/ajax');
		$data['rekap_base'] = site_url('main/rekap_penjualan');
		$data['reprint_base'] = site_url('main/kasir/reprint');
		$data['m_rekap'] = TRUE;
		$data['output'] = $this->load->view('rekap_penjualan', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	// 1. DETAIL NOTA MODAL POPUP
	public function detail_nota()
	{
		$this->load->library('page_access');
		if (!$this->page_access->can_access('rekap_penjualan')) {
			$this->_json(FALSE, 'Akses ditolak.');
		}

		$nota = trim((string) $this->input->get('nota', TRUE));
		if ($nota === '') {
			$this->_json(FALSE, 'Nomor nota tidak boleh kosong.');
		}

		$receipt = $this->db->query("
			SELECT sale.nota, sale.id_karyawan AS npk, MIN(sale.tgl) AS transaction_date,
				COALESCE(employee.nama, sale.id_karyawan) AS employee_name,
				employee.bagian,
				COALESCE(NULLIF(CONCAT_WS(' ', cashier.first_name, cashier.last_name), ''), cashier.username, payment.id_user, sale.id_user) AS cashier_name,
				COALESCE(payment.metode_pembayaran, sale.metode_pembayaran, 'potong_gaji') AS payment_method,
				COALESCE(payment.total_tagihan,
					SUM(CASE WHEN sale.dpp_satuan IS NOT NULL THEN sale.jumlah
						ELSE sale.jumlah + sale.jumlah * COALESCE(sale.ppn, 0) END),
					SUM(sale.jumlah)) AS total_paid,
				COALESCE(payment.diskon_voucher, 0) AS voucher_discount,
				COALESCE(payment.kode_voucher, '') AS voucher_code,
				COALESCE(payment.nominal_bayar, 0) AS amount_paid,
				COALESCE(payment.kembalian, 0) AS change_amount
			FROM penjualan sale
			LEFT JOIN penjualan_pembayaran payment ON payment.nota = sale.nota
			LEFT JOIN karyawan employee ON employee.npk = sale.id_karyawan
			LEFT JOIN users cashier ON cashier.id = payment.id_user
			WHERE sale.nota = ?
			GROUP BY sale.nota, sale.id_karyawan, employee.nama, employee.bagian, cashier.first_name,
				cashier.last_name, cashier.username, payment.id_user, sale.id_user,
				payment.metode_pembayaran, sale.metode_pembayaran, payment.total_tagihan,
				payment.diskon_voucher, payment.kode_voucher, payment.nominal_bayar, payment.kembalian
		", array($nota))->row();

		if (!$receipt) {
			$this->_json(FALSE, 'Nota tidak ditemukan.');
		}

		$items = $this->db->query("
			SELECT sale.kode AS barcode, COALESCE(stock.nama, sale.kode) AS product_name,
				sale.qty, sale.harga, sale.jumlah, sale.ppn,
				sale.dpp_satuan, sale.ppn_satuan
			FROM penjualan sale
			LEFT JOIN stok stock ON stock.barcode = sale.kode
			WHERE sale.nota = ?
			ORDER BY sale.id ASC
		", array($nota))->result();

		$this->_json(TRUE, 'Detail nota berhasil dimuat', array(
			'receipt' => $receipt,
			'items' => $items
		));
	}

	// 2. TOP PRODUK & GRAFIK TREN PENJUALAN
	public function top_produk()
	{
		$this->load->library('page_access');
		if (!$this->page_access->can_access('rekap_penjualan')) {
			$this->_json(FALSE, 'Akses ditolak.');
		}

		$start_date = $this->input->get('start_date', TRUE);
		$end_date = $this->input->get('end_date', TRUE);

		if (!$start_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
			$start_date = date('Y-m-01');
		}
		if (!$end_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
			$end_date = date('Y-m-d');
		}

		$start = $start_date . ' 00:00:00';
		$end = $end_date . ' 23:59:59';

		// Top 10 Produk Terlaris
		$top_products = $this->db->query("
			SELECT sale.kode AS barcode,
				COALESCE(stock.nama, sale.kode) AS item,
				SUM(sale.qty) AS total_qty,
				ROUND(SUM(CASE WHEN sale.dpp_satuan IS NOT NULL THEN sale.jumlah
					ELSE sale.jumlah + sale.jumlah * COALESCE(sale.ppn, 0) END), 2) AS total_omset
			FROM penjualan sale
			LEFT JOIN stok stock ON stock.barcode = sale.kode
			WHERE sale.tgl >= ? AND sale.tgl <= ?
			GROUP BY sale.kode, stock.nama
			ORDER BY total_qty DESC
			LIMIT 10
		", array($start, $end))->result();

		// Tren Penjualan Harian
		$daily_trends = $this->db->query("
			SELECT DATE(sale.tgl) AS tgl,
				COUNT(DISTINCT sale.nota) AS total_nota,
				SUM(sale.qty) AS total_qty,
				ROUND(SUM(CASE WHEN sale.dpp_satuan IS NOT NULL THEN sale.jumlah
					ELSE sale.jumlah + sale.jumlah * COALESCE(sale.ppn, 0) END), 2) AS total_omset
			FROM penjualan sale
			WHERE sale.tgl >= ? AND sale.tgl <= ?
			GROUP BY DATE(sale.tgl)
			ORDER BY tgl ASC
		", array($start, $end))->result();

		$this->_json(TRUE, 'Data analitik berhasil dimuat', array(
			'top_products' => $top_products,
			'daily_trends' => $daily_trends
		));
	}

	// 3. EXPORT EXCEL (.XLSX)
	public function export_excel()
	{
		$this->load->library('page_access');
		if (!$this->page_access->can_access('rekap_penjualan')) {
			show_error('Akses ditolak.', 403);
		}

		$type = $this->input->get('type', TRUE);
		$date = $this->input->get('date', TRUE);
		$period = $this->input->get('period', TRUE);
		$npk = $this->input->get('npk', TRUE);

		require_once APPPATH . 'third_party/PHPExcel.php';
		$excel = new PHPExcel();
		$excel->setActiveSheetIndex(0);
		$sheet = $excel->getActiveSheet();

		if ($type === 'harian') {
			if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				$date = date('Y-m-d');
			}
			$start = $date . ' 00:00:00';
			$end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';

			$rows = $this->db->query("
				SELECT sale.nota, sale.id_karyawan AS npk, employee.nama, employee.bagian,
					sale.kode AS barcode, product.nama AS item,
					CASE WHEN sale.dpp_satuan IS NOT NULL THEN sale.harga
						ELSE sale.harga * (1 + COALESCE(sale.ppn, 0)) END AS harga,
					sale.qty,
					CASE WHEN sale.dpp_satuan IS NOT NULL THEN sale.jumlah
						ELSE sale.jumlah + sale.jumlah * COALESCE(sale.ppn, 0) END AS jumlah,
					ROUND(COALESCE(approved_return.amount, 0), 2) AS retur_disetujui,
					ROUND(CASE WHEN sale.dpp_satuan IS NOT NULL THEN sale.jumlah
						ELSE sale.jumlah + sale.jumlah * COALESCE(sale.ppn, 0) END -
						COALESCE(approved_return.amount, 0), 2) AS tertagih,
					sale.metode_pembayaran
				FROM penjualan sale
				LEFT JOIN stok product ON product.barcode = sale.kode
				LEFT JOIN karyawan employee ON employee.npk = sale.id_karyawan
				LEFT JOIN (
					SELECT return_item.penjualan_id, SUM(return_item.subtotal) AS amount
					FROM retur_penjualan_items return_item
					JOIN retur_penjualan return_header ON return_header.id = return_item.retur_id
					WHERE return_header.status IN ('approved', 'completed')
					GROUP BY return_item.penjualan_id
				) approved_return ON approved_return.penjualan_id = sale.id
				WHERE sale.tgl >= ? AND sale.tgl < ?
				ORDER BY sale.tgl ASC, sale.id ASC
			", array($start, $end))->result();

			$sheet->setTitle('Rekap Harian');
			$sheet->setCellValue('A1', 'REKAP PENJUALAN HARIAN: ' . date('d/m/Y', strtotime($date)));
			$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

			$headers = array('No', 'Nota', 'NPK', 'Nama Anggota', 'Bagian', 'Barcode', 'Nama Item', 'Harga', 'Qty', 'Jumlah (Rp)', 'Retur (Rp)', 'Tertagih (Rp)', 'Metode');
			$col = 'A';
			foreach ($headers as $h) {
				$sheet->setCellValue($col . '3', $h);
				$sheet->getStyle($col . '3')->getFont()->setBold(true);
				$col++;
			}

			$r = 4;
			$no = 1;
			foreach ($rows as $row) {
				$sheet->setCellValue('A' . $r, $no++);
				$sheet->setCellValueExplicit('B' . $r, $row->nota, PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('C' . $r, $row->npk, PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->setCellValue('D' . $r, $row->nama);
				$sheet->setCellValue('E' . $r, $row->bagian);
				$sheet->setCellValueExplicit('F' . $r, $row->barcode, PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->setCellValue('G' . $r, $row->item);
				$sheet->setCellValue('H' . $r, (float) $row->harga);
				$sheet->setCellValue('I' . $r, (int) $row->qty);
				$sheet->setCellValue('J' . $r, (float) $row->jumlah);
				$sheet->setCellValue('K' . $r, (float) $row->retur_disetujui);
				$sheet->setCellValue('L' . $r, (float) $row->tertagih);
				$sheet->setCellValue('M' . $r, ucfirst($row->metode_pembayaran));

				$sheet->getStyle('H' . $r . ':L' . $r)->getNumberFormat()->setFormatCode('#,##0');
				$r++;
			}

			if ($r > 4) {
				$sheet->setCellValue('A' . $r, 'TOTAL');
				$sheet->mergeCells('A' . $r . ':H' . $r);
				$sheet->getStyle('A' . $r . ':M' . $r)->getFont()->setBold(true);
				$sheet->setCellValue('I' . $r, '=SUM(I4:I' . ($r - 1) . ')');
				$sheet->setCellValue('J' . $r, '=SUM(J4:J' . ($r - 1) . ')');
				$sheet->setCellValue('K' . $r, '=SUM(K4:K' . ($r - 1) . ')');
				$sheet->setCellValue('L' . $r, '=SUM(L4:L' . ($r - 1) . ')');
				$sheet->getStyle('I' . $r . ':L' . $r)->getNumberFormat()->setFormatCode('#,##0');
			}

			foreach (range('A', 'M') as $c) {
				$sheet->getColumnDimension($c)->setAutoSize(true);
			}

			$filename = 'Rekap_Penjualan_Harian_' . $date . '.xlsx';
		} else {
			// Default export umum
			$sheet->setCellValue('A1', 'Laporan Rekap Penjualan');
			$filename = 'Rekap_Penjualan_' . date('Ymd_His') . '.xlsx';
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');

		$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
		$writer->save('php://output');
		exit;
	}

	private function _json($success, $message, $data = array())
	{
		header('Content-Type: application/json');
		echo json_encode(array(
			'success' => (bool) $success,
			'message' => $message,
			'data' => $data
		));
		exit;
	}
}
