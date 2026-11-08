<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Data_update extends CI_Controller
{
	public function index()
	{
		$this->_require_update_access();
		$this->load->helper('url');
		$data['js_file'] = array(base_url('assets/kasir/update.js?v=20261108-safe-import-progress'));
		$data['upload_url'] = site_url('main/data_update/upload');
		$data['sample_stock_url'] = base_url('assets/misc/samplestok.png');
		$data['sample_employee_url'] = base_url('assets/misc/samplekaryawan.png');
		$data['m_update'] = TRUE;
		$data['output'] = $this->load->view('data_update', $data, TRUE);
		$this->load->view('welcome_message', $data);
	}

	public function upload($type = '')
	{
		$this->_require_update_access();
		if ($this->input->method(TRUE) !== 'POST') {
			show_error('Metode permintaan tidak valid.', 405);
		}
		if (!in_array($type, array('stok', 'karyawan'), TRUE)) {
			show_error('Jenis data tidak valid.', 404);
		}

		$file = isset($_FILES['file']) ? $_FILES['file'] : NULL;
		if (!$file || !isset($file['name'], $file['error'], $file['size']) ||
			$file['error'] !== UPLOAD_ERR_OK) {
			$this->_json_response(400, FALSE, 'File tidak berhasil diunggah. Silakan pilih ulang file.');
			return;
		}
		if ((int) $file['size'] < 1 || (int) $file['size'] > 104857600) {
			$this->_json_response(400, FALSE, 'Ukuran file harus lebih dari 0 dan maksimal 100 MB.');
			return;
		}

		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($extension, array('xls', 'xlsx', 'csv', 'txt'), TRUE)) {
			$this->_json_response(400, FALSE, 'Format file harus XLS, XLSX, CSV, atau TXT.');
			return;
		}

		$this->load->library('upload');
		$this->upload->initialize(array(
			'upload_path' => FCPATH.'assets/uploads/',
			'allowed_types' => 'xls|xlsx|csv|txt',
			'max_size' => 102400,
			'encrypt_name' => TRUE,
			'remove_spaces' => TRUE
		));
		if (!$this->upload->do_upload('file')) {
			$this->_json_response(400, FALSE, trim(strip_tags($this->upload->display_errors())));
			return;
		}

		$uploaded = $this->upload->data();
		$file_path = $uploaded['full_path'];
		try {
			$this->load->library('excel');
			try {
				$sheet = PHPExcel_IOFactory::load($file_path)->getActiveSheet();
			} catch (Exception $exception) {
				$this->_json_response(422, FALSE, 'File tidak dapat dibaca. Pastikan isi file sesuai dengan gambar contoh.');
				return;
			}

			$parsed = $this->_parse_import_sheet($sheet, $type);
			if (!empty($parsed['errors'])) {
				$this->_json_response(422, FALSE, 'Data belum diperbarui karena ada kesalahan pada file.', array(
					'errors' => $parsed['errors']
				));
				return;
			}

			if ($type === 'karyawan' &&
				(!$this->db->field_exists('is_active', 'karyawan') ||
					!$this->db->field_exists('resign_date', 'karyawan'))) {
				$this->_json_response(500, FALSE,
					'Kolom status karyawan belum tersedia. Jalankan application/sql/karyawan_employment_status.sql terlebih dahulu.');
				return;
			}

			$result = $this->_save_import_rows($type, $parsed['rows']);
			$this->_json_response(200, TRUE, 'Data berhasil diperbarui.', $result);
		} catch (Exception $exception) {
			log_message('error', 'Data update import failed: '.$exception->getMessage());
			$this->_json_response(500, FALSE, 'Proses update gagal. Data sebelumnya tetap dipertahankan.');
		} finally {
			if (is_file($file_path)) {
				unlink($file_path);
			}
		}
	}

	private function _require_update_access()
	{
		$this->load->library(array('ion_auth', 'page_access'));
		if (!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		if (!$this->page_access->can_access('update_data')) {
			show_error('Anda tidak memiliki akses ke Update Data.', 403);
		}
		$allowed_groups = array('admin', 'management', 'manager', 'pengurus');
		if (!$this->ion_auth->in_group($allowed_groups)) {
			show_error('Hanya admin, management, manager, atau pengurus yang dapat memperbarui data.', 403);
		}
	}

	private function _parse_import_sheet($sheet, $type)
	{
		$headers = $type === 'stok'
			? array('barcode', 'nama', 'jumlah', 'harga', 'distributor', 'ppn')
			: array('npk', 'nama', 'bagian', 'limit belanja', 'join date');
		foreach ($headers as $column => $expected) {
			$actual = strtolower(trim((string) $sheet->getCellByColumnAndRow($column, 1)->getFormattedValue()));
			if ($actual !== $expected) {
				return array('rows' => array(), 'errors' => array(array(
					'baris' => 1,
					'pesan' => 'Header file tidak sesuai format contoh.'
				)));
			}
		}

		$last_row = (int) $sheet->getHighestDataRow();
		if ($last_row > 20001) {
			return array('rows' => array(), 'errors' => array(array(
				'baris' => '',
				'pesan' => 'File berisi lebih dari 20.000 baris data.'
			)));
		}

		$rows = array();
		$errors = array();
		$seen_ids = array();
		for ($row_number = 2; $row_number <= $last_row; $row_number++) {
			$values = array();
			foreach ($headers as $column => $header) {
				$cell = $sheet->getCellByColumnAndRow($column, $row_number);
				$values[] = $cell->getCalculatedValue();
			}
			if (count(array_filter($values, function ($value) {
				return $value !== NULL && trim((string) $value) !== '';
			})) === 0) {
				continue;
			}

			$id = trim((string) $sheet->getCellByColumnAndRow(0, $row_number)->getFormattedValue());
			$name = trim((string) $values[1]);
			$section = trim((string) $values[2]);
			$row_error = '';
			if ($id === '' || $this->_import_text_length($id) > ($type === 'stok' ? 64 : 10)) {
				$row_error = 'Kode/barcode atau NPK wajib diisi dan panjangnya tidak boleh berlebih.';
			} elseif ($name === '' || $this->_import_text_length($name) > ($type === 'stok' ? 64 : 128)) {
				$row_error = 'Nama wajib diisi dan panjangnya tidak boleh berlebih.';
			} elseif (isset($seen_ids[$id])) {
				$row_error = 'Kode/barcode atau NPK duplikat di dalam file.';
			}

			if ($type === 'stok') {
				$quantity = $this->_import_number($values[2]);
				$price = $this->_import_number($values[3]);
				$distributor = trim((string) $values[4]);
				$tax = $values[5] === NULL || trim((string) $values[5]) === ''
					? 0 : $this->_import_number($values[5]);
				if ($row_error === '' && (!$this->_is_nonnegative_integer($quantity) ||
					!$this->_is_nonnegative_integer($price) || $this->_import_text_length($distributor) > 64 ||
					$tax === FALSE || $tax < 0 || $tax > 1)) {
					$row_error = 'Jumlah dan harga harus bilangan bulat nonnegatif; panjang distributor maksimal 64 karakter; PPN harus antara 0 dan 1.';
				}
				if ($row_error === '') {
					$rows[] = array(
						'barcode' => $id,
						'nama' => $name,
						'jumlah' => (int) $quantity,
						'harga' => (int) $price,
						'distributor' => $distributor,
						'ppn' => (float) $tax
					);
				}
			} else {
				$limit = $this->_import_number($values[3]);
				$join_date = $this->_parse_employee_join_date($values[4]);
				if ($row_error === '' && ($section === '' || $this->_import_text_length($section) > 128 ||
					!$this->_is_nonnegative_integer($limit) || $join_date === FALSE)) {
					$row_error = 'Bagian wajib diisi, limit harus bilangan bulat nonnegatif, dan tanggal masuk harus valid.';
				}
				if ($row_error === '') {
					$rows[] = array(
						'npk' => $id,
						'nama' => $name,
						'bagian' => $section,
						'limit_belanja' => (int) $limit,
						'join_date' => $join_date,
						'is_active' => 1,
						'resign_date' => NULL
					);
				}
			}

			if ($row_error !== '') {
				$errors[] = array('baris' => $row_number, 'pesan' => $row_error);
				if (count($errors) >= 20) {
					$errors[] = array('baris' => '', 'pesan' => 'Kesalahan lainnya tidak ditampilkan.');
					break;
				}
			} else {
				$seen_ids[$id] = TRUE;
			}
		}

		if (empty($errors) && empty($rows)) {
			$errors[] = array('baris' => '', 'pesan' => 'File tidak berisi data. Data lama tidak diubah.');
		}
		return array('rows' => $rows, 'errors' => $errors);
	}

	private function _import_number($value)
	{
		if (!is_numeric($value) || !is_finite((float) $value)) {
			return FALSE;
		}
		return (float) $value;
	}

	private function _is_nonnegative_integer($value)
	{
		return $value !== FALSE && $value >= 0 && $value <= 2147483647 && floor($value) === $value;
	}

	private function _import_text_length($value)
	{
		if (function_exists('mb_strlen')) {
			return mb_strlen($value, 'UTF-8');
		}
		$count = preg_match_all('/./us', $value, $matches);
		return $count === FALSE ? strlen($value) : $count;
	}

	private function _parse_employee_join_date($value)
	{
		if (is_numeric($value) && (float) $value > 0) {
			try {
				return PHPExcel_Shared_Date::ExcelToPHPObject($value)->format('Y-m-d');
			} catch (Exception $exception) {
				return FALSE;
			}
		}

		$value = trim((string) $value);
		foreach (array('!d/m/Y', '!Y-m-d') as $format) {
			$date = DateTime::createFromFormat($format, $value);
			$errors = DateTime::getLastErrors();
			if ($date && ($errors === FALSE || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
				return $date->format('Y-m-d');
			}
		}
		return FALSE;
	}

	private function _save_import_rows($type, $rows)
	{
		try {
			if (!$this->db->trans_begin()) {
				throw new RuntimeException('Database tidak dapat memulai transaksi.');
			}
			if ($type === 'karyawan') {
				date_default_timezone_set('Asia/Jakarta');
			}
			if ($type === 'stok') {
				$this->db->empty_table('stok');
				foreach (array_chunk($rows, 500) as $batch) {
					$this->db->insert_batch('stok', $batch);
				}
				$result = array('jumlah_data' => count($rows));
			} else {
				$npks = array();
				foreach ($rows as $row) {
					$npks[] = $row['npk'];
				}

				$existing = $this->db->select('npk')->get('karyawan')->result_array();
				$existing_npks = array();
				foreach ($existing as $employee) {
					$existing_npks[$employee['npk']] = TRUE;
				}

				$this->db->where('is_active', 1)->where_not_in('npk', $npks);
				$resigned_count = $this->db->count_all_results('karyawan');
				$this->db->where('is_active', 1)->where_not_in('npk', $npks)->update('karyawan', array(
					'is_active' => 0,
					'resign_date' => date('Y-m-d')
				));

				$added = 0;
				$updated = 0;
				foreach ($rows as $row) {
					$npk = $row['npk'];
					if (isset($existing_npks[$npk])) {
						$this->db->where('npk', $npk)->update('karyawan', $row);
						$updated++;
					} else {
						$this->db->insert('karyawan', $row);
						$added++;
					}
				}
				$result = array(
					'jumlah_data' => count($rows),
					'karyawan_baru' => $added,
					'karyawan_diperbarui' => $updated,
					'ditandai_resign' => (int) $resigned_count
				);
			}

			if ($this->db->trans_status() === FALSE) {
				throw new RuntimeException('Database menolak perubahan data.');
			}
			if ($this->db->trans_commit() === FALSE) {
				throw new RuntimeException('Database gagal menyelesaikan transaksi.');
			}
			return $result;
		} catch (Exception $exception) {
			$this->db->trans_rollback();
			throw $exception;
		}
	}

	private function _json_response($http_status, $success, $message, $data = array())
	{
		$this->output->set_status_header($http_status)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode(array(
				'success' => (bool) $success,
				'message' => $message,
				'data' => $data
			)));
	}
}
