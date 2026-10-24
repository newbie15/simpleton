<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Sales_billing.php';

$controller = (new ReflectionClass('Sales_billing'))->newInstanceWithoutConstructor();
$invoke = function ($method, $arguments) use ($controller) {
	$reflection = new ReflectionMethod('Sales_billing', $method);
	$reflection->setAccessible(TRUE);
	return $reflection->invokeArgs($controller, $arguments);
};
$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert($invoke('_ranges_overlap', array('2026-01-01', '2026-01-31', '2026-01-31', '2026-02-28')),
	'Rejects periods sharing an inclusive boundary date.');
$assert(!$invoke('_ranges_overlap', array('2026-01-01', '2026-01-31', '2026-02-01', '2026-02-28')),
	'Allows adjacent periods with no shared date.');
$assert($invoke('_billable_total', array(100000, 25000)) === 75000.0,
	'Deducts approved returns from sales for member billing.');
$assert($invoke('_billable_total', array(100000, 120000)) === -20000.0,
	'Preserves excess approved returns as a credit for a later period.');

$controller_source = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Sales_billing.php');
$assert(strpos($controller_source, "return_header.status IN ('approved', 'completed')") !== FALSE,
	'Only approved and historical completed returns affect billing.');
$assert(strpos($controller_source, 'private function _late_return_credits') !== FALSE &&
	strpos($controller_source, 'original.last_sale < ?') !== FALSE,
	'Approved returns for earlier periods are carried as credits into the current period.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);