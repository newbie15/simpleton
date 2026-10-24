<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Purchasing.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Management_payments.php';

$base = dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'main';
$purchasing = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Purchasing.php');
$payments = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Management_payments.php');
$petty = file_get_contents($base.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Petty_cash.php');
$view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'purchasing.php');
$payment_view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'petty_cash_new.php');
$management_payment_view = file_get_contents($base.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'management_payments.php');
$adhoc_search = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'purchasing'.DIRECTORY_SEPARATOR.'adhoc_product_search.js');
$migration = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'purchasing_workflow.sql');
$payment_status_view = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'purchasing_payment_status.sql');

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($migration, "enum('open','closed','cancelled')") !== FALSE &&
	strpos($migration, '`close_reason`') !== FALSE,
	'Migration adds PO lifecycle status and close audit fields.');
$assert(strpos($purchasing, "'status' => 'closed'") !== FALSE &&
	strpos($purchasing, "SELECT id,status FROM purchase_orders WHERE id = ? FOR UPDATE") !== FALSE,
	'PO close is row-locked and records closed status.');
$assert(strpos($purchasing, 'if (!$locked_order || $locked_order->status !== \'open\')') !== FALSE,
	'Receipt and PO edits reject closed orders under lock.');
$assert(strpos($purchasing, "'is_adhoc' => 1") !== FALSE &&
	strpos($purchasing, '\'authorized_by\' => (string) $this->ion_auth->get_user_id()') !== FALSE,
	'Ad-hoc receipt lines are marked and retain authorizing user audit.');
$assert(strpos($view, 'Barang di luar PO') !== FALSE && strpos($view, 'name="adhoc[0][barcode]"') !== FALSE,
	'Receipt UI has a distinct ad-hoc line section.');
$assert(strpos($view, 'data-product-search-url=') !== FALSE && strpos($view, 'data-adhoc-search') !== FALSE &&
	strpos($view, 'data-adhoc-barcode') !== FALSE && strpos($purchasing, 'public function product_search()') !== FALSE,
	'Ad-hoc receipt rows expose scan/search inputs backed by product search.');
$assert(strpos($purchasing, "\$data['js_file'] = array(") !== FALSE &&
	strpos($purchasing, 'adhoc_product_search.js?v=') !== FALSE,
	'Receive page loads the search script through the welcome view asset slot.');
$assert(strpos($adhoc_search, "event.key !== 'Enter'") !== FALSE &&
	strpos($adhoc_search, 'chooseProduct(input, exact || products[0])') !== FALSE &&
	strpos($adhoc_search, "parts.barcode.value = term") !== FALSE,
	'Ad-hoc search supports Enter selection and manual new-barcode entry.');
$assert(strpos($payments, 'cash_tx.transaction_type = \'supplier_payment\'') !== FALSE &&
	strpos($payments, 'outstanding_after_requests') !== FALSE &&
	strpos($payments, 'main/petty_cash/approve/') === FALSE,
	'Management queue filters existing supplier payments and calculates outstanding balance.');
$assert(strpos($payments, 'cash_tx.proof_filename') !== FALSE && strpos($management_payment_view, '$payment->proof_filename') !== FALSE,
	'Management payment rows include the proof filename used by the view.');
$assert(strpos($payment_status_view, "payment.status = 'posted'") !== FALSE &&
	strpos($payment_status_view, "payment.status = 'pending'") !== FALSE &&
	strpos($payment_status_view, "THEN 'Lunas'") !== FALSE &&
	strpos($payment_status_view, "ELSE 'Belum dibayar'") !== FALSE,
	'PO payment status distinguishes posted payments from pending requests.');
$assert(strpos($purchasing, "'pending_payment_amount'") !== FALSE &&
	strpos($purchasing, "'pending_payment_amount', array(\$this, '_callback_money')") !== FALSE,
	'PO list shows pending payment amounts separately from paid and outstanding amounts.');
$assert(strpos($payment_view, 'main/petty_cash/approve/') !== FALSE &&
	strpos($petty, 'PETTY_CASH') !== FALSE,
	'Payment approvals reuse the existing petty-cash journal handler.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);