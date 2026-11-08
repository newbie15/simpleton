<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/application/modules/main/controllers/Stock_opname.php');
$view = file_get_contents($root.'/application/modules/main/views/stock_opname.php');

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($controller, "\$this->ion_auth->is_admin()") !== FALSE &&
	strpos($controller, "\$this->ion_auth->in_group('pengurus')") !== FALSE &&
	strpos($view, 'if ($can_see_system_stock)') !== FALSE &&
	strpos($view, 'if (canSeeSystemStock)') !== FALSE,
	'Qty sistem dan selisih hanya ditampilkan kepada admin atau pengurus.');
$assert(strpos($controller, 'cost.average_cost') !== FALSE &&
	strpos($controller, "'unit_cost' => \$product->average_cost") !== FALSE &&
	strpos($view, "title: 'HPP Sistem / Unit'") !== FALSE &&
	strpos($view, "readOnly: true") !== FALSE,
	'HPP opname berasal dari inventory cost sistem dan tidak dapat diedit.');
$assert(strpos($controller, "'counted_qty' => (int) \$counted_qty") !== FALSE &&
	strpos($controller, "\$row[2]") === FALSE &&
	strpos($view, 'payload.push([data[i][0], data[i][quantityColumn]])') !== FALSE,
	'Penyimpanan draft hanya menerima qty fisik, bukan perubahan HPP dari browser.');
$assert(strpos($controller, 'HPP sistem belum tersedia') !== FALSE &&
	strpos($controller, "->update('inventory_costs'") === FALSE,
	'Finalisasi memakai HPP sistem dan tidak menimpa HPP dengan nilai opname.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
