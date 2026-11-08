<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Database_backup
{
	private $CI;
	private $settings;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->database();
		$this->CI->config->load('database_backup');
		$this->settings = $this->CI->config->item('database_backup');
	}

	private function directory()
	{
		$directory = $this->settings['directory'];
		if (!is_dir($directory) && !mkdir($directory, 0700, TRUE)) {
			throw new RuntimeException('Folder backup tidak dapat dibuat.');
		}
		$resolved = realpath($directory);
		$web = realpath(FCPATH);
		if (!$resolved || !$web || strcasecmp($resolved, $web) === 0 ||
			strncasecmp($resolved, $web.DIRECTORY_SEPARATOR, strlen($web) + 1) === 0) {
			throw new RuntimeException('Folder backup harus berada di luar folder web aplikasi.');
		}
		if (!is_writable($resolved)) {
			throw new RuntimeException('Folder backup tidak dapat ditulis.');
		}
		return $resolved;
	}

	public function records()
	{
		$directory = $this->directory();
		$files = glob($directory.DIRECTORY_SEPARATOR.'backup_*.json');
		if ($files === FALSE) {
			throw new RuntimeException('Daftar backup tidak dapat dibaca.');
		}
		$rows = array();
		foreach ($files as $file) {
			$row = json_decode(file_get_contents($file), TRUE);
			if (!is_array($row) || !isset($row['id'], $row['database'], $row['checksum'], $row['objects'], $row['created_at'], $row['bytes'])) {
				throw new RuntimeException('Metadata backup rusak: '.basename($file));
			}
			if ($row['database'] === $this->CI->db->database) {
				$rows[$row['id']] = $row;
			}
		}
		krsort($rows);
		return $rows;
	}

	public function backup($user_id)
	{
		return $this->exclusive(function () use ($user_id) {
			return $this->create($user_id);
		});
	}

	public function restore($id, $user_id)
	{
		return $this->exclusive(function () use ($id, $user_id) {
			$record = $this->record($id);
			$objects = $this->objects();
			if ($objects !== $record['objects']) {
				throw new RuntimeException('Daftar objek database berbeda dari backup. Restore web diblokir; gunakan pemulihan terencana untuk perubahan skema.');
			}
			$safety = $this->create($user_id);
			log_message('error', 'Restore database dimulai oleh admin '.$user_id.' dari '.$id.'; backup pengaman '.$safety['id']);
			$input = NULL;
			try {
				$input = $this->restore_input($record);
				$this->process($this->settings['restore_binary'], array('--binary-mode', '--default-character-set=utf8'),
					$input);
			} catch (RuntimeException $exception) {
				throw new RuntimeException('Restore gagal dan database mungkin sudah berubah sebagian. Backup pengaman: '.$safety['id'].'. '.$exception->getMessage());
			} finally {
				if ($input !== NULL && $input !== $this->path($id, 'sql') && is_file($input) && !unlink($input)) {
					log_message('error', 'SQL sementara restore tidak dapat dihapus.');
				}
			}
			log_message('info', 'Restore database selesai oleh admin '.$user_id.' dari '.$id);
			return $safety;
		});
	}

	public function record($id)
	{
		$this->path($id, 'sql');
		$rows = $this->records();
		if (!isset($rows[$id])) {
			throw new RuntimeException('Backup tidak ditemukan untuk database ini.');
		}
		$record = $rows[$id];
		$path = $this->archive_path($record);
		if (!is_file($path) || is_link($path) || !hash_equals($record['checksum'], hash_file('sha256', $path))) {
			throw new RuntimeException('File backup hilang atau checksum tidak sesuai.');
		}
		return $record;
	}

	public function archive_path($record)
	{
		$format = isset($record['format']) ? $record['format'] : 'sql';
		if (!in_array($format, array('sql', 'zip'), TRUE)) {
			throw new RuntimeException('Format backup tidak didukung.');
		}
		return $this->path($record['id'], $format);
	}

	private function restore_input($record)
	{
		if (!isset($record['format']) || $record['format'] === 'sql') {
			return $this->archive_path($record);
		}
		if (!class_exists('ZipArchive')) { throw new RuntimeException('Ekstensi PHP ZIP tidak tersedia.'); }
		$zip = new ZipArchive();
		if ($zip->open($this->archive_path($record)) !== TRUE) { throw new RuntimeException('ZIP backup gagal dibuka.'); }
		$temporary = NULL;
		$source = FALSE;
		$target = FALSE;
		try {
			$entry = $record['id'].'.sql';
			if ($zip->numFiles !== 1 || $zip->getNameIndex(0) !== $entry || !isset($record['sql_checksum'])) {
				throw new RuntimeException('Isi ZIP backup tidak valid.');
			}
			$temporary = tempnam($this->directory(), 'restore_');
			if ($temporary === FALSE) { throw new RuntimeException('SQL sementara restore gagal dibuat.'); }
			$source = $zip->getStream($entry);
			$target = fopen($temporary, 'wb');
			if ($source === FALSE || $target === FALSE || stream_copy_to_stream($source, $target) === FALSE) {
				throw new RuntimeException('Ekstraksi SQL backup gagal.');
			}
			fclose($target);
			$target = FALSE;
			if (!hash_equals($record['sql_checksum'], hash_file('sha256', $temporary))) {
				throw new RuntimeException('Checksum SQL dalam ZIP tidak sesuai.');
			}
			return $temporary;
		} catch (RuntimeException $exception) {
			if (is_resource($target)) { fclose($target); $target = FALSE; }
			if ($temporary !== NULL && is_file($temporary) && !unlink($temporary)) {
				log_message('error', 'Ekstraksi gagal tidak dapat dibersihkan.');
			}
			throw $exception;
		} finally {
			if (is_resource($source)) { fclose($source); }
			if (is_resource($target)) { fclose($target); }
			$zip->close();
		}
	}

	public function path($id, $extension = 'sql')
	{
		if (!is_string($id) || !preg_match('/^backup_[0-9]{8}_[0-9]{6}_[a-f0-9]{12}$/D', $id)) {
			throw new RuntimeException('ID backup tidak valid.');
		}
		return $this->directory().DIRECTORY_SEPARATOR.$id.'.'.$extension;
	}

	private function objects()
	{
		$query = $this->CI->db->query("SELECT TABLE_NAME AS object_name, TABLE_TYPE AS object_type
			FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?
			UNION ALL SELECT ROUTINE_NAME, ROUTINE_TYPE FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = ?
			UNION ALL SELECT EVENT_NAME, 'EVENT' FROM INFORMATION_SCHEMA.EVENTS WHERE EVENT_SCHEMA = ?
			UNION ALL SELECT TRIGGER_NAME, 'TRIGGER' FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = ?
			ORDER BY object_type, object_name",
			array($this->CI->db->database, $this->CI->db->database, $this->CI->db->database, $this->CI->db->database));
		if ($query === FALSE) { throw new RuntimeException('Daftar tabel database tidak dapat dibaca.'); }
		return $query->result_array();
	}

	private function create($user_id)
	{
		if (!class_exists('ZipArchive')) {
			throw new RuntimeException('Backup ZIP memerlukan ekstensi PHP ZIP.');
		}
		$id = 'backup_'.date('Ymd_His').'_'.substr(md5(uniqid('', TRUE)), 0, 12);
		$path = $this->path($id);
		$objects = $this->objects();
		try {
			$this->process($this->settings['dump_binary'], array(
				'--lock-all-tables', '--routines', '--events', '--triggers', '--hex-blob',
				'--default-character-set=utf8', '--result-file='.$path
			), NULL);
			if (!is_file($path) || filesize($path) === 0) {
				throw new RuntimeException('File hasil backup kosong.');
			}
			$sql_bytes = filesize($path);
			$sql_checksum = hash_file('sha256', $path);
			$archive = $this->path($id, 'zip');
			$zip = new ZipArchive();
			if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== TRUE) {
				throw new RuntimeException('ZIP backup tidak dapat dibuat.');
			}
			$added = $zip->addFile($path, $id.'.sql');
			$closed = $zip->close();
			if (!$added || !$closed || !is_file($archive) || filesize($archive) === 0) {
				throw new RuntimeException('Kompresi ZIP backup gagal.');
			}
			$record = array('id' => $id, 'database' => $this->CI->db->database, 'objects' => $objects,
				'format' => 'zip', 'sql_bytes' => $sql_bytes, 'sql_checksum' => $sql_checksum,
				'checksum' => hash_file('sha256', $archive), 'bytes' => filesize($archive),
				'created_at' => date('Y-m-d H:i:s'), 'created_by' => (string) $user_id);
			if (!unlink($path)) { throw new RuntimeException('SQL mentah backup gagal dihapus setelah kompresi.'); }
			if (file_put_contents($this->path($id, 'json'), json_encode($record), LOCK_EX) === FALSE) {
				throw new RuntimeException('Metadata backup gagal disimpan.');
			}
			log_message('info', 'Backup database dibuat oleh admin '.$user_id.': '.$id);
			return $record;
		} catch (RuntimeException $exception) {
			foreach (array($path, $this->path($id, 'zip')) as $failed_path) {
				if (is_file($failed_path) && !unlink($failed_path)) { log_message('error', 'Backup gagal tidak dapat dibersihkan: '.$id); }
			}
			throw $exception;
		}
	}

	private function exclusive($action)
	{
		$db = $this->CI->db;
		if ($db->dbdriver !== 'mysqli') {
			throw new RuntimeException('Backup web hanya mendukung MySQL/MariaDB dengan driver mysqli.');
		}
		$name = 'simpleton_schema_'.substr(hash('sha256', $db->database), 0, 32);
		$query = $db->query('SELECT GET_LOCK(?, 0) AS acquired', array($name));
		if ($query === FALSE || (string) $query->row()->acquired !== '1') {
			throw new RuntimeException('Backup, restore, atau migrasi sedang berjalan. Coba kembali nanti.');
		}
		try { return $action(); } finally {
			if ($db->query('SELECT RELEASE_LOCK(?)', array($name)) === FALSE) {
				log_message('error', 'Lock backup database gagal dilepas.');
			}
		}
	}

	private function process($binary, $arguments, $input)
	{
		if (!is_file($binary) || !function_exists('proc_open')) {
			throw new RuntimeException('Tool MySQL atau proc_open tidak tersedia. Periksa konfigurasi database_backup.');
		}
		$db = $this->CI->db;
		$temporary = tempnam($this->directory(), 'mysql_');
		if ($temporary === FALSE) { throw new RuntimeException('Konfigurasi sementara MySQL tidak dapat dibuat.'); }
		$error_path = $temporary.'.error';
		$output_path = $temporary.'.output';
		try {
			chmod($temporary, 0600);
			$escape = function ($value) {
				return '"'.str_replace(array('\\', '"', "\r", "\n"), array('\\\\', '\\"', '\\r', '\\n'), (string) $value).'"';
			};
			$options = "[client]\nhost=".$escape($db->hostname)."\nuser=".$escape($db->username).
				"\npassword=".$escape($db->password)."\n";
			if (!empty($db->port)) { $options .= 'port='.(int) $db->port."\n"; }
			if (file_put_contents($temporary, $options) === FALSE) { throw new RuntimeException('Konfigurasi MySQL gagal ditulis.'); }
			$command = escapeshellarg($binary).' '.escapeshellarg('--defaults-extra-file='.$temporary);
			foreach ($arguments as $argument) { $command .= ' '.escapeshellarg($argument); }
			$command .= ' '.escapeshellarg($db->database);
			$descriptors = array(
				0 => $input === NULL ? array('pipe', 'r') : array('file', $input, 'r'),
				1 => array('file', $output_path, 'w'), 2 => array('file', $error_path, 'w')
			);
			$process = proc_open($command, $descriptors, $pipes, NULL, NULL, array('bypass_shell' => TRUE));
			if (!is_resource($process)) { throw new RuntimeException('Proses MySQL gagal dimulai.'); }
			if (isset($pipes[0])) { fclose($pipes[0]); }
			$exit = proc_close($process);
			if ($exit !== 0) {
				log_message('error', 'Tool backup/restore MySQL gagal: '.file_get_contents($error_path));
				throw new RuntimeException('Tool MySQL gagal (exit '.$exit.'). Periksa log server.');
			}
		} finally {
			foreach (array($temporary, $error_path, $output_path) as $file) {
				if (is_file($file) && !unlink($file)) { log_message('error', 'File sementara backup tidak dapat dibersihkan.'); }
			}
		}
	}
}
