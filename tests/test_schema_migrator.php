<?php
require __DIR__.'/bootstrap.php';
if (!defined('APPPATH')) {
	define('APPPATH', dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR);
}
require APPPATH.'libraries'.DIRECTORY_SEPARATOR.'Schema_migrator.php';

$migrator = (new ReflectionClass('Schema_migrator'))->newInstanceWithoutConstructor();
$assert = function ($condition, $message) {
	if (!$condition) {
		throw new RuntimeException('FAIL '.$message);
	}
	echo 'PASS '.$message.PHP_EOL;
};
$expect_error = function ($action, $text) use ($assert) {
	try {
		$action();
	} catch (RuntimeException $exception) {
		$assert(strpos($exception->getMessage(), $text) !== FALSE, $text);
		return;
	}
	throw new RuntimeException('Expected error: '.$text);
};
$assert($migrator->split_sql("-- ignore ;\nSELECT 'a;b'; # end\nSELECT \"x;y\";") ===
	array("SELECT 'a;b'", 'SELECT "x;y"'), 'Semicolons inside strings and comments are not split.');
$assert($migrator->split_sql("SELECT 'it''s;ok'; /* ; */ SELECT `a;b`") ===
	array("SELECT 'it''s;ok'", 'SELECT `a;b`'), 'Doubled quotes, backticks and trailing SQL are preserved.');
$assert(count($migrator->split_sql("SELECT 'a\\';b'; SELECT 2;")) === 2, 'Escaped quotes are preserved.');
$expect_error(function () use ($migrator) { $migrator->split_sql("SELECT 'broken"); }, 'belum ditutup');
$expect_error(function () use ($migrator) { $migrator->split_sql('/*! SELECT 1 */'); }, 'executable');
$expect_error(function () use ($migrator) { $migrator->split_sql('-- comments only'); }, 'tidak memiliki');

$directory = APPPATH.'schema_migrations';
$files = $migrator->discover($directory);
$assert(array_search('001_purchasing.sql', array_values($files), TRUE) <
	array_search('002_foundation.sql', array_values($files), TRUE), 'Purchasing runs before foundation receipt extensions.');
foreach ($files as $file) {
	$assert(count($migrator->split_sql(file_get_contents($directory.DIRECTORY_SEPARATOR.$file))) > 0,
		'Parses '.$file);
}
$assert(!in_array('telegram_pin.sql', $files, TRUE) &&
	!in_array('demo_petty_cash_agustus_2026.sql', $files, TRUE) &&
	!in_array('import_laporan_agustus_2026.sql', $files, TRUE), 'Demo, transaction imports and PIN defaults are excluded.');

$temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'simpleton_discovery_'.uniqid();
mkdir($temporary);
try {
	file_put_contents($temporary.DIRECTORY_SEPARATOR.'023_new_change.sql', 'SELECT 1;');
	file_put_contents($temporary.DIRECTORY_SEPARATOR.'001_first.sql', 'SELECT 1;');
	file_put_contents($temporary.DIRECTORY_SEPARATOR.'notes.txt', 'Not a migration');
	$assert(array_keys($migrator->discover($temporary)) === array('001_first', '023_new_change'),
		'New files are automatically discovered and sorted, with non-SQL files ignored.');
	file_put_contents($temporary.DIRECTORY_SEPARATOR.'023_duplicate.sql', 'SELECT 1;');
	$expect_error(function () use ($migrator, $temporary) { $migrator->discover($temporary); }, 'duplikat');
	unlink($temporary.DIRECTORY_SEPARATOR.'023_duplicate.sql');
	file_put_contents($temporary.DIRECTORY_SEPARATOR.'wrong.sql', 'SELECT 1;');
	$expect_error(function () use ($migrator, $temporary) { $migrator->discover($temporary); }, 'tidak valid');
} finally {
	foreach (scandir($temporary) as $file) {
		if ($file !== '.' && $file !== '..') { unlink($temporary.DIRECTORY_SEPARATOR.$file); }
	}
	rmdir($temporary);
}

if (!in_array('--database', $argv, TRUE)) {
	echo 'Database integration tests require --database; they use an isolated temporary database.'.PHP_EOL;
	exit(0);
}

class Migration_test_load
{
	public function database() {}
}
function &get_instance() { return $GLOBALS['migration_test_ci']; }
if (!function_exists('log_message')) {
	function log_message($level, $message) { fwrite(STDERR, $level.': '.$message.PHP_EOL); }
}

require APPPATH.'config'.DIRECTORY_SEPARATOR.'database.php';
mysqli_report(MYSQLI_REPORT_OFF);
$settings = $db[$active_group];
$connection = new mysqli($settings['hostname'], $settings['username'], $settings['password'], '',
	isset($settings['port']) ? (int) $settings['port'] : 3306);
