<?php
/**
 * Test runner minimalis untuk validasi ruang kerja buku besar baru.
 *
 * Jalankan: php tests/test_accounting_new.php
 * Pengujian helper tidak memerlukan koneksi database CodeIgniter.
 */

require __DIR__.'/bootstrap.php';

$controller_path = dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.
	'Accounting_new.php';
require_once $controller_path;

$controller = (new ReflectionClass('Accounting_new'))->newInstanceWithoutConstructor();
$tests = array();
$invoke = function ($method, $arguments) use ($controller) {
	$reflection = new ReflectionMethod('Accounting_new', $method);
	$reflection->setAccessible(TRUE);
	return $reflection->invokeArgs($controller, $arguments);
};
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert($invoke('_amount_cents', array('1500.25')) === '150025', 'Parses decimal amounts as exact string cents.');
$assert($invoke('_amount_cents', array('0')) === '0', 'Allows zero on an unused debit or credit side.');
$assert($invoke('_amount_cents', array('9999999999999.99')) === '999999999999999', 'Accepts the maximum DECIMAL(15,2) amount on 32-bit PHP.');
$assert($invoke('_amount_cents', array('00012.5')) === '1250', 'Normalizes leading zeroes and a one-digit fraction.');
$assert($invoke('_amount_cents', array('-1.00')) === FALSE, 'Rejects negative journal amounts.');
$assert($invoke('_amount_cents', array('1,000')) === FALSE, 'Rejects locale-formatted journal amounts.');
$assert($invoke('_amount_cents', array('1.001')) === FALSE, 'Rejects amounts with more than two decimal places.');
$assert($invoke('_amount_cents', array('10000000000000')) === FALSE, 'Rejects amounts outside DECIMAL(15,2).');

$assert($invoke('_positive_id', array('12')) === 12, 'Parses a positive account ID.');
$assert($invoke('_positive_id', array('0')) === FALSE, 'Rejects zero as an account ID.');
$assert($invoke('_positive_id', array('12x')) === FALSE, 'Rejects non-numeric account IDs.');
$assert($invoke('_is_date', array('2026-10-10')) === TRUE, 'Accepts a valid calendar date.');
$assert($invoke('_is_date', array('2026-02-30')) === FALSE, 'Rejects an invalid calendar date.');
$assert($invoke('_add_cents', array('999999999999999', '1')) === '1000000000000000', 'Adds large journal totals without integer overflow.');
$assert($invoke('_compare_cents', array('999999999999999', '1000000000000000')) < 0, 'Compares decimal-string totals by magnitude.');
$assert($invoke('_cents_amount', array('150025')) === '1500.25', 'Converts exact cents to a decimal value for journal storage.');

$source = file_get_contents($controller_path);
$view = file_get_contents(dirname($controller_path).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.
	'views'.DIRECTORY_SEPARATOR.'accounting_new.php');
$assert(strpos($source, "'MANUAL_NEW'") !== FALSE &&
	strpos($source, '$this->accounting->post_journal') !== FALSE, 'New manual entries post through the shared journal service.');
$assert(strpos($source, 'accounting_new_form_token') !== FALSE &&
	strpos($view, 'name="form_token"') !== FALSE, 'Manual journal posts require the workspace form token.');
$assert(strpos($source, '$this->db->trans_begin()') !== FALSE &&
	strpos($source, '$this->db->trans_commit()') !== FALSE, 'Manual journal and journal lines are saved within a database transaction.');
$assert(strpos($view, 'Buku akun') !== FALSE &&
	strpos($view, 'Transaksi baru') !== FALSE &&
	strpos($view, 'name="account_id[]"') !== FALSE &&
	strpos($source, 'Total debit harus sama') !== FALSE, 'New workspace separates the account register and validates journal balancing.');
$assert(strpos($view, 'buku besar bersama') !== FALSE, 'The posting form warns that new journals affect the shared ledger.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
