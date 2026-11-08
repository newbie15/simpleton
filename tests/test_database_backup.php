<?php
require __DIR__.'/bootstrap.php';
define('APPPATH', dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR);
define('FCPATH', dirname(__DIR__).DIRECTORY_SEPARATOR);
require APPPATH.'libraries'.DIRECTORY_SEPARATOR.'Database_backup.php';
function &get_instance() { return $GLOBALS['backup_ci']; }
function log_message($level, $message) { if ($level === 'error') { fwrite(STDERR, $message.PHP_EOL); } }
class Backup_test_load { public function database() {} }
class Backup_test_config {
	public $settings;
	public function load($name) {}
	public function item($name) { return $this->settings; }
}
$assert = function ($condition, $message) {
	if (!$condition) { throw new RuntimeException('FAIL '.$message); }
	echo 'PASS '.$message.PHP_EOL;
};
$reject = function ($action) {
	try { $action(); } catch (RuntimeException $exception) { echo 'PASS Rejected: '.$exception->getMessage().PHP_EOL; return; }
	throw new RuntimeException('Expected rejection');
};
$service = (new ReflectionClass('Database_backup'))->newInstanceWithoutConstructor();
$reject(function () use ($service) { $service->path('../outside'); });
if (!in_array('--database', $argv, TRUE)) { exit(0); }
require APPPATH.'config'.DIRECTORY_SEPARATOR.'database.php';
require BASEPATH.'database'.DIRECTORY_SEPARATOR.'DB.php';
require APPPATH.'config'.DIRECTORY_SEPARATOR.'database_backup.php';
mysqli_report(MYSQLI_REPORT_OFF);
$settings = $db[$active_group];
$connection = new mysqli($settings['hostname'], $settings['username'], $settings['password']);
if ($connection->connect_errno) { throw new RuntimeException('Database test connection failed.'); }
$name = 'simpleton_backup_test_'.substr(md5(uniqid('', TRUE)), 0, 12);
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
if (!$connection->query('CREATE DATABASE `'.$name.'`')) { throw new RuntimeException('Cannot create test schema.'); }
try {
	$settings['database'] = $name;
	$settings['pconnect'] = FALSE;
	$adapter = DB($settings, TRUE);
	$test_config = new Backup_test_config();
	$test_config->settings = $config['database_backup'];
	$test_config->settings['directory'] = $directory;
	$GLOBALS['backup_ci'] = (object) array('db' => $adapter, 'load' => new Backup_test_load(), 'config' => $test_config);
	$adapter->query('CREATE TABLE test_values (id int PRIMARY KEY, value varchar(64)) ENGINE=InnoDB');
	$adapter->query("INSERT INTO test_values VALUES (1, 'before restore')");
	$adapter->query('CREATE VIEW test_view AS SELECT * FROM test_values');
	$service = new Database_backup();
	$record = $service->backup('test');
	$assert($record['bytes'] > 0 && count($service->records()) === 1, 'Backup dump and metadata persist.');
	$assert($record['format'] === 'zip' && $record['bytes'] < $record['sql_bytes'], 'ZIP backup is smaller than original SQL.');
	$assert(!is_file($service->path($record['id'])), 'Uncompressed SQL is removed after ZIP creation.');
	$zip = new ZipArchive();
	$assert($zip->open($service->archive_path($record)) === TRUE && $zip->numFiles === 1, 'ZIP contains exactly one SQL entry.');
	$sql = $zip->getFromIndex(0);
	$zip->close();
	$assert(hash('sha256', $sql) === $record['sql_checksum'], 'Archived SQL content checksum is preserved.');
	$service->record($record['id']);
	$adapter->query("UPDATE test_values SET value = 'after backup'");
	$safety = $service->restore($record['id'], 'test');
	$assert($adapter->query('SELECT value FROM test_values')->row()->value === 'before restore', 'Restore recovers original data.');
	$assert(count($service->records()) === 2 && $safety['id'] !== $record['id'], 'Restore creates a separate safety backup.');
	$assert($adapter->query('SELECT value FROM test_view')->row()->value === 'before restore', 'Views are restored.');
	$legacy = $record;
	$legacy['id'] = 'backup_20000101_000000_abcdef123456';
	unset($legacy['format'], $legacy['sql_bytes'], $legacy['sql_checksum']);
	$legacy['checksum'] = hash('sha256', $sql);
	$legacy['bytes'] = strlen($sql);
	file_put_contents($service->path($legacy['id']), $sql);
	file_put_contents($service->path($legacy['id'], 'json'), json_encode($legacy));
	$adapter->query("UPDATE test_values SET value = 'legacy restore test'");
	$service->restore($legacy['id'], 'test');
	$assert($adapter->query('SELECT value FROM test_values')->row()->value === 'before restore', 'Legacy SQL backups still restore correctly.');
	$adapter->query('CREATE TABLE added_table (id int)');
	$reject(function () use ($service, $record) { $service->restore($record['id'], 'test'); });
	file_put_contents($service->archive_path($record), '-- corruption', FILE_APPEND);
	$reject(function () use ($service, $record) { $service->record($record['id']); });
} finally {
	if (isset($adapter)) { $adapter->close(); }
	if (!$connection->query('DROP DATABASE `'.$name.'`')) { throw new RuntimeException('Cannot clean test database '.$name); }
	$connection->close();
	if (is_dir($directory)) {
		foreach (scandir($directory) as $file) {
			if ($file !== '.' && $file !== '..') { unlink($directory.DIRECTORY_SEPARATOR.$file); }
		}
		rmdir($directory);
	}
}
