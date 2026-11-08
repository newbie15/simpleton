<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Approval_notifications.php';

function site_url($path) { return 'http://example.test/index.php/'.$path; }
function log_message($level, $message) { $GLOBALS['notification_logs'][] = $message; }

class NotificationAuth
{
	public $groups = array();
	public $logged_in = TRUE;
	public function logged_in() { return $this->logged_in; }
	public function get_user_id() { return 7; }
	public function in_group($groups) { return (bool) array_intersect((array) $groups, $this->groups); }
}

class NotificationAccess
{
	public $allowed = array();
	public function can_access($page) { return in_array($page, $this->allowed, TRUE); }
}

class NotificationInput
{
	public $page = NULL;
	public function get($key, $filter) { return $this->page; }
}

class NotificationOutput
{
	public $status;
	public $body;
	public function set_status_header($status) { $this->status = $status; return $this; }
	public function set_content_type($type, $charset) { return $this; }
	public function set_header($header) { return $this; }
	public function set_output($body) { $this->body = $body; return $this; }
}

class NotificationResult
{
	private $count;
	public function __construct($count) { $this->count = $count; }
	public function row() { return (object) array('total' => $this->count); }
	public function result()
	{
		return array((object) array('id' => 42, 'period_id' => 4, 'reference' => '<reference>',
			'details' => 'Detail', 'amount' => '100.00', 'created' => '2026-01-01'));
	}
}

class NotificationDb
{
	public $db_debug = TRUE;
	public $missing = array();
	public $fail = FALSE;
	public $queries = array();
	public function table_exists($table) { return !in_array($table, $this->missing, TRUE); }
	public function query($sql, $params)
	{
		$this->queries[] = array($sql, $params);
		return $this->fail ? FALSE : new NotificationResult(22);
	}
	public function error() { return array('code' => 1, 'message' => 'test query failure'); }
}

class TestNotificationController extends Approval_notifications
{
	public $ion_auth;
	public $page_access;
	public $input;
	public $output;
	public $db;
	public function __construct()
	{
		$this->ion_auth = new NotificationAuth();
		$this->page_access = new NotificationAccess();
		$this->input = new NotificationInput();
		$this->output = new NotificationOutput();
		$this->db = new NotificationDb();
	}
}

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};
$run = function ($controller) {
	$controller->index();
	return json_decode($controller->output->body, TRUE);
};

$controller = new TestNotificationController();
$controller->ion_auth->logged_in = FALSE;
$response = $run($controller);
$assert($controller->output->status === 401 && isset($response['error']) && !$controller->db->queries,
	'An expired session is rejected without querying notifications.');

$controller = new TestNotificationController();
$controller->ion_auth->groups = array('kasir');
$controller->page_access->allowed = array('petty_cash', 'sales_return', 'supplier_return', 'accounting');
$response = $run($controller);
$assert($response['groups'] === array() && !$controller->db->queries,
	'Users outside approver groups do not receive approval items.');

$controller = new TestNotificationController();
$controller->ion_auth->groups = array('management');
$controller->page_access->allowed = array('petty_cash', 'sales_return', 'accounting');
$controller->input->page = '2';
$response = $run($controller);
$queries = $controller->db->queries;
$assert(count($response['groups']) === 3 && $response['groups'][0]['count'] === 22 &&
	strpos($queries[1][0], 'LIMIT 10 OFFSET 10') !== FALSE,
	'Authorized categories include counts and use the requested page offset.');
$assert(strpos($queries[0][0], "t.status = 'pending' AND t.created_by <> ?") !== FALSE &&
	$queries[0][1] === array('7') &&
	$response['groups'][0]['items'][0]['url'] === 'http://example.test/index.php/main/petty_cash_new?tab=requests&transaction_id=42',
	'Cash notifications exclude the current creator and link to the specific transaction.');
$assert(strpos($queries[4][0], 't.revised_by <> ?') !== FALSE &&
	strpos($queries[4][0], "p.status = 'open'") !== FALSE &&
	strpos($queries[4][0], "r.validation_mode IN ('period', 'cutoff')") !== FALSE &&
	$queries[4][1] === array('7') &&
	$response['groups'][2]['items'][0]['url'] === 'http://example.test/index.php/main/sales_billing/revisions/4?adjustment_id=42',
	'Management revisions exclude their own requests and link to the selected eligible revision.');

$controller = new TestNotificationController();
$controller->ion_auth->groups = array('admin');
$controller->page_access->allowed = array('accounting');
$response = $run($controller);
$assert(count($response['groups']) === 1 && strpos($controller->db->queries[0][0], 't.revised_by <> ?') === FALSE &&
	$controller->db->queries[0][1] === array(),
	'Admin can see their own eligible billing revisions, without access to other categories.');

$controller = new TestNotificationController();
$controller->ion_auth->groups = array('pengurus');
$controller->page_access->allowed = array('supplier_return', 'sales_return');
$response = $run($controller);
$assert(count($response['groups']) === 1 && $response['groups'][0]['label'] === 'Retur supplier' &&
	strpos($controller->db->queries[0][0], "t.status = 'draft'") !== FALSE,
	'Pengurus sees supplier drafts but not manager-only sales returns.');

$controller = new TestNotificationController();
$controller->input->page = '0';
$response = $run($controller);
$assert($controller->output->status === 400 && isset($response['error']) && !$controller->db->queries,
	'Invalid pagination is rejected.');

$controller = new TestNotificationController();
$controller->ion_auth->groups = array('management');
$controller->page_access->allowed = array('petty_cash', 'sales_return');
$controller->db->missing = array('acc_cash_funds');
$response = $run($controller);
$assert(count($response['errors']) === 1 && count($response['groups']) === 1 &&
	$response['groups'][0]['label'] === 'Retur penjualan',
	'Missing schema is reported while available categories remain visible.');

$controller = new TestNotificationController();
$controller->ion_auth->groups = array('admin');
$controller->page_access->allowed = array('petty_cash');
$controller->db->fail = TRUE;
$GLOBALS['notification_logs'] = array();
$response = $run($controller);
$assert(count($response['errors']) === 1 && $response['groups'] === array() &&
	count($GLOBALS['notification_logs']) === 1 && $controller->db->db_debug === TRUE,
	'Query failures are logged and surfaced, with database debugging restored.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) { $failures++; }
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