if ($connection->connect_errno) {
	throw new RuntimeException('Test database connection failed.');
}
$database = 'simpleton_migration_test_'.substr(md5(uniqid('', TRUE)), 0, 12);
if (!$connection->query('CREATE DATABASE `'.$database.'` CHARACTER SET utf8')) {
	throw new RuntimeException('Cannot create isolated test database.');
}
try {
	$connection->select_db($database);
	require_once BASEPATH.'database'.DIRECTORY_SEPARATOR.'DB.php';
	$test_settings = $settings;
	$test_settings['database'] = $database;
	$test_settings['pconnect'] = FALSE;
	$test_settings['db_debug'] = TRUE;
	$adapter = DB($test_settings, TRUE);
	$GLOBALS['migration_test_ci'] = (object) array(
		'db' => $adapter, 'load' => new Migration_test_load()
	);
	$fixtures = array(
		"CREATE TABLE groups (id mediumint unsigned PRIMARY KEY, name varchar(20), description varchar(100)) ENGINE=InnoDB",
		"CREATE TABLE users (id int PRIMARY KEY, username varchar(100), email varchar(100)) ENGINE=InnoDB",
		"CREATE TABLE users_groups (id int PRIMARY KEY, user_id int, group_id mediumint unsigned) ENGINE=InnoDB",
		"CREATE TABLE stok (id int PRIMARY KEY, barcode varchar(64) NOT NULL, nama varchar(64), jumlah int, harga int, distributor varchar(64), ppn float) ENGINE=InnoDB",
		"CREATE TABLE karyawan (id int PRIMARY KEY, npk varchar(10), nama varchar(128), bagian varchar(128), limit_belanja int, join_date date) ENGINE=InnoDB",
		"CREATE TABLE penjualan (id int PRIMARY KEY, nota varchar(64), id_user varchar(11), id_karyawan varchar(11), tgl datetime, kode varchar(128), harga int, qty int, jumlah int, ppn float) ENGINE=InnoDB",
		"INSERT INTO groups VALUES (1, 'admin', 'Test admin'), (2, 'members', 'Test members')",
		"INSERT INTO stok (id, barcode, jumlah) VALUES (1, 'TEST', 2), (2, 'TEST', 3)"
	);
	foreach ($fixtures as $sql) {
		$assert($adapter->query($sql) !== FALSE, 'Creates isolated fixture.');
	}
	$runner = new Schema_migrator();
	$expect_error(function () use ($runner) { $runner->run_next('test'); }, '001_purchasing.sql pernyataan');
	$assert($adapter->query("SELECT status FROM schema_migrations")->row()->status === 'failed', 'Failed SQL is persisted and not marked applied.');
	$assert($adapter->db_debug === TRUE, 'Database debug state is restored after failure.');
	$adapter->query("DELETE FROM stok WHERE id = 2");
	for ($pass = 0; $pass < 2; $pass++) {
		for ($i = 0; $i < count($files); $i++) {
			$runner->run_next('test');
		}
		$assert($adapter->query("SELECT COUNT(*) AS count FROM schema_migrations WHERE status = 'applied'")->row()->count == count($files),
			'All migrations applied, pass '.($pass + 1).'.');
		$assert($adapter->query("SELECT COUNT(*) AS count FROM acc_accounts")->row()->count == 23, 'Default chart contains 23 distinct accounts.');
		$assert($adapter->query("SELECT jumlah FROM stok WHERE id = 1")->row()->jumlah == 2, 'Stock quantities are preserved.');
		$assert($adapter->query("SELECT COUNT(*) AS count FROM acc_journals")->row()->count == 0, 'No transaction journals or demo data are seeded.');
		$assert($runner->run_next('test') === 'Semua migrasi sudah diterapkan.', 'Applied migrations are skipped.');
		if ($pass === 0) {
			$adapter->query("DELETE FROM group_pages WHERE group_id = 4 AND page_key = 'penjualan'");
			$adapter->query('DELETE FROM schema_migrations');
		}
	}
	$assert($adapter->query("SELECT COUNT(*) AS count FROM group_pages WHERE group_id = 4 AND page_key = 'penjualan'")->row()->count == 0,
		'Existing custom group access is not reset by the auth upgrade.');
	$adapter->query("UPDATE schema_migrations SET checksum = REPEAT('0', 64) WHERE migration_id = '001_purchasing'");
	$expect_error(function () use ($runner) { $runner->run_next('test'); }, 'berubah setelah dicatat');
	$adapter->query("UPDATE schema_migrations SET checksum = '".$adapter->escape_str(hash('sha256',
		str_replace(array("\r\n", "\r"), "\n", file_get_contents($directory.DIRECTORY_SEPARATOR.'001_purchasing.sql')))).
		"' WHERE migration_id = '001_purchasing'");
	$adapter->query("DELETE FROM schema_migrations WHERE migration_id = '001_purchasing'");
	$expect_error(function () use ($runner) { $runner->run_next('test'); }, 'setelah nomor terakhir');
	$lock_name = 'simpleton_schema_'.substr(hash('sha256', $database), 0, 32);
	$other = new mysqli($settings['hostname'], $settings['username'], $settings['password'], $database,
		isset($settings['port']) ? (int) $settings['port'] : 3306);
	try {
		$other->query("SELECT GET_LOCK('".$lock_name."', 0)");
		$expect_error(function () use ($runner) { $runner->run_next('test'); }, 'request lain');
	} finally {
		$other->close();
	}
	echo 'PASS Database integration: repeat-safe upgrades, failure, checksums and concurrent runner lock.'.PHP_EOL;
} finally {
	if (isset($adapter)) { $adapter->close(); }
	if (!$connection->query('DROP DATABASE `'.$database.'`')) {
		throw new RuntimeException('Could not remove isolated test database '.$database);
	}
	$connection->close();
}
