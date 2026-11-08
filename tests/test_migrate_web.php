<?php
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Migrate.php';

class Migration_web_redirect extends RuntimeException {}
function redirect($uri, $method = '') { throw new Migration_web_redirect($uri); }
function log_message($level, $message) {}
class Migration_web_load {
	public function helper($helpers) {}
	public function library($libraries) {}
}
class Migration_web_auth {
	public $logged_in = TRUE;
	public $admin = TRUE;
	public function logged_in() { return $this->logged_in; }
	public function is_admin() { return $this->admin; }
	public function get_user_id() { return 1; }
}
class Migration_web_session {
	public $flash = array();
	public function userdata($key) { return 'test-token'; }
	public function set_flashdata($key, $value) { $this->flash[$key] = $value; }
}
class Migration_web_input {
	public $method = 'POST';
	public $values = array('form_token' => 'test-token', 'backup_confirmed' => '1');
	public function method($upper) { return $this->method; }
	public function post($key) { return isset($this->values[$key]) ? $this->values[$key] : NULL; }
}
class Migration_web_runner {
	public $calls = 0;
	public $fail = FALSE;
	public function run_next($user_id) {
		$this->calls++;
		if ($this->fail) { throw new RuntimeException('Test SQL failure'); }
		return 'Applied one migration';
	}
}
$controller = (new ReflectionClass('Migrate'))->newInstanceWithoutConstructor();
$controller->load = new Migration_web_load();
$controller->ion_auth = new Migration_web_auth();
$controller->input = new Migration_web_input();
$controller->session = new Migration_web_session();
$controller->schema_migrator = new Migration_web_runner();

$expect = function ($action, $code, $message) {
	try { $action(); } catch (RuntimeException $exception) {
		if ($exception->getCode() !== $code || strpos($exception->getMessage(), $message) === FALSE) {
			throw $exception;
		}
		echo 'PASS '.$message.PHP_EOL;
		return;
	}
	throw new RuntimeException('Expected '.$message);
};
$controller->ion_auth->logged_in = FALSE;
$expect(function () use ($controller) { $controller->__construct(); }, 0, 'auth/login');
$controller->ion_auth->logged_in = TRUE;
$controller->ion_auth->admin = FALSE;
$expect(function () use ($controller) { $controller->__construct(); }, 403, 'administrator');
$controller->ion_auth->admin = TRUE;
$controller->input->method = 'GET';
$expect(function () use ($controller) { $controller->run(); }, 405, 'Metode');
$controller->input->method = 'POST';
$controller->input->values['form_token'] = 'wrong-token';
$expect(function () use ($controller) { $controller->run(); }, 403, 'Sesi formulir');
$controller->input->values['form_token'] = array('test-token');
$expect(function () use ($controller) { $controller->run(); }, 403, 'Sesi formulir');
$controller->input->values['form_token'] = 'test-token';
unset($controller->input->values['backup_confirmed']);
$expect(function () use ($controller) { $controller->run(); }, 400, 'backup');
if ($controller->schema_migrator->calls !== 0) { throw new RuntimeException('Invalid requests ran SQL.'); }
$controller->input->values['backup_confirmed'] = '1';
$expect(function () use ($controller) { $controller->run(); }, 0, 'main/migrate');
if (!isset($controller->session->flash['migration_notice']) || $controller->schema_migrator->calls !== 1) {
	throw new RuntimeException('Valid web request did not execute one migration.');
}
$controller->schema_migrator->fail = TRUE;
$expect(function () use ($controller) { $controller->run(); }, 0, 'main/migrate');
if ($controller->session->flash['migration_error'] !== 'Test SQL failure') {
	throw new RuntimeException('SQL failure was not surfaced to web user.');
}
echo 'PASS Web execution, login, admin, POST, token, backup and error guards.'.PHP_EOL;

function site_url($uri) { return 'http://localhost/index.php/'.$uri; }
function html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$notice = NULL;
$error = '<script>test</script>';
$form_token = 'test-token';
$rows = array(array(
	'migration_id' => '001_test', 'filename' => 'test.sql', 'status' => 'pending',
	'changed' => FALSE, 'completed_at' => NULL, 'error_message' => '<bad>'
));
ob_start();
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'modules'.
	DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'migrate.php';
$html = ob_get_clean();
if (strpos($html, '&lt;script&gt;test&lt;/script&gt;') === FALSE ||
	strpos($html, 'name="backup_confirmed"') === FALSE ||
	strpos($html, 'main/migrate/run') === FALSE ||
	strpos($html, 'test.sql') === FALSE) {
	throw new RuntimeException('Migration view did not render the guarded form, status and escaped errors.');
}
echo 'PASS Migration page renders pending files, POST form, backup confirmation and escaped errors.'.PHP_EOL;
