<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/application/modules/main/controllers/Bank_reconciliation.php');
$view = file_get_contents($root.'/application/modules/main/views/bank_reconciliation.php');

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($controller, "COALESCE(SUM(status = 'draft'), 0) AS draft") !== FALSE &&
	strpos($controller, "COALESCE(SUM(status = 'reconciled'), 0) AS reconciled") !== FALSE &&
	strpos($controller, "'total' => \$counts ? (int) \$counts->total : 0") !== FALSE &&
	strpos($view, 'Ringkasan di atas menghitung seluruh dokumen.') !== FALSE,
	'Ringkasan dokumen menghitung seluruh data, terpisah dari daftar 100 terbaru.');
$assert(substr_count($controller, '$this->_require_manage();') === 6 &&
	strpos($controller, "in_group(array('admin', 'management', 'pengurus'))") !== FALSE &&
	strpos($controller, 'show_error(\'Anda tidak memiliki izin untuk mengubah rekonsiliasi bank.\', 403)') !== FALSE,
	'Semua endpoint perubahan memeriksa grup pengelola pada server.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
