<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'libraries'.DIRECTORY_SEPARATOR.'Sales_tax.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Kasir.php';

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$tax = (new ReflectionClass('Sales_tax'))->newInstanceWithoutConstructor();
$amounts_11 = $tax->inclusive_amounts(111, 11);
$assert($amounts_11['dpp'] === 100.0 && $amounts_11['ppn'] === 11.0,
	'Extracts DPP and PPN from a price that includes 11% VAT.');
$amounts_fractional = $tax->inclusive_amounts(1125, 12.5);
$assert($amounts_fractional['dpp'] === 1000.0 && $amounts_fractional['ppn'] === 125.0,
	'Uses the persisted global percentage when extracting VAT.');

$new_sale = (object) array(
	'harga' => 111,
	'qty' => 1,
	'jumlah' => 111,
	'ppn' => 0.11,
	'dpp_satuan' => 100,
	'ppn_satuan' => 11
);
$new_amounts = $tax->sale_amounts($new_sale);
$assert($new_amounts['dpp'] === 100.0 && $new_amounts['ppn'] === 11.0 &&
	$new_amounts['gross'] === 111.0,
	'Reads the explicit VAT-inclusive snapshot for new sales.');

$old_sale = (object) array('harga' => 100, 'qty' => 1, 'jumlah' => 100, 'ppn' => 0.11);
$old_amounts = $tax->sale_amounts($old_sale);
$assert($old_amounts['dpp'] === 100.0 && $old_amounts['ppn'] === 11.0 &&
	$old_amounts['gross'] === 111.0,
	'Preserves the historical tax-exclusive storage interpretation.');

$kasir = (new ReflectionClass('Kasir'))->newInstanceWithoutConstructor();
$clean = new ReflectionMethod('Kasir', '_clean_cart_items');
$clean->setAccessible(TRUE);
$without_client_tax = $clean->invoke($kasir, array(array('barcode' => 'A-1', 'qty' => 1, 'ppn' => 0)));
$with_forged_client_tax = $clean->invoke($kasir, array(array('barcode' => 'A-1', 'qty' => 1, 'ppn' => 0.99)));
$assert($without_client_tax === $with_forged_client_tax &&
	!array_key_exists('ppn', $with_forged_client_tax[0]),
	'Checkout ignores client-supplied VAT and uses the server setting.');

$subtotal = new ReflectionMethod('Kasir', '_checkout_subtotal');
$subtotal->setAccessible(TRUE);
$inclusive_total = $subtotal->invoke($kasir, array(array(
	'product' => (object) array('harga' => 111),
	'qty' => 1
)));
$assert($inclusive_total === 111,
	'Checkout treats the catalog price as gross and does not add VAT again.');

$source = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Kasir.php');
$receipt_view = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'pos_receipt_reprint.php');
$assert(strpos($source, "'dpp_satuan'") !== FALSE && strpos($source, "'ppn_satuan'") !== FALSE,
	'New POS sales persist DPP and PPN per unit for audit and returns.');
$assert(strpos($receipt_view, '<span>DPP</span>') !== FALSE &&
	strpos($receipt_view, '<span>PPN</span>') !== FALSE,
	'Receipt shows both DPP and VAT.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);
