<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Schema_migrator
{
	private $CI;
	private $files;
	private $lock_name;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->database();
		$this->files = $this->discover(APPPATH.'schema_migrations');
		$this->lock_name = 'simpleton_schema_'.substr(hash('sha256', $this->CI->db->database), 0, 32);
	}

	public function status()
	{
		$this->files = $this->discover(APPPATH.'schema_migrations');
		$history = array();
		$table = $this->query("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'")->row();
		if ($table && (int) $table->count > 0) {
			foreach ($this->query('SELECT * FROM schema_migrations')->result_array() as $row) {
				$history[$row['migration_id']] = $row;
			}
		}
		$rows = array();
		foreach ($this->files as $id => $file) {
			$sql = $this->read_sql($file);
			$row = isset($history[$id]) ? $history[$id] : array(
				'status' => 'pending', 'checksum' => NULL, 'completed_at' => NULL,
				'error_message' => NULL, 'executed_by' => NULL
			);
			$row['migration_id'] = $id;
			$row['filename'] = $file;
			$row['changed'] = $row['checksum'] !== NULL && $row['checksum'] !== hash('sha256', $sql);
			$rows[] = $row;
		}
		foreach ($history as $id => $row) {
			if (!isset($this->files[$id])) {
				throw new RuntimeException('Database memiliki migrasi '.$id.' yang tidak ada pada versi aplikasi ini. Jangan downgrade aplikasi.');
			}
		}
		$pending_seen = FALSE;
		foreach ($rows as $row) {
			if ($row['status'] !== 'applied') {
				$pending_seen = TRUE;
			} elseif ($pending_seen) {
				throw new RuntimeException('Migrasi baru harus ditambahkan setelah nomor terakhir yang sudah diterapkan.');
			}
		}
		return $rows;
	}

	public function discover($directory)
	{
		if (!is_dir($directory) || !is_readable($directory)) {
			throw new RuntimeException('Folder migrasi tidak tersedia atau tidak dapat dibaca.');
		}
		$entries = scandir($directory);
		if ($entries === FALSE) {
			throw new RuntimeException('Folder migrasi gagal dibaca.');
		}
		$files = array();
		$numbers = array();
		foreach ($entries as $file) {
			if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'sql') {
				continue;
			}
			if (!preg_match('/^([0-9]{3})_[a-z][a-z0-9_]*\.sql$/D', $file, $match) ||
				strlen($file) > 68 || $match[1] === '000') {
				throw new RuntimeException('Nama migrasi tidak valid: '.$file.'. Gunakan 001_nama_perubahan.sql (maksimal 64 karakter ID).');
			}
			$path = $directory.DIRECTORY_SEPARATOR.$file;
			if (!is_file($path) || is_link($path) || !is_readable($path)) {
				throw new RuntimeException('File migrasi tidak tersedia atau tidak dapat dibaca: '.$file);
			}
			if (isset($numbers[$match[1]])) {
				throw new RuntimeException('Nomor migrasi duplikat: '.$match[1]);
			}
			$numbers[$match[1]] = TRUE;
			$files[substr($file, 0, -4)] = $file;
		}
		if (empty($files)) {
			throw new RuntimeException('Folder migrasi tidak memiliki file SQL.');
		}
		ksort($files, SORT_STRING);
		return $files;
	}

	public function run_next($user_id)
	{
		$db = $this->CI->db;
		$debug = $db->db_debug;
		$db->db_debug = FALSE;
		$locked = FALSE;
		$current = NULL;
		try {
			if ($db->dbdriver !== 'mysqli') {
				throw new RuntimeException('Migrasi web hanya mendukung MySQL/MariaDB dengan driver mysqli.');
			}
			$lock = $this->query('SELECT GET_LOCK(?, 0) AS acquired', array($this->lock_name))->row();
			if (!$lock || (string) $lock->acquired !== '1') {
				throw new RuntimeException('Migrasi sedang berjalan pada request lain. Tunggu dan muat ulang halaman.');
			}
			$locked = TRUE;
			$mode = $this->query('SELECT @@SESSION.sql_mode AS mode')->row();
			if (!$mode || preg_match('/(?:^|,)(?:ANSI_QUOTES|NO_BACKSLASH_ESCAPES)(?:,|$)/', $mode->mode)) {
				throw new RuntimeException('SQL mode ANSI_QUOTES/NO_BACKSLASH_ESCAPES tidak didukung oleh skrip migrasi ini.');
			}
			foreach (array('users', 'groups', 'users_groups', 'stok', 'karyawan', 'penjualan') as $table) {
				if (!$db->table_exists($table)) {
					throw new RuntimeException('Database dasar belum tersedia: '.$table.'. Pasang database dasar Simpleton dan akun admin dahulu.');
				}
			}
			$this->query("CREATE TABLE IF NOT EXISTS schema_migrations (
				migration_id varchar(64) NOT NULL PRIMARY KEY,
				checksum char(64) NOT NULL,
				status varchar(16) NOT NULL,
				executed_by varchar(32) NOT NULL,
				started_at datetime NOT NULL,
				completed_at datetime DEFAULT NULL,
				error_message text
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$rows = $this->status();
			foreach ($rows as $row) {
				if ($row['changed']) {
					throw new RuntimeException('Isi SQL '.$row['filename'].' berubah setelah dicatat. Pulihkan file asli; perubahan baru harus menjadi migrasi baru.');
				}
			}
			foreach ($rows as $row) {
				if ($row['status'] !== 'applied') {
					$current = $row['migration_id'];
					$sql = $this->read_sql($row['filename']);
					$this->query("INSERT INTO schema_migrations
						(migration_id, checksum, status, executed_by, started_at)
						VALUES (?, ?, 'running', ?, NOW())
						ON DUPLICATE KEY UPDATE status = 'running', executed_by = VALUES(executed_by),
							started_at = NOW(), completed_at = NULL, error_message = NULL",
						array($current, hash('sha256', $sql), (string) $user_id));
					$statements = $this->split_sql($sql);
					foreach ($statements as $index => $statement) {
						try {
							$this->query($statement);
						} catch (RuntimeException $exception) {
							throw new RuntimeException($row['filename'].' pernyataan '.($index + 1).': '.$exception->getMessage());
						}
					}
					$this->query("UPDATE schema_migrations SET status = 'applied', completed_at = NOW()
						WHERE migration_id = ?", array($current));
					return $row['filename'].' berhasil diterapkan.';
				}
			}
			return 'Semua migrasi sudah diterapkan.';
		} catch (RuntimeException $exception) {
			if ($current !== NULL) {
				$saved = $db->query("UPDATE schema_migrations SET status = 'failed', error_message = ?
					WHERE migration_id = ?", array($exception->getMessage(), $current));
				if ($saved === FALSE) {
					log_message('error', 'Status gagal migrasi tidak dapat disimpan: '.$current);
				}
			}
			throw $exception;
		} finally {
			if ($locked) {
				if ($db->query('SELECT RELEASE_LOCK(?)', array($this->lock_name)) === FALSE) {
					log_message('error', 'Lock migrasi tidak dapat dilepas.');
				}
			}
			$db->db_debug = $debug;
		}
	}

	private function query($sql, $params = array())
	{
		$db = $this->CI->db;
		$debug = $db->db_debug;
		$db->db_debug = FALSE;
		try {
			$result = $db->query($sql, $params);
			if ($result === FALSE) {
				$error = $db->error();
				throw new RuntimeException('Database error '.$error['code'].': '.$error['message']);
			}
			return $result;
		} finally {
			$db->db_debug = $debug;
		}
	}

	private function read_sql($file)
	{
		$path = APPPATH.'schema_migrations'.DIRECTORY_SEPARATOR.$file;
		if (!is_readable($path)) {
			throw new RuntimeException('File migrasi tidak dapat dibaca: '.$file);
		}
		$sql = file_get_contents($path);
		if ($sql === FALSE || trim($sql) === '') {
			throw new RuntimeException('File migrasi kosong atau gagal dibaca: '.$file);
		}
		// Keep checksums stable when Git converts Windows and Unix line endings.
		return str_replace(array("\r\n", "\r"), "\n", $sql);
	}

	public function split_sql($sql)
	{
		$statements = array();
		$buffer = '';
		$quote = NULL;
		$comment = NULL;
		$length = strlen($sql);
		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];
			$next = $i + 1 < $length ? $sql[$i + 1] : '';
			if ($comment === 'line') {
				if ($char === "\n") {
					$comment = NULL;
					$buffer .= "\n";
				}
				continue;
			}
			if ($comment === 'block') {
				if ($char === '*' && $next === '/') {
					$comment = NULL;
					$buffer .= ' ';
					$i++;
				}
				continue;
			}
			if ($quote !== NULL) {
				$buffer .= $char;
				if ($char === '\\' && $next !== '') {
					$buffer .= $next;
					$i++;
				} elseif ($char === $quote) {
					if ($next === $quote) {
						$buffer .= $next;
						$i++;
					} else {
						$quote = NULL;
					}
				}
				continue;
			}
			if ($char === "'" || $char === '"' || $char === '`') {
				$quote = $char;
				$buffer .= $char;
			} elseif ($char === '#' || ($char === '-' && $next === '-' &&
				($i + 2 === $length || ctype_space($sql[$i + 2])))) {
				$comment = 'line';
				$buffer .= ' ';
			} elseif ($char === '/' && $next === '*') {
				if ($i + 2 < $length && $sql[$i + 2] === '!') {
					throw new RuntimeException('Komentar SQL executable tidak didukung oleh runner.');
				}
				$comment = 'block';
				$i++;
			} elseif ($char === ';') {
				if (trim($buffer) !== '') {
					$statements[] = trim($buffer);
				}
				$buffer = '';
			} else {
				$buffer .= $char;
			}
		}
		if ($quote !== NULL || $comment === 'block') {
			throw new RuntimeException('SQL memiliki string atau komentar yang belum ditutup.');
		}
		if (trim($buffer) !== '') {
			$statements[] = trim($buffer);
		}
		if (empty($statements)) {
			throw new RuntimeException('SQL tidak memiliki pernyataan untuk dijalankan.');
		}
		return $statements;
	}
}
