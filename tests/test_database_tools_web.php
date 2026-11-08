<?php
require __DIR__.'/test_migrate_web.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'modules'.
	DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Database_tools.php';
class Backup_web_auth extends Migration_web_auth {
	public $password_valid = FALSE;
	public $logged_out = FALSE;
	public function hash_password_db($id, $password) { return $this->password_valid; }
	public function logout() { $this->logged_out = TRUE; }
}
class Backup_web_runner {
	public $calls = 0;
	public function backup($id) { $this->calls++; return array('id' => 'test-backup'); }
	public function restore($backup, $id) { $this->calls++; return array('id' => 'safety-backup'); }
}
$tools = (new ReflectionClass('Database_tools'))->newInstanceWithoutConstructor();
$tools->load = new Migration_web_load();
$tools->ion_auth = new Backup_web_auth();
$tools->session = new Migration_web_session();
$tools->input = new Migration_web_input();
$tools->database_backup = new Backup_web_runner();
$tools->db = (object) array('database' => 'test_database');
$tools->ion_auth->admin = FALSE;
$expect(function () use ($tools) { $tools->__construct(); }, 403, 'administrator');
$tools->ion_auth->admin = TRUE;
$tools->input->method = 'GET';
$expect(function () use ($tools) { $tools->backup(); }, 405, 'Metode');
$tools->input->method = 'POST';
$tools->input->values['form_token'] = 'wrong';
$expect(function () use ($tools) { $tools->backup(); }, 403, 'Sesi');
$tools->input->values['form_token'] = 'test-token';
$expect(function () use ($tools) { $tools->backup(); }, 400, 'Hentikan');
$tools->input->values['maintenance_confirmed'] = '1';
$tools->input->values['database_confirmation'] = 'wrong_database';
$expect(function () use ($tools) { $tools->restore(); }, 400, 'Konfirmasi');
$tools->input->values['database_confirmation'] = 'test_database';
$tools->input->values['admin_password'] = 'wrong_password';
$expect(function () use ($tools) { $tools->restore(); }, 403, 'Password');
if ($tools->database_backup->calls !== 0) { throw new RuntimeException('Invalid input changed database.'); }
$expect(function () use ($tools) { $tools->backup(); }, 0, 'main/database_tools');
$tools->ion_auth->password_valid = TRUE;
$tools->input->values['backup_id'] = 'test-backup';
$expect(function () use ($tools) { $tools->restore(); }, 0, 'auth/login');
if (!$tools->ion_auth->logged_out || $tools->database_backup->calls !== 2) {
	throw new RuntimeException('Valid restore did not execute and log out.');
}
echo 'PASS Backup/restore web guards and logout after restore.'.PHP_EOL;
