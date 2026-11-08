<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Kasir.php';

$controller = (new ReflectionClass('Kasir'))->newInstanceWithoutConstructor();

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

// 1. Uji _clean_cart_items
$clean_items_method = new ReflectionMethod('Kasir', '_clean_cart_items');
$clean_items_method->setAccessible(TRUE);

$raw_items = array(
	array('barcode' => '8991234567890', 'qty' => 2),
	array('barcode' => '8999876543210', 'qty' => 1)
);

$cleaned = $clean_items_method->invoke($controller, $raw_items);
$assert(is_array($cleaned) && count($cleaned) === 2, 'Membersihkan dan memvalidasi keranjang belanja.');
$assert($cleaned[0]['barcode'] === '8991234567890' && $cleaned[0]['qty'] === 2, 'Kuantitas dan barcode item tervalidasi.');

// 2. Uji _checkout_subtotal dengan resolved items
$subtotal_method = new ReflectionMethod('Kasir', '_checkout_subtotal');
$subtotal_method->setAccessible(TRUE);

$mock_product1 = (object) array('id' => 1, 'barcode' => '8991234567890', 'nama' => 'Beras 5kg', 'harga' => 65000);
$mock_product2 = (object) array('id' => 2, 'barcode' => '8999876543210', 'nama' => 'Minyak Goreng 2L', 'harga' => 34000);

$resolved_items = array(
	array('product' => $mock_product1, 'qty' => 2, 'unbox_actions' => array()),
	array('product' => $mock_product2, 'qty' => 3, 'unbox_actions' => array())
);

$total = $subtotal_method->invoke($controller, $resolved_items);
$expected_total = (65000 * 2) + (34000 * 3); // 130000 + 102000 = 232000
$assert($total === $expected_total, 'Menghitung subtotal tagihan checkout dengan tepat dari resolved items.');

// 3. Uji pemetaan akun jurnal pembayaran
$payment_account_method = new ReflectionMethod('Kasir', '_payment_account');
$payment_account_method->setAccessible(TRUE);

$assert($payment_account_method->invoke(null, 'tunai') === '1101', 'Metode tunai dipetakan ke akun 1101 (Kas Kasir).');
$assert($payment_account_method->invoke(null, 'cash') === '1101', 'Metode cash dipetakan ke akun 1101 (Kas Kasir).');
$assert($payment_account_method->invoke(null, 'potong_gaji') === '1103', 'Metode potong_gaji dipetakan ke akun 1103 (Piutang Anggota).');
$assert($payment_account_method->invoke(null, 'qris') === '1102', 'Metode QRIS dipetakan ke akun 1102 (Kas Bank).');
$assert($payment_account_method->invoke(null, 'edc') === '1102', 'Metode EDC dipetakan ke akun 1102 (Kas Bank).');
$assert($payment_account_method->invoke(null, 'transfer') === '1102', 'Metode transfer dipetakan ke akun 1102 (Kas Bank).');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
