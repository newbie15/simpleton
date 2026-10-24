<?php
/**
 * Focused tests for the spreadsheet accounting and petty-cash workspaces.
 *
 * Run: php tests/test_spreadsheet_workspaces.php
 */

require __DIR__.'/bootstrap.php';

$base = dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main';
require_once $base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Accounting_grid.php';
require_once $base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash_grid.php';

$accounting = (new ReflectionClass('Accounting_grid'))->newInstanceWithoutConstructor();
$petty_cash = (new ReflectionClass('Petty_cash_grid'))->newInstanceWithoutConstructor();
$invoke = function ($controller, $class, $method, $arguments) {
	$reflection = new ReflectionMethod($class, $method);
	$reflection->setAccessible(TRUE);
	return $reflection->invokeArgs($controller, $arguments);
};
$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert($invoke($accounting, 'Accounting_grid', '_amount_cents', array('1500.25')) === '150025',
	'Accounting grid parses decimals exactly as cents.');
$assert($invoke($accounting, 'Accounting_grid', '_amount_cents', array('1,500')) === FALSE,
	'Accounting grid rejects locale-formatted numbers.');
$assert($invoke($accounting, 'Accounting_grid', '_amount_cents', array('1.001')) === FALSE,
	'Accounting grid rejects more than two decimal places.');
$assert($invoke($accounting, 'Accounting_grid', '_is_date', array('2026-02-30')) === FALSE,
	'Accounting grid rejects impossible calendar dates.');
$assert($invoke($petty_cash, 'Petty_cash_grid', '_amount_cents', array('250000')) === '25000000',
	'Petty cash grid parses whole-number amounts as cents.');
$assert($invoke($petty_cash, 'Petty_cash_grid', '_amount_cents', array('-1')) === FALSE,
	'Petty cash grid rejects negative amounts.');
$assert($invoke($petty_cash, 'Petty_cash_grid', '_is_date', array('2026-01-31')) === TRUE,
	'Petty cash grid accepts a real calendar date.');

$accounting_source = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Accounting_grid.php');
$petty_source = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash_grid.php');
$petty_handler = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash.php');
$accounting_view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'accounting_grid.php');
$petty_view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'petty_cash_grid.php');
$assert(strpos($accounting_source, 'MANUAL_GRID') !== FALSE &&
	strpos($accounting_source, '$this->accounting->post_journal') !== FALSE &&
	strpos($accounting_source, '$this->db->trans_rollback()') !== FALSE,
	'Accounting grid posts through the shared journal service and rolls back failed batches.');
$assert(strpos($accounting_source, 'accounting_grid_form_token') !== FALSE &&
	strpos($accounting_view, 'name="form_token"') !== FALSE &&
	strpos($accounting_view, "type: 'dropdown'") !== FALSE,
	'Accounting grid includes a session token and account dropdowns.');
$assert(strpos($petty_source, "'pending'") !== FALSE &&
	strpos($petty_source, "'funding'") !== FALSE &&
	strpos($petty_source, "'expense'") !== FALSE,
	'Petty cash grid stores standard pending funding and expense transactions.');
$expense_validation_start = strpos($petty_handler, '$expense = $this->db->get_where(\'acc_accounts\', array(');
$expense_validation_end = $expense_validation_start === FALSE
	? FALSE
	: strpos($petty_handler, '))->row();', $expense_validation_start);
$expense_validation = $expense_validation_end === FALSE
	? ''
	: substr($petty_handler, $expense_validation_start, $expense_validation_end - $expense_validation_start);
$assert(strpos($expense_validation, "'type' => 'expense'") !== FALSE &&
	strpos($expense_validation, "'type' => 'asset'") === FALSE,
	'The shared approval handler validates expense accounts as expenses.');
$assert(strpos($petty_source, 'petty_cash_grid_form_token') !== FALSE &&
	strpos($petty_view, 'name="form_token"') !== FALSE &&
	strpos($petty_view, 'belum mengubah saldo') !== FALSE,
	'Petty cash grid uses a session token and tells users entries await approval.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
