<?php
/**
 * Test runner minimalis untuk ruang kerja petty cash baru.
 *
 * Jalankan: php tests/test_petty_cash_new.php
 */

require __DIR__.'/bootstrap.php';

$base = dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main';
$controller_path = $base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash_new.php';
require_once $controller_path;

$controller = (new ReflectionClass('Petty_cash_new'))->newInstanceWithoutConstructor();
$tests = array();
$invoke = function ($method, $arguments) use ($controller) {
	$reflection = new ReflectionMethod('Petty_cash_new', $method);
	$reflection->setAccessible(TRUE);
	return $reflection->invokeArgs($controller, $arguments);
};
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert($invoke('_fund_account_code', array('cashier')) === '1101', 'Maps cashier fund balances to cash register account 1101.');
$assert($invoke('_fund_account_code', array('main')) === '1106', 'Maps main fund balances to account 1106.');
$assert($invoke('_fund_account_code', array('petty')) === '1107', 'Maps petty cash balances to account 1107.');
$assert($invoke('_fund_account_code', array('unknown')) === '', 'Rejects unknown fund types.');
$assert($invoke('_positive_id', array('5')) === 5, 'Parses a valid fund ID.');
$assert($invoke('_positive_id', array('0')) === FALSE, 'Rejects zero fund IDs.');
$assert($invoke('_is_date', array('2026-10-10')) === TRUE, 'Accepts a real calendar date.');
$assert($invoke('_is_date', array('2026-02-30')) === FALSE, 'Rejects an impossible calendar date.');

$view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'petty_cash_new.php');
$legacy_view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'petty_cash.php');
$legacy_controller = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash.php');
$assert(strpos($view, 'main/petty_cash/submit_transaction') !== FALSE &&
	strpos($view, 'main/petty_cash/approve/') !== FALSE &&
	strpos($view, 'main/petty_cash/close_day') !== FALSE, 'New workspace uses the established transaction and approval handlers.');
$assert(substr_count($view, 'name="form_token"') >= 5 &&
	substr_count($legacy_view, 'name="form_token"') >= 5, 'Both workspaces submit the form token for every cash operation.');
$assert(strpos($legacy_controller, "userdata('petty_cash_form_token')") !== FALSE &&
	strpos($legacy_controller, 'Sesi formulir kas tidak valid') !== FALSE, 'Cash operation handlers validate the workspace form token.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
