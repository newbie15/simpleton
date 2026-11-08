<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Simpanan extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper(array('url', 'html', 'date'));
		$this->load->library(array('ion_auth', 'page_access', 'accounting'));

		if (!$this->ion_auth->logged_in())
		{
			redirect('auth/login', 'refresh');
		}

		$allowed_groups = array('admin', 'pengurus', 'management');
		$in_group = FALSE;
		foreach ($allowed_groups as $group)
		{
			if ($this->ion_auth->in_group($group))
			{
				$in_group = TRUE;
				break;
			}
		}

		if (!$in_group && !$this->page_access->can_access('simpanan'))
		{
			show_error('Anda tidak memiliki akses ke manajemen simpanan.', 403);
		}
	}

	public function index()
	{
		$this->load->helper('url');
		
		$tab = $this->input->get('tab', TRUE);
		if (!in_array($tab, array('pokok', 'wajib', 'sukarela', 'rekap', 'tagihan_wajib')))
		{
			// backward compatibility jika pakai ?jenis=
			$jenis_param = $this->input->get('jenis', TRUE);
			if (in_array($jenis_param, array('pokok', 'wajib', 'sukarela')))
			{
				$tab = $jenis_param;
			}
			else
			{
				$tab = 'rekap';
			}
		}

		$data['tab_active'] = $tab;
		$data['jenis_selected'] = in_array($tab, array('pokok', 'wajib', 'sukarela')) ? $tab : 'pokok';

		// 1. DATA REKAP SEMUA ANGGOTA (POKOK + WAJIB + SUKARELA)
		$data['members_summary'] = $this->db->query("
			SELECT 
				k.id,
				k.npk,
				k.nama,
				k.bagian,
				COALESCE(k.is_active, 1) AS is_active,
				k.resign_date,
				COALESCE(SUM(CASE WHEN s.jenis_simpanan = 'pokok' THEN s.saldo ELSE 0 END), 0) AS saldo_pokok,
				COALESCE(SUM(CASE WHEN s.jenis_simpanan = 'wajib' THEN s.saldo ELSE 0 END), 0) AS saldo_wajib,
				COALESCE(SUM(CASE WHEN s.jenis_simpanan = 'sukarela' THEN s.saldo ELSE 0 END), 0) AS saldo_sukarela,
				COALESCE(SUM(s.saldo), 0) AS total_saldo
			FROM karyawan k
			LEFT JOIN simpanan s ON s.karyawan_id = k.id
			GROUP BY k.id, k.npk, k.nama, k.bagian, k.is_active, k.resign_date
			ORDER BY k.nama ASC
		")->result();

		// Grand Total Saldo Rekap
		$rekap_totals = $this->db->query("
			SELECT 
				COALESCE(SUM(CASE WHEN jenis_simpanan = 'pokok' THEN saldo ELSE 0 END), 0) AS grand_pokok,
				COALESCE(SUM(CASE WHEN jenis_simpanan = 'wajib' THEN saldo ELSE 0 END), 0) AS grand_wajib,
				COALESCE(SUM(CASE WHEN jenis_simpanan = 'sukarela' THEN saldo ELSE 0 END), 0) AS grand_sukarela,
				COALESCE(SUM(saldo), 0) AS grand_total
			FROM simpanan
		")->row();
		$data['rekap_totals'] = $rekap_totals;

		// 2. DATA PER JENIS SIMPANAN TERTENTU (POKOK / WAJIB / SUKARELA)
		$data['members_savings'] = $this->db->query("
			SELECT 
				k.id,
				k.npk,
				k.nama,
				k.bagian,
				COALESCE(k.is_active, 1) AS is_active,
				k.resign_date,
				s.jenis_simpanan,
				COALESCE(s.saldo, 0) AS saldo,
				s.updated_at,
				COUNT(sm.id) AS mutasi_count
			FROM karyawan k
			LEFT JOIN simpanan s ON s.karyawan_id = k.id AND s.jenis_simpanan = ?
			LEFT JOIN simpanan_mutasi sm ON sm.karyawan_id = k.id AND sm.jenis_simpanan = s.jenis_simpanan
			GROUP BY k.id, s.jenis_simpanan
			ORDER BY k.nama ASC
		", array($data['jenis_selected']))->result();

		$data['total_saldo'] = $this->db->query("
			SELECT COALESCE(SUM(saldo), 0) AS total
			FROM simpanan
			WHERE jenis_simpanan = ?
		", array($data['jenis_selected']))->row()->total;

		// 3. DATA SIMPANAN WAJIB BULAN INI (STATUS PEMBAYARAN)
		$periode_ini = $this->input->get('periode', TRUE);
		if (!$periode_ini || !preg_match('/^\d{4}-\d{2}$/', $periode_ini))
		{
			$periode_ini = date('Y-m');
		}
		$data['periode_ini'] = $periode_ini;

		// Hitung anggota aktif yang sudah setor wajib di periode ini
		$bulan_start = $periode_ini.'-01 00:00:00';
		$bulan_end = date('Y-m-t 23:59:59', strtotime($bulan_start));

		$wajib_paid = $this->db->query("
			SELECT DISTINCT karyawan_id 
			FROM simpanan_mutasi 
			WHERE jenis_simpanan = 'wajib' 
			  AND jenis_mutasi = 'setor'
			  AND tgl_transaksi >= ? AND tgl_transaksi <= ?
		", array($bulan_start, $bulan_end))->result();

		$paid_ids = array();
		foreach ($wajib_paid as $wp)
		{
			$paid_ids[] = (int) $wp->karyawan_id;
		}

		$total_anggota_aktif = (int) $this->db->where('is_active', 1)->count_all_results('karyawan');
		$data['total_anggota_aktif'] = $total_anggota_aktif;
		$data['total_wajib_lunas'] = count($paid_ids);
		$data['total_wajib_belum'] = max(0, $total_anggota_aktif - count($paid_ids));
		$data['paid_karyawan_ids'] = $paid_ids;

		// Tagihan batch history
		$data['batch_tagihan'] = $this->db->order_by('periode', 'DESC')->get('simpanan_wajib_tagihan')->result();

		// Daftar karyawan untuk modal transaksi
		$data['karyawan_all'] = $this->db->query("
			SELECT id, npk, nama, bagian, COALESCE(is_active, 1) AS is_active, resign_date
			FROM karyawan
			ORDER BY nama ASC
		")->result();

		// Kas / Bank accounts
		$data['cash_accounts'] = $this->db->query("
			SELECT id, code, name
			FROM acc_accounts
			WHERE type = 'asset' AND is_active = 1 AND (code LIKE '110%' OR name LIKE '%kas%' OR name LIKE '%bank%')
			ORDER BY code ASC
		")->result();

		$data['m_simpanan'] = TRUE;
		
		ob_start();
		$this->load->view('simpanan', $data);
		$data['output'] = ob_get_clean();
		
		$this->load->view('welcome_message', $data);
	}

	public function history($karyawan_id = 0)
	{
		$karyawan_id = (int) $karyawan_id;
		if (!$karyawan_id)
		{
			show_error('Member tidak ditemukan', 404);
		}

		$member = $this->db->where('id', $karyawan_id)->get('karyawan')->row();
		if (!$member)
		{
			show_error('Member tidak ditemukan', 404);
		}

		$jenis = $this->input->get('jenis', TRUE);
		if (!in_array($jenis, array('semua', 'pokok', 'wajib', 'sukarela')))
		{
			$jenis = 'semua';
		}

		$start_date = $this->input->get('start_date', TRUE);
		$end_date = $this->input->get('end_date', TRUE);

		$where_clauses = array("sm.karyawan_id = " . (int) $karyawan_id);
		if ($jenis !== 'semua')
		{
			$where_clauses[] = "sm.jenis_simpanan = " . $this->db->escape($jenis);
		}
		if ($start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date))
		{
			$where_clauses[] = "DATE(sm.tgl_transaksi) >= " . $this->db->escape($start_date);
		}
		if ($end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))
		{
			$where_clauses[] = "DATE(sm.tgl_transaksi) <= " . $this->db->escape($end_date);
		}

		$where_sql = implode(' AND ', $where_clauses);

		$data['member'] = $member;
		$data['jenis'] = $jenis;
		$data['start_date'] = $start_date;
		$data['end_date'] = $end_date;

		$data['transactions'] = $this->db->query("
			SELECT sm.*, j.journal_number, j.is_posted
			FROM simpanan_mutasi sm
			LEFT JOIN acc_journals j ON j.reference_type = 'SIMPANAN' AND j.reference_id = sm.nomor_bukti
			WHERE $where_sql
			ORDER BY sm.tgl_transaksi DESC
		")->result();

		// Rekap Saldo Per Jenis
		$saldo_rows = $this->db->where('karyawan_id', $karyawan_id)->get('simpanan')->result();
		$saldos = array('pokok' => 0, 'wajib' => 0, 'sukarela' => 0);
		foreach ($saldo_rows as $sr)
		{
			$saldos[$sr->jenis_simpanan] = (float) $sr->saldo;
		}
		$data['saldos'] = $saldos;

		$data['m_simpanan'] = TRUE;
		
		ob_start();
		$this->load->view('simpanan_history', $data);
		$data['output'] = ob_get_clean();
		
		$this->load->view('welcome_message', $data);
	}

	public function export_history($karyawan_id = 0)
	{
		$karyawan_id = (int) $karyawan_id;
		$member = $this->db->where('id', $karyawan_id)->get('karyawan')->row();
		if (!$member)
		{
			show_error('Member tidak ditemukan', 404);
		}

		$jenis = $this->input->get('jenis', TRUE);
		if (!in_array($jenis, array('semua', 'pokok', 'wajib', 'sukarela')))
		{
			$jenis = 'semua';
		}

		$start_date = $this->input->get('start_date', TRUE);
		$end_date = $this->input->get('end_date', TRUE);

		$where_clauses = array("sm.karyawan_id = " . (int) $karyawan_id);
		if ($jenis !== 'semua')
		{
			$where_clauses[] = "sm.jenis_simpanan = " . $this->db->escape($jenis);
		}
		if ($start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date))
		{
			$where_clauses[] = "DATE(sm.tgl_transaksi) >= " . $this->db->escape($start_date);
		}
		if ($end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))
		{
			$where_clauses[] = "DATE(sm.tgl_transaksi) <= " . $this->db->escape($end_date);
		}

		$where_sql = implode(' AND ', $where_clauses);
		$txs = $this->db->query("
			SELECT sm.*, j.journal_number
			FROM simpanan_mutasi sm
			LEFT JOIN acc_journals j ON j.reference_type = 'SIMPANAN' AND j.reference_id = sm.nomor_bukti
			WHERE $where_sql
			ORDER BY sm.tgl_transaksi ASC
		")->result();

		$filename = 'Mutasi_Simpanan_' . $member->npk . '_' . date('Ymd_His') . '.csv';
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'"');

		$out = fopen('php://output', 'w');
		fputcsv($out, array('BUKU REKENING SIMPANAN ANGGOTA - KOPERASI'), ';');
		fputcsv($out, array('NPK: ' . $member->npk, 'Nama: ' . $member->nama, 'Bagian: ' . $member->bagian, 'Status: ' . ($member->is_active ? 'Aktif' : 'Resign')), ';');
		fputcsv($out, array('Filter Jenis: ' . ucfirst($jenis), 'Periode: ' . ($start_date ? $start_date : 'Awal') . ' s/d ' . ($end_date ? $end_date : 'Hari ini')), ';');
		fputcsv($out, array(), ';');
		fputcsv($out, array('No', 'Tanggal', 'Nomor Bukti', 'No. Jurnal', 'Jenis Simpanan', 'Jenis Mutasi', 'Nominal (Rp)', 'Saldo Sebelum (Rp)', 'Saldo Sesudah (Rp)', 'Keterangan'), ';');

		$no = 1;
		foreach ($txs as $row)
		{
			$sign = ($row->jenis_mutasi === 'tarik') ? -1 : 1;
			fputcsv($out, array(
				$no++,
				$row->tgl_transaksi,
				$row->nomor_bukti,
				$row->journal_number ? $row->journal_number : '-',
				ucfirst($row->jenis_simpanan),
				ucfirst($row->jenis_mutasi),
				number_format($sign * $row->nominal, 2, ',', '.'),
				number_format($row->saldo_sebelum, 2, ',', '.'),
				number_format($row->saldo_setelah, 2, ',', '.'),
				$row->keterangan
			), ';');
		}

		fclose($out);
		exit;
	}

	public function save_transaksi()
	{
		if ($this->input->server('REQUEST_METHOD') !== 'POST')
		{
			json_response(FALSE, 'Metode request tidak valid', array());
		}

		$karyawan_id = (int) $this->input->post('karyawan_id', TRUE);
		$jenis_simpanan = strtolower(trim((string) $this->input->post('jenis_simpanan', TRUE)));
		$jenis_mutasi = strtolower(trim((string) $this->input->post('jenis_mutasi', TRUE)));
		$raw_nominal = $this->input->post('nominal', TRUE);
		$keterangan = trim((string) $this->input->post('keterangan', TRUE));
		$kas_account_code = trim((string) $this->input->post('kas_account_code', TRUE));
		$tgl_transaksi = trim((string) $this->input->post('tgl_transaksi', TRUE));

		if (!$tgl_transaksi || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_transaksi))
		{
			$tgl_transaksi = date('Y-m-d');
		}

		$nominal = $this->_parse_amount($raw_nominal);

		if ($karyawan_id <= 0 || $nominal <= 0)
		{
			json_response(FALSE, 'Pilih anggota dan masukkan nominal lebih dari 0', array());
		}

		if (!in_array($jenis_simpanan, array('pokok', 'wajib', 'sukarela')))
		{
			json_response(FALSE, 'Jenis simpanan tidak valid', array());
		}

		if (!in_array($jenis_mutasi, array('setor', 'tarik', 'bunga', 'shu', 'koreksi')))
		{
			json_response(FALSE, 'Jenis mutasi tidak valid', array());
		}

		$member = $this->db->where('id', $karyawan_id)->get('karyawan')->row();
		if (!$member)
		{
			json_response(FALSE, 'Data anggota/karyawan tidak ditemukan', array());
		}

		// VALIDASI RESIGN: Penarikan simpanan hanya boleh jika karyawan sudah resign (is_active = 0)
		$is_active = isset($member->is_active) ? (int) $member->is_active : 1;
		if ($jenis_mutasi === 'tarik' && $is_active === 1)
		{
			json_response(FALSE, 'Penarikan simpanan hanya dapat dilakukan oleh anggota yang SUDAH RESIGN / keluar.', array(
				'is_active' => $is_active,
				'nama' => $member->nama
			));
		}

		// Cek akun kas jika diisi
		if ($kas_account_code !== '')
		{
			$acc = $this->db->get_where('acc_accounts', array('code' => $kas_account_code, 'is_active' => 1))->row();
			if (!$acc)
			{
				json_response(FALSE, 'Akun Kas/Bank yang dipilih tidak valid atau tidak aktif', array());
			}
		}

		$this->db->trans_start();

		$simpanan = $this->db
			->where('karyawan_id', $member->id)
			->where('jenis_simpanan', $jenis_simpanan)
			->get('simpanan')
			->row();

		$saldo_sebelum = $simpanan ? (float) $simpanan->saldo : 0;
		if ($jenis_mutasi === 'tarik' && $saldo_sebelum < $nominal)
		{
			$this->db->trans_rollback();
			json_response(FALSE, 'Saldo simpanan '.ucfirst($jenis_simpanan).' tidak mencukupi. Saldo saat ini: Rp '.number_format($saldo_sebelum, 0, ',', '.'), array());
		}

		$saldo_setelah = ($jenis_mutasi === 'tarik') 
			? $saldo_sebelum - $nominal 
			: $saldo_sebelum + $nominal;

		$user = $this->ion_auth->user()->row();
		$user_id = $user ? $user->id : 1;
		$nomor_bukti = 'SIM-'.date('YmdHis').'-'.$member->id.'-'.mt_rand(100, 999);

		$this->db->insert('simpanan_mutasi', array(
			'nomor_bukti' => $nomor_bukti,
			'karyawan_id' => $member->id,
			'jenis_simpanan' => $jenis_simpanan,
			'jenis_mutasi' => $jenis_mutasi,
			'nominal' => $nominal,
			'saldo_sebelum' => $saldo_sebelum,
			'saldo_setelah' => $saldo_setelah,
			'tgl_transaksi' => $tgl_transaksi.' '.date('H:i:s'),
			'keterangan' => $keterangan,
			'id_user' => $user_id
		));

		if (!$simpanan)
		{
			$this->db->insert('simpanan', array(
				'karyawan_id' => $member->id,
				'jenis_simpanan' => $jenis_simpanan,
				'saldo' => $saldo_setelah,
				'updated_at' => date('Y-m-d H:i:s')
			));
		}
		else
		{
			$this->db->where('id', $simpanan->id)->update('simpanan', array(
				'saldo' => $saldo_setelah,
				'updated_at' => date('Y-m-d H:i:s')
			));
		}

		// POSTING JURNAL OTOMATIS
		$journal_posted = FALSE;
		$journal_error = '';
		if ($kas_account_code !== '')
		{
			$simpanan_account_code = $this->_get_simpanan_account_code($jenis_simpanan);
			$lines = array();

			if ($jenis_mutasi === 'setor')
			{
				$lines[] = array('account' => $kas_account_code, 'debit' => $nominal, 'credit' => 0, 'memo' => 'Penerimaan Simpanan '.ucfirst($jenis_simpanan).' - '.$member->nama.' ('.$member->npk.')');
				$lines[] = array('account' => $simpanan_account_code, 'debit' => 0, 'credit' => $nominal, 'memo' => 'Setoran Simpanan '.ucfirst($jenis_simpanan).' - '.$member->nama);
			}
			elseif ($jenis_mutasi === 'tarik')
			{
				$lines[] = array('account' => $simpanan_account_code, 'debit' => $nominal, 'credit' => 0, 'memo' => 'Penarikan Simpanan '.ucfirst($jenis_simpanan).' - '.$member->nama.' (Resign)');
				$lines[] = array('account' => $kas_account_code, 'debit' => 0, 'credit' => $nominal, 'memo' => 'Pengeluaran Kas Penarikan Simpanan - '.$member->nama);
			}
			elseif ($jenis_mutasi === 'bunga' || $jenis_mutasi === 'shu')
			{
				$lines[] = array('account' => '3104', 'debit' => $nominal, 'credit' => 0, 'memo' => strtoupper($jenis_mutasi).' Simpanan '.ucfirst($jenis_simpanan).' - '.$member->nama);
				$lines[] = array('account' => $simpanan_account_code, 'debit' => 0, 'credit' => $nominal, 'memo' => 'Alokasi '.strtoupper($jenis_mutasi).' ke Simpanan '.ucfirst($jenis_simpanan));
			}

			if (count($lines) >= 2)
			{
				$desc = 'Transaksi Simpanan '.ucfirst($jenis_simpanan).' ('.strtoupper($jenis_mutasi).') - '.$member->nama.' ['.$nomor_bukti.']';
				$post_ok = $this->accounting->post_journal('SIMPANAN', $nomor_bukti, $tgl_transaksi, $desc, (string) $user_id, $lines);
				if ($post_ok)
				{
					$journal_posted = TRUE;
				}
				else
				{
					$journal_error = $this->accounting->error();
				}
			}
		}

		$this->db->trans_complete();

		if ($this->db->trans_status() === FALSE)
		{
			json_response(FALSE, 'Gagal menyimpan transaksi ke database', array());
		}

		$msg = 'Transaksi simpanan berhasil disimpan!';
		if ($journal_posted)
		{
			$msg .= ' dan jurnal akuntansi otomatis tercatat.';
		}
		elseif ($journal_error !== '')
		{
			$msg .= ' Namun jurnal gagal dicatat: '.$journal_error;
		}

		json_response(TRUE, $msg, array(
			'nomor_bukti' => $nomor_bukti,
			'journal_posted' => $journal_posted
		));
	}

	// 4. PREVIEW SEBELUM IMPORT (CSV & EXCEL XLSX/XLS)
	public function preview_upload()
	{
		if ($this->input->server('REQUEST_METHOD') !== 'POST')
		{
			json_response(FALSE, 'Metode request tidak valid', array());
		}

		if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
		{
			json_response(FALSE, 'File gagal diunggah atau tidak ditemukan', array());
		}

		$file = $_FILES['file'];
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		$rows = array();
		if ($ext === 'csv')
		{
			$handle = fopen($file['tmp_name'], 'r');
			if (!$handle)
			{
				json_response(FALSE, 'Gagal membaca file CSV', array());
			}
			$first_line = fgets($handle);
			rewind($handle);
			$delimiter = (substr_count($first_line, ';') >= substr_count($first_line, ',')) ? ';' : ',';

			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
			{
				$rows[] = $row;
			}
			fclose($handle);
		}
		elseif ($ext === 'xlsx' || $ext === 'xls')
		{
			try {
				require_once APPPATH . 'third_party/PHPExcel/IOFactory.php';
				$objPHPExcel = PHPExcel_IOFactory::load($file['tmp_name']);
				$sheet = $objPHPExcel->getActiveSheet();
				$rows = $sheet->toArray(null, true, true, false);
			} catch (Exception $e) {
				json_response(FALSE, 'Gagal membaca file Excel: ' . $e->getMessage(), array());
			}
		}
		else
		{
			json_response(FALSE, 'Format file tidak didukung. Harap gunakan .xlsx, .xls, atau .csv', array());
		}

		if (count($rows) < 2)
		{
			json_response(FALSE, 'File kosong atau hanya memiliki baris header', array());
		}

		// Validasi baris preview
		$preview_rows = array();
		$total_nominal = 0;
		$valid_count = 0;
		$error_count = 0;
		$all_valid_data = array();

		for ($i = 1; $i < count($rows); $i++)
		{
			$r = $rows[$i];
			if (empty($r[0])) continue;

			$npk = trim((string) $r[0]);
			$nama = trim(isset($r[1]) ? (string) $r[1] : '');
			$jenis = strtolower(trim(isset($r[2]) ? (string) $r[2] : ''));
			$mutasi = strtolower(trim(isset($r[3]) ? (string) $r[3] : ''));
			$nominal = $this->_parse_amount(isset($r[4]) ? $r[4] : 0);
			$keterangan = trim(isset($r[5]) ? (string) $r[5] : '');

			$err = array();
			if (!$npk || !$jenis || !$mutasi || $nominal <= 0)
			{
				$err[] = 'Kolom tidak lengkap atau nominal 0';
			}
			if (!in_array($jenis, array('pokok', 'wajib', 'sukarela')))
			{
				$err[] = 'Jenis simpanan tidak valid';
			}
			if (!in_array($mutasi, array('setor', 'tarik', 'bunga', 'shu', 'koreksi')))
			{
				$err[] = 'Jenis mutasi tidak valid';
			}

			// Cek member
			$member = $this->db->where('npk', $npk)->get('karyawan')->row();
			if (!$member)
			{
				$err[] = 'NPK tidak ditemukan di database';
			}
			else
			{
				$nama = $member->nama;
				if ($mutasi === 'tarik' && (int) $member->is_active === 1)
				{
					$err[] = 'Ditolak: Anggota masih AKTIF (belum resign)';
				}
			}

			$status_valid = (count($err) === 0);
			if ($status_valid)
			{
				$valid_count++;
				$total_nominal += $nominal;
			}
			else
			{
				$error_count++;
			}

			$row_item = array(
				'row_num' => $i + 1,
				'npk' => $npk,
				'nama' => $nama,
				'jenis' => $jenis,
				'mutasi' => $mutasi,
				'nominal' => $nominal,
				'keterangan' => $keterangan,
				'valid' => $status_valid,
				'errors' => $err
			);

			if (count($preview_rows) < 15)
			{
				$preview_rows[] = $row_item;
			}
			$all_valid_data[] = $row_item;
		}

		// Simpan payload sementara ke session untuk diproses saat user klik "Konfirmasi & Simpan"
		$upload_token = md5(uniqid(mt_rand(), TRUE));
		$this->session->set_userdata('simpanan_upload_' . $upload_token, $all_valid_data);

		json_response(TRUE, 'Preview berhasil disiapkan', array(
			'token' => $upload_token,
			'total_rows' => count($all_valid_data),
			'valid_count' => $valid_count,
			'error_count' => $error_count,
			'total_nominal' => $total_nominal,
			'preview' => $preview_rows
		));
	}

	public function execute_upload()
	{
		if ($this->input->server('REQUEST_METHOD') !== 'POST')
		{
			json_response(FALSE, 'Invalid request', array());
		}

		$token = $this->input->post('token', TRUE);
		$kas_account_code = trim((string) $this->input->post('kas_account_code', TRUE));

		$data = $this->session->userdata('simpanan_upload_' . $token);
		if (!$data || !is_array($data))
		{
			json_response(FALSE, 'Sesi preview telah kedaluwarsa. Silakan upload ulang file Anda.', array());
		}

		$this->db->trans_start();
		$processed = 0;
		$user = $this->ion_auth->user()->row();
		$user_id = $user ? $user->id : 1;
		$kas_default = ($kas_account_code !== '') ? $kas_account_code : '1106';

		foreach ($data as $item)
		{
			if (!$item['valid']) continue;

			$npk = $item['npk'];
			$jenis = $item['jenis'];
			$mutasi = $item['mutasi'];
			$nominal = (float) $item['nominal'];
			$keterangan = $item['keterangan'];

			$member = $this->db->where('npk', $npk)->get('karyawan')->row();
			if (!$member) continue;

			// Double protection status resign
			if ($mutasi === 'tarik' && (int) $member->is_active === 1) continue;

			$simpanan = $this->db
				->where('karyawan_id', $member->id)
				->where('jenis_simpanan', $jenis)
				->get('simpanan')
				->row();

			$saldo_sebelum = $simpanan ? (float) $simpanan->saldo : 0;
			if ($mutasi === 'tarik' && $saldo_sebelum < $nominal) continue;

			$saldo_setelah = ($mutasi === 'tarik') 
				? $saldo_sebelum - $nominal 
				: $saldo_sebelum + $nominal;

			$nomor_bukti = 'SIM-'.date('YmdHis').'-'.$member->id.'-'.$processed;

			$this->db->insert('simpanan_mutasi', array(
				'nomor_bukti' => $nomor_bukti,
				'karyawan_id' => $member->id,
				'jenis_simpanan' => $jenis,
				'jenis_mutasi' => $mutasi,
				'nominal' => $nominal,
				'saldo_sebelum' => $saldo_sebelum,
				'saldo_setelah' => $saldo_setelah,
				'tgl_transaksi' => date('Y-m-d H:i:s'),
				'keterangan' => $keterangan,
				'id_user' => $user_id
			));

			if (!$simpanan)
			{
				$this->db->insert('simpanan', array(
					'karyawan_id' => $member->id,
					'jenis_simpanan' => $jenis,
					'saldo' => $saldo_setelah,
					'updated_at' => date('Y-m-d H:i:s')
				));
			} else {
				$this->db->where('id', $simpanan->id)->update('simpanan', array(
					'saldo' => $saldo_setelah,
					'updated_at' => date('Y-m-d H:i:s')
				));
			}

			// Jurnal Akuntansi Otomatis
			$simpanan_account_code = $this->_get_simpanan_account_code($jenis);
			$lines = array();

			if ($mutasi === 'setor')
			{
				$lines[] = array('account' => $kas_default, 'debit' => $nominal, 'credit' => 0, 'memo' => 'Setoran Simpanan '.$jenis.' - '.$member->nama);
				$lines[] = array('account' => $simpanan_account_code, 'debit' => 0, 'credit' => $nominal, 'memo' => 'Setoran Simpanan '.$jenis);
			}
			elseif ($mutasi === 'tarik')
			{
				$lines[] = array('account' => $simpanan_account_code, 'debit' => $nominal, 'credit' => 0, 'memo' => 'Penarikan Simpanan '.$jenis.' - '.$member->nama);
				$lines[] = array('account' => $kas_default, 'debit' => 0, 'credit' => $nominal, 'memo' => 'Pengeluaran Kas Penarikan Simpanan');
			}

			if (count($lines) >= 2)
			{
				$this->accounting->post_journal('SIMPANAN', $nomor_bukti, date('Y-m-d'), 'Import Mutasi '.$jenis.' - '.$member->nama, (string) $user_id, $lines);
			}

			$processed++;
		}

		$this->db->trans_complete();
		$this->session->unset_userdata('simpanan_upload_' . $token);

		if ($this->db->trans_status() === FALSE)
		{
			json_response(FALSE, 'Terjadi kesalahan database saat commit transaksi', array());
		}

		json_response(TRUE, "Berhasil memproses dan menjurnal $processed baris transaksi simpanan!", array(
			'processed' => $processed
		));
	}

	// 5. GENERATE TAGIHAN SIMPANAN WAJIB BULANAN
	public function generate_tagihan_wajib()
	{
		if ($this->input->server('REQUEST_METHOD') !== 'POST')
		{
			json_response(FALSE, 'Invalid request', array());
		}

		$periode = trim((string) $this->input->post('periode', TRUE));
		$raw_nominal = $this->input->post('nominal', TRUE);
		$kas_account_code = trim((string) $this->input->post('kas_account_code', TRUE));
		$nominal = $this->_parse_amount($raw_nominal);

		if (!$periode || !preg_match('/^\d{4}-\d{2}$/', $periode))
		{
			json_response(FALSE, 'Format periode tidak valid (harus YYYY-MM)', array());
		}

		if ($nominal <= 0)
		{
			$nominal = 50000; // Default nominal simpanan wajib
		}

		if ($kas_account_code === '')
		{
			$kas_account_code = '1103'; // Piutang Belanja Anggota (Potong Gaji) atau Kas Utama 1106
		}

		// Cek apakah tagihan periode ini sudah pernah di-generate
		$existing_batch = $this->db->get_where('simpanan_wajib_tagihan', array('periode' => $periode, 'status' => 'posted'))->row();
		if ($existing_batch)
		{
			json_response(FALSE, "Tagihan Simpanan Wajib periode $periode sudah pernah diposting sebelumnya.", array());
		}

		// Ambil seluruh karyawan aktif
		$karyawan_aktif = $this->db->where('is_active', 1)->get('karyawan')->result();
		if (empty($karyawan_aktif))
		{
			json_response(FALSE, 'Tidak ada anggota berstatus aktif.', array());
		}

		$bulan_start = $periode.'-01 00:00:00';
		$bulan_end = date('Y-m-t 23:59:59', strtotime($bulan_start));

		// Filter anggota yang belum bayar simpanan wajib bulan ini
		$already_paid = $this->db->query("
			SELECT DISTINCT karyawan_id 
			FROM simpanan_mutasi 
			WHERE jenis_simpanan = 'wajib' 
			  AND jenis_mutasi = 'setor'
			  AND tgl_transaksi >= ? AND tgl_transaksi <= ?
		", array($bulan_start, $bulan_end))->result();

		$paid_map = array();
		foreach ($already_paid as $ap)
		{
			$paid_map[$ap->karyawan_id] = TRUE;
		}

		$user = $this->ion_auth->user()->row();
		$user_id = $user ? $user->id : 1;
		$processed_count = 0;
		$total_nominal_batch = 0;

		$this->db->trans_start();

		foreach ($karyawan_aktif as $member)
		{
			if (isset($paid_map[$member->id]))
			{
				continue; // Sudah bayar, lewati
			}

			$simpanan = $this->db
				->where('karyawan_id', $member->id)
				->where('jenis_simpanan', 'wajib')
				->get('simpanan')
				->row();

			$saldo_sebelum = $simpanan ? (float) $simpanan->saldo : 0;
			$saldo_setelah = $saldo_sebelum + $nominal;
			$nomor_bukti = 'SIM-WJB-'.$periode.'-'.$member->id;

			$this->db->insert('simpanan_mutasi', array(
				'nomor_bukti' => $nomor_bukti,
				'karyawan_id' => $member->id,
				'jenis_simpanan' => 'wajib',
				'jenis_mutasi' => 'setor',
				'nominal' => $nominal,
				'saldo_sebelum' => $saldo_sebelum,
				'saldo_setelah' => $saldo_setelah,
				'tgl_transaksi' => $periode.'-01 08:00:00',
				'keterangan' => 'Potong Gaji Simpanan Wajib Periode ' . $periode,
				'id_user' => $user_id
			));

			if (!$simpanan)
			{
				$this->db->insert('simpanan', array(
					'karyawan_id' => $member->id,
					'jenis_simpanan' => 'wajib',
					'saldo' => $saldo_setelah,
					'updated_at' => date('Y-m-d H:i:s')
				));
			}
			else
			{
				$this->db->where('id', $simpanan->id)->update('simpanan', array(
					'saldo' => $saldo_setelah,
					'updated_at' => date('Y-m-d H:i:s')
				));
			}

			// Jurnal Akuntansi individual
			$lines = array(
				array('account' => $kas_account_code, 'debit' => $nominal, 'credit' => 0, 'memo' => 'Potong Gaji Simpanan Wajib '.$periode.' - '.$member->nama),
				array('account' => '3102', 'debit' => 0, 'credit' => $nominal, 'memo' => 'Simpanan Wajib Anggota '.$member->nama)
			);
			$this->accounting->post_journal('SIMPANAN', $nomor_bukti, $periode.'-01', 'Potong Gaji Simpanan Wajib '.$periode.' - '.$member->nama, (string) $user_id, $lines);

			$processed_count++;
			$total_nominal_batch += $nominal;
		}

		// Catat batch log
		$this->db->replace('simpanan_wajib_tagihan', array(
			'periode' => $periode,
			'nominal' => $nominal,
			'total_anggota' => $processed_count,
			'total_nominal' => $total_nominal_batch,
			'status' => 'posted',
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => (string) $user_id,
			'posted_at' => date('Y-m-d H:i:s')
		));

		$this->db->trans_complete();

		if ($this->db->trans_status() === FALSE)
		{
			json_response(FALSE, 'Gagal memproses batch simpanan wajib', array());
		}

		json_response(TRUE, "Berhasil men-generate & memposting simpanan wajib untuk $processed_count anggota (Total: Rp ".number_format($total_nominal_batch, 0, ',', '.').")", array(
			'processed' => $processed_count,
			'total' => $total_nominal_batch
		));
	}

	public function download_template()
	{
		$filename = 'template_simpanan_'.date('YmdHis').'.csv';
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'"');

		$output = fopen('php://output', 'w');
		fputcsv($output, array('NPK', 'Nama', 'Jenis Simpanan', 'Jenis Mutasi', 'Nominal', 'Keterangan'), ';');
		fputcsv($output, array('001', 'Andi Wijaya', 'pokok', 'setor', '100000', 'Setor simpanan pokok bulan ini'), ';');
		fputcsv($output, array('002', 'Budi Santoso', 'wajib', 'setor', '50000', 'Setor simpanan wajib'), ';');
		fputcsv($output, array('001', 'Andi Wijaya', 'sukarela', 'tarik', '25000', 'Tarik simpanan sukarela (Khusus karyawan resign)'), ';');
		fclose($output);
		exit;
	}

	private function _get_simpanan_account_code($jenis)
	{
		switch ($jenis)
		{
			case 'pokok':
				return '3101'; // Simpanan Pokok Anggota
			case 'wajib':
				return '3102'; // Simpanan Wajib Anggota
			case 'sukarela':
			default:
				return '2102'; // Simpanan Sukarela Anggota
		}
	}

	private function _parse_amount($value)
	{
		if (is_numeric($value)) {
			return round((float) $value, 2);
		}
		$clean = preg_replace('/[^\d,\.]/', '', (string) $value);
		if ($clean === '') {
			return 0.0;
		}
		if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
			$clean = str_replace('.', '', $clean);
			$clean = str_replace(',', '.', $clean);
		} elseif (strpos($clean, '.') !== false && strpos($clean, ',') === false) {
			if (preg_match('/\.\d{3}(\.|$)/', $clean)) {
				$clean = str_replace('.', '', $clean);
			}
		} elseif (strpos($clean, ',') !== false) {
			$clean = str_replace(',', '.', $clean);
		}
		return round((float) $clean, 2);
	}
}

if (!function_exists('json_response'))
{
	function json_response($success, $message, $data)
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
