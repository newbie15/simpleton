<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/application/modules/main/controllers/Hpp.php');
$view = file_get_contents($root.'/application/modules/main/views/hpp.php');
$datatable = file_get_contents($root.'/assets/purchasing/hpp_inventory.js');
$purchasing = file_get_contents($root.'/application/modules/main/controllers/Purchasing.php');
$menu = file_get_contents($root.'/application/modules/main/views/main_menu.php');
$migration = file_get_contents($root.'/application/sql/inventory_cost_history.sql');

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($controller, 'goods_receipt_items item') !== FALSE &&
	strpos($controller, 'supplier.name AS supplier_name') !== FALSE &&
	strpos($view, 'Riwayat harga dari Purchasing') !== FALSE,
	'Riwayat HPP menampilkan penerimaan dan supplier.');
$assert(strpos($controller, 'public function inventory_data()') !== FALSE &&
	strpos($controller, "'recordsTotal' => (int) \$total") !== FALSE &&
	strpos($controller, "'recordsFiltered' => (int) \$filtered") !== FALSE &&
	strpos($datatable, 'serverSide: true') !== FALSE &&
	strpos($controller, 'hpp_inventory.js?v=') !== FALSE,
	'Daftar HPP memakai DataTables server-side dengan pencarian dan pagination.');
$assert(strpos($purchasing, "'average_cost_after' => \$new_average_cost") !== FALSE &&
	strpos($migration, 'average_cost_after') !== FALSE,
	'Purchasing mencatat HPP rata-rata setelah penerimaan.');
$assert(strpos($controller, 'inventory_cost_history') !== FALSE &&
	strpos($view, 'Manual (bukan vendor/supplier)') !== FALSE &&
	strpos($controller, 'previous_average_cost') !== FALSE,
	'HPP manual memiliki sumber dan riwayat perubahan tersendiri.');
$assert(strpos($controller, 'in_group(array(\'admin\', \'management\', \'manager\', \'pengurus\'))') !== FALSE &&
	strpos($menu, 'main/hpp') !== FALSE,
	'Halaman terhubung ke menu Purchasing dan perubahan manual dibatasi.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
