<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Sales_return.php';

$source = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Sales_return.php');
$migration = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'sql'.DIRECTORY_SEPARATOR.'sales_return_approval.sql');
$view = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'sales_return.php');
$ajax = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Ajax.php');
$save_start = strpos($source, 'public function save()');
$approve_start = strpos($source, 'public function approve(');
$reject_start = strpos($source, 'public function reject(');
$save = substr($source, $save_start, $approve_start - $save_start);
$approve = substr($source, $approve_start, $reject_start - $approve_start);
$reject = substr($source, $reject_start);

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($save, "'status' => 'pending'") !== FALSE,
	'Submitting a return creates a pending request.');
$assert(strpos($save, 'post_journal') === FALSE && strpos($save, "->update('stok'") === FALSE,
	'Submission does not post a journal or mutate stock.');
$assert(strpos($save, "return_header.status IN ('pending', 'approved', 'completed')") !== FALSE,
	'Pending returns reserve the remaining return quantity.');
$assert(strpos($approve, "return->status !== 'pending'") !== FALSE &&
	strpos($approve, '->update(\'stok\'') !== FALSE && strpos($approve, 'post_journal') !== FALSE,
	'Only the pending-return approval flow changes stock and posts the journal.');
$assert(strpos($approve, "'approved_by'") !== FALSE && strpos($approve, "'approved_at'") !== FALSE,
	'Approval records the manager and timestamp.');
$assert(strpos($reject, "'status' => 'rejected'") !== FALSE &&
	strpos($reject, "'rejection_reason' => $reason") !== FALSE,
	'Rejection records the decision and required reason.');
$assert(strpos($migration, "enum('pending','approved','rejected','completed','cancelled')") !== FALSE &&
	strpos($migration, 'rejection_reason') !== FALSE,
	'Migration adds approval states and decision audit fields.');
$assert(strpos($view, 'Setujui') !== FALSE && strpos($view, 'Tolak') !== FALSE &&
	strpos($view, 'Menunggu manager') !== FALSE,
	'Return UI exposes manager decisions and pending status.');
$assert(strpos($ajax, "return_header.status IN ('approved', 'completed')") !== FALSE &&
	strpos($ajax, 'AS tertagih') !== FALSE &&
	strpos($ajax, 'AS retur_disetujui') !== FALSE,
	'Personal and monthly billing reports deduct approved returns and show net billable totals.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);