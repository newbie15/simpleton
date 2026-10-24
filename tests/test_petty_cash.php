<?php

require __DIR__.DIRECTORY_SEPARATOR.'bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash.php';

$controller = (new ReflectionClass('Petty_cash'))->newInstanceWithoutConstructor();
$tests = array();

class PettyCashTestResult
{
	private $value;
	public function __construct($value) { $this->value = $value; }
	public function row() { return $this->value; }
}

class PettyCashTestDatabase
{
	public function query($sql, $params) { return new PettyCashTestResult(NULL); }
	public function get_where($table, $where) { return new PettyCashTestResult((object) array('imprest_limit' => 4573000)); }
}

$invoke = function ($method, $arguments) use ($controller) {
	$reflection = new ReflectionMethod('Petty_cash', $method);
	$reflection->setAccessible(TRUE);
	return $reflection->invokeArgs($controller, $arguments);
};
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert($invoke('_parse_amount', array('1500.25')) === 1500.25, 'Parses a valid cash amount.');
$assert($invoke('_parse_amount', array('0.00')) === 0.0, 'Allows zero for counted physical cash.');
$assert($invoke('_parse_amount', array('-1')) === FALSE, 'Rejects negative amounts.');
$assert($invoke('_parse_amount', array('1,000')) === FALSE, 'Rejects locale-formatted amounts.');
$assert($invoke('_parse_amount', array('10000000000000')) === FALSE, 'Rejects amounts beyond journal range.');
$assert($invoke('_positive_id', array('12')) === 12, 'Parses a positive record ID.');
$assert($invoke('_positive_id', array('0')) === FALSE, 'Rejects zero as a record ID.');
$assert($invoke('_is_date', array('2026-10-10')) === TRUE, 'Accepts a valid calendar date.');
$assert($invoke('_is_date', array('2026-02-30')) === FALSE, 'Rejects an invalid calendar date.');
$assert($invoke('_fund_account_code', array('cashier')) === '1101', 'Maps cashier funds to account 1101.');
$assert($invoke('_fund_account_code', array('main')) === '1106', 'Maps main funds to account 1106.');
$assert($invoke('_fund_account_code', array('petty')) === '1107', 'Maps petty cash to account 1107.');
$assert($invoke('_fund_account_code', array('invalid')) === '', 'Rejects unknown cash-fund types.');
$controller->db = new PettyCashTestDatabase();
$assert($invoke('_unreplenished_expenses', array(12, '2026-08-31', 99)) === 4573000.0,
	'Allows the first petty cash advance up to the fund imprest limit.');

$migration = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'sql'.DIRECTORY_SEPARATOR.'petty_cash.sql');
$assert(strpos($migration, "enum('cashier','main','petty')") !== FALSE, 'Migration declares separate cashier, main, and petty funds.');
$assert(strpos($migration, "enum('funding','expense','supplier_payment')") !== FALSE, 'Migration distinguishes operating expenses from supplier payments.');
$assert(strpos($migration, 'UNIQUE KEY `idx_acc_cash_fund_slot`') !== FALSE, 'Migration enforces one system cashier and main fund.');

$controller_source = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash.php');
$view_source = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'petty_cash.php');
$assert(strpos($controller_source, 'journal.date < ?') !== FALSE &&
	strpos($controller_source, 'journal.date = ?') !== FALSE, 'Daily cash report separates opening balance from dated movements.');
$assert(strpos($controller_source, 'cash_tx.fund_id = ?') !== FALSE, 'Daily cash report filters ledger entries to the selected fund.');
$assert(strpos($view_source, 'Buku kas harian') !== FALSE &&
	strpos($view_source, 'Saldo awal') !== FALSE &&
	strpos($view_source, 'Saldo berjalan') !== FALSE &&
	strpos($view_source, 'Saldo akhir sistem') !== FALSE, 'Daily cash view presents opening, running, and closing balances.');
$assert(strpos($controller_source, 'if (!$supplier_receipt || $outstanding === FALSE') !== FALSE,
	'Supplier-payment approval validates the locked goods receipt before posting.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
