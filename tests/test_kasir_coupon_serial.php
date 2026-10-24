<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Kasir.php';

class KasirCouponTestResult
{
	private $value;
	public function __construct($value) { $this->value = $value; }
	public function row() { return $this->value; }
}

class KasirCouponTestDatabase
{
	public $coupon;
	public $voucher = array(
		'id' => 7,
		'tipe' => 'diskon_nominal',
		'nilai' => 5000,
		'min_belanja' => 10000,
		'max_diskon' => NULL,
		'kuota' => 100,
		'terpakai' => 0
	);
	public function table_exists($table) { return $table === 'voucher_coupons'; }
	public function get_where($table, $where) { return new KasirCouponTestResult($this->coupon); }
	public function query($sql, $params) { return new KasirCouponTestResult((object) $this->voucher); }
}

$controller = (new ReflectionClass('Kasir'))->newInstanceWithoutConstructor();
$controller->db = new KasirCouponTestDatabase();
$apply = new ReflectionMethod('Kasir', '_apply_voucher');
$apply->setAccessible(TRUE);
$redeem = function ($npk) use ($apply, $controller) {
	return $apply->invoke($controller, 'COOP-001', 20000, $npk);
};
$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$controller->db->coupon = (object) array(
	'id' => 12,
	'voucher_id' => 7,
	'status' => 'available',
	'assigned_npk' => '154481'
);
$valid = $redeem('154481');
$assert($valid['valid'] && $valid['voucher_id'] === 7 && $valid['coupon_id'] === 12 && $valid['diskon'] === 5000.0,
	'Accepts an available coupon assigned to the matching NPK.');
$wrong_npk = $redeem('999999');
$assert(!$wrong_npk['valid'] && strpos($wrong_npk['message'], 'NPK lain') !== FALSE,
	'Rejects an assigned coupon when the buyer NPK differs.');
$controller->db->coupon->status = 'used';
$used = $redeem('154481');
$assert(!$used['valid'] && strpos($used['message'], 'sudah dipakai') !== FALSE,
	'Rejects a coupon serial that is already used.');

$format_receipt_number = new ReflectionMethod('Kasir', '_format_receipt_number');
$format_receipt_number->setAccessible(TRUE);
$assert($format_receipt_number->invoke($controller, '1544811', '2026-10-24', 1) === '1544811-261024-001',
	'Formats receipt numbers as NPK, YYMMDD, and a padded daily sequence.');
$assert($format_receipt_number->invoke($controller, '1544811', '2026-10-24', 1000) === '1544811-261024-1000',
	'Keeps sequence values unique after the three-digit display width is exceeded.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);