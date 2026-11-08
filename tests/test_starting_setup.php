<?php

require __DIR__.'/bootstrap.php';

$base = dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'main';
$controller = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Starting_setup.php');
$view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'starting_setup.php');
$menu = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'main_menu.php');
$coa_seed = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'phase3_foundation.sql');

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($controller, "can_access('accounting')") !== FALSE &&
	strpos($controller, "redirect('auth/login'") !== FALSE,
	'Starting setup guide requires an authenticated accounting-authorized user.');
$assert(strpos($menu, 'main/starting_setup') !== FALSE &&
	strpos($menu, 'Setup Awal & Migrasi') !== FALSE,
	'Starting setup guide is reachable from the backend menu.');
$assert(strpos($view, 'mengganti seluruh isi tabel stok') !== FALSE &&
	strpos($view, 'barcode, nama, jumlah, harga, distributor, ppn') !== FALSE,
	'Guide warns that stock import replaces the full stock table and lists its format.');
$assert(strpos($view, 'Belum tersedia impor umum') !== FALSE &&
	strpos($view, 'saldo pembukaan umum pada akun hutang saja') !== FALSE,
	'Guide documents unsupported legacy imports and per-receipt supplier debt requirements.');
$assert(strpos($view, 'bukan membuat jurnal persediaan') !== FALSE &&
	strpos($view, 'jangan mencatat saldo yang sama dua kali') !== FALSE,
	'Guide warns that stock import does not post inventory journals and avoids duplicate petty-cash opening balances.');
$assert(strpos($coa_seed, "('1106', 'Kas Utama', 'asset', 'debit'") !== FALSE &&
	strpos($coa_seed, "('1107', 'Kas Kecil', 'asset', 'debit'") !== FALSE &&
	strpos($coa_seed, "('2101', 'Hutang Dagang Supplier', 'liability', 'credit'") !== FALSE,
	'Foundation SQL seeds the cash and payable accounts required by the core workflows.');
$assert(strpos($view, 'main/accounting_unified?tab=accounts') !== FALSE &&
	strpos($view, 'main/accounting_unified?tab=input') !== FALSE &&
	strpos($view, 'main/data_update') !== FALSE,
	'Guide links to existing import and opening-balance workflows.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
