<?php

require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/application/modules/main/controllers/Data_update.php';

class MockImportCell
{
	private $value;

	public function __construct($value)
	{
		$this->value = $value;
	}

	public function getFormattedValue()
	{
		return (string) $this->value;
	}

	public function getCalculatedValue()
	{
		return $this->value;
	}
}

class MockImportSheet
{
	private $cells;
	private $last_row;

	public function __construct($rows)
	{
		$this->cells = $rows;
		$this->last_row = count($rows);
	}

	public function getCellByColumnAndRow($column, $row)
	{
		$value = isset($this->cells[$row - 1][$column])
			? $this->cells[$row - 1][$column] : '';
		return new MockImportCell($value);
	}

	public function getHighestDataRow()
	{
		return $this->last_row;
	}
}

function data_update_check($condition, $message)
{
	echo ($condition ? 'PASS ' : 'FAIL ').$message.PHP_EOL;
	return $condition ? 0 : 1;
}

$controller = (new ReflectionClass('Data_update'))->newInstanceWithoutConstructor();
$parse_method = new ReflectionMethod('Data_update', '_parse_import_sheet');
$parse_method->setAccessible(TRUE);
$failures = 0;

$employee_sheet = new MockImportSheet(array(
	array('Npk', 'Nama', 'Bagian', 'Limit belanja', 'Join date'),
	array('1544811', 'Fajar Rukmo', 'STAF', 1000000, '01/09/2015')
));
$employee_result = $parse_method->invoke($controller, $employee_sheet, 'karyawan');
$failures += data_update_check(
	count($employee_result['rows']) === 1 &&
	$employee_result['rows'][0]['join_date'] === '2015-09-01' &&
	$employee_result['rows'][0]['npk'] === '1544811',
	'Format karyawan pada gambar contoh dapat dibaca dan tanggal dikonversi'
);

$duplicate_employee_sheet = new MockImportSheet(array(
	array('Npk', 'Nama', 'Bagian', 'Limit belanja', 'Join date'),
	array('1544811', 'Fajar Rukmo', 'STAF', 1000000, '01/09/2015'),
	array('1544811', 'Fajar Rukmo', 'STAF', 1000000, '01/09/2015')
));
$duplicate_result = $parse_method->invoke($controller, $duplicate_employee_sheet, 'karyawan');
$failures += data_update_check(
	count($duplicate_result['errors']) === 1 && count($duplicate_result['rows']) === 1,
	'NPK duplikat menggagalkan validasi sebelum data disimpan'
);

$bad_stock_sheet = new MockImportSheet(array(
	array('Barcode', 'Nama', 'Jumlah', 'Harga', 'Distributor', 'Ppn'),
	array('1', 'Produk', 'jumlah salah', 38000, 'Toko', 0.1)
));
$bad_stock_result = $parse_method->invoke($controller, $bad_stock_sheet, 'stok');
$failures += data_update_check(
	count($bad_stock_result['errors']) === 1 && empty($bad_stock_result['rows']),
	'Nilai stok yang tidak valid ditolak sebelum data diganti'
);

$date_method = new ReflectionMethod('Data_update', '_parse_employee_join_date');
$date_method->setAccessible(TRUE);
$failures += data_update_check(
	$date_method->invoke($controller, 'tanggal tidak valid') === FALSE,
	'Tanggal masuk yang tidak valid ditolak'
);

$controller_source = file_get_contents(dirname(__DIR__).'/application/modules/main/controllers/Data_update.php');
$cashier_source = file_get_contents(dirname(__DIR__).'/application/modules/main/controllers/Kasir.php');
$view_source = file_get_contents(dirname(__DIR__).'/application/modules/main/views/data_update.php');
$failures += data_update_check(
	strpos($controller_source, "empty_table('stok')") !== FALSE &&
	!preg_match('/TRUNCATE\s+TABLE/i', $controller_source) &&
	strpos($controller_source, 'trans_rollback()') !== FALSE &&
	!preg_match('/TRUNCATE\s+TABLE/i', $cashier_source) &&
	strpos($cashier_source, 'Importer lama dinonaktifkan') !== FALSE,
	'Update stok memakai transaksi dan tidak memakai TRUNCATE'
);
$has_examples = strpos($controller_source, "assets/misc/samplestok.png") !== FALSE &&
	strpos($controller_source, "assets/misc/samplekaryawan.png") !== FALSE;
$failures += data_update_check(
	$has_examples &&
	strpos($view_source, '$sample_stock_url') !== FALSE &&
	strpos($view_source, '$sample_employee_url') !== FALSE &&
	strpos($view_source, 'progress-bar') !== FALSE,
	'Gambar contoh format dan progressbar tetap tersedia'
);

echo ($failures === 0 ? 'Semua pemeriksaan lulus.' : $failures.' pemeriksaan gagal.').PHP_EOL;
exit($failures === 0 ? 0 : 1);
