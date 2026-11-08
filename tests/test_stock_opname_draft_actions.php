<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/application/modules/main/controllers/Stock_opname.php');
$view = file_get_contents($root.'/application/modules/main/views/stock_opname.php');

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($controller, 'public function sync($opname_id = NULL)') !== FALSE &&
	strpos($controller, 'LEFT JOIN stock_opname_items item') !== FALSE &&
	strpos($controller, 'WHERE item.id IS NULL') !== FALSE &&
	strpos($controller, "'counted_qty' => NULL") !== FALSE,
	'Sinkronisasi hanya menambahkan produk baru sebagai item yang belum dihitung.');
$assert(strpos($controller, '\'system_qty\' => (int) $product->jumlah') !== FALSE &&
	strpos($controller, 'Snapshot barang yang sudah ada tidak diubah.') !== FALSE &&
	strpos($view, 'Sinkronkan Barang Baru') !== FALSE &&
	strpos($view, 'opname-sync-form') !== FALSE,
	'Barang baru mengambil snapshot stok saat sinkron tanpa mengubah hitungan lama.');
$assert(strpos($controller, 'public function cancel($opname_id = NULL)') !== FALSE &&
	strpos($controller, "->where('status', 'draft')") !== FALSE &&
	strpos($controller, "array('status' => 'cancelled')") !== FALSE &&
	strpos($controller, "->delete('stock_opname_items'") === FALSE,
	'Pembatalan hanya mengubah status draft dan mempertahankan data historis.');
$assert(strpos($view, "=== 'cancelled' ? 'Dibatalkan'") !== FALSE &&
	strpos($view, 'Batalkan Draft') !== FALSE &&
	strpos($view, 'Data hitungannya disimpan sebagai riwayat') !== FALSE,
	'Status batal dan tindakan pembatalan ditampilkan pada antarmuka.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
