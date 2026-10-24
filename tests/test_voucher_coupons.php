<?php

require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'third_party'.DIRECTORY_SEPARATOR.'PHPExcel.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Voucher.php';

class VoucherCouponTestResult
{
	private $rows;
	public function __construct($rows) { $this->rows = $rows; }
	public function result() { return $this->rows; }
	public function row() { return isset($this->rows[0]) ? $this->rows[0] : NULL; }
}

class VoucherCouponTestDatabase
{
	public $existing = array();
	public $quota = 100;
	public function select($columns) { return $this; }
	public function where_in($column, $values) { return $this; }
	public function where($column, $value) { return $this; }
	public function get($table) { return new VoucherCouponTestResult($this->existing); }
	public function count_all_results($table) { return 0; }
	public function query($sql, $params) { return new VoucherCouponTestResult(array((object) array('total' => 0))); }
	public function get_where($table, $where) {
		return new VoucherCouponTestResult(array((object) array('kuota' => $this->quota)));
	}
}

$controller = (new ReflectionClass('Voucher'))->newInstanceWithoutConstructor();
$controller->db = new VoucherCouponTestDatabase();
$validate = new ReflectionMethod('Voucher', '_validate_coupon_import_rows');
$validate->setAccessible(TRUE);
$check = function ($rows) use ($validate, $controller) {
	return $validate->invoke($controller, 1, $rows);
};
$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$valid_rows = array(
	array('row' => 2, 'serial_code' => 'COOP-001', 'assigned_npk' => '154481'),
	array('row' => 3, 'serial_code' => 'COOP-002', 'assigned_npk' => '')
);
$assert(count($check($valid_rows)) === 0, 'Accepts unique serials with optional NPK assignments.');
$assert(count($check(array(
	array('row' => 2, 'serial_code' => 'COOP-001', 'assigned_npk' => ''),
	array('row' => 3, 'serial_code' => 'COOP-001', 'assigned_npk' => '')
))) > 0, 'Rejects duplicate serials in one workbook.');
$assert(count($check(array(
	array('row' => 2, 'serial_code' => 'COUPON#1', 'assigned_npk' => '154481')
))) > 0, 'Rejects invalid serial characters.');
$assert(count($check(array(
	array('row' => 2, 'serial_code' => 'COOP-003', 'assigned_npk' => 'NPK 1')
))) > 0, 'Rejects invalid NPK characters.');

$controller->db->existing = array((object) array('serial_code' => 'COOP-001'));
$assert(count($check(array(
	array('row' => 2, 'serial_code' => 'COOP-001', 'assigned_npk' => '')
))) > 0, 'Rejects serials already stored in the database.');

$controller->db->existing = array();
$controller->db->quota = 1;
$assert(count($check($valid_rows)) > 0, 'Rejects import batches exceeding the voucher quota.');

$validate_voucher_import = new ReflectionMethod('Voucher', '_validate_voucher_import_rows');
$validate_voucher_import->setAccessible(TRUE);
$parse_expiry = new ReflectionMethod('Voucher', '_parse_import_expiry');
$parse_expiry->setAccessible(TRUE);
$date_only_serial = PHPExcel_Shared_Date::FormattedPHPToExcel(2026, 12, 31);
$assert($parse_expiry->invoke($controller, $date_only_serial) === '2026-12-31 23:59:59',
	'Interprets an Excel date-only serial as valid through 23:59:59 without a timezone shift.');
$datetime_serial = PHPExcel_Shared_Date::FormattedPHPToExcel(2026, 12, 31, 16, 30, 0);
$assert($parse_expiry->invoke($controller, $datetime_serial) === '2026-12-31 16:30:00',
	'Preserves the wall-clock time in an Excel datetime serial.');
$expiry = date('Y-m-d', strtotime('+3 days')).' 23:59:59';
$voucher_rows = array(array(
	'row' => 2,
	'name' => 'Voucher Anggota',
	'assigned_npk' => '1544811',
	'amount' => '150000',
	'expiry' => $expiry,
	'serial_code' => '1544811-150000'
));
$assert(count($validate_voucher_import->invoke($controller, $voucher_rows)) === 0,
	'Accepts a named, nominal, NPK-assigned voucher with a future expiry.');
$assert(count($validate_voucher_import->invoke($controller, array_merge($voucher_rows, $voucher_rows))) > 0,
	'Rejects duplicate NPK-nominal voucher codes in one workbook.');
$invalid_amount_rows = $voucher_rows;
$invalid_amount_rows[0]['amount'] = '150000.50';
$assert(count($validate_voucher_import->invoke($controller, $invalid_amount_rows)) > 0,
	'Rejects fractional nominal amounts for member vouchers.');
$expired_rows = $voucher_rows;
$expired_rows[0]['expiry'] = date('Y-m-d H:i:s', strtotime('-1 day'));
$assert(count($validate_voucher_import->invoke($controller, $expired_rows)) > 0,
	'Rejects expired vouchers during preview and confirmation.');
$large_voucher_rows = array();
for ($index = 1; $index <= 1001; $index++) {
	$npk = (string) (1000000 + $index);
	$large_voucher_rows[] = array(
		'row' => $index + 1,
		'name' => 'Voucher '.$index,
		'assigned_npk' => $npk,
		'amount' => '150000',
		'expiry' => $expiry,
		'serial_code' => $npk.'-150000'
	);
}
$assert(count($validate_voucher_import->invoke($controller, $large_voucher_rows)) === 0,
	'Accepts a valid batch larger than the former 1,000 voucher limit.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);