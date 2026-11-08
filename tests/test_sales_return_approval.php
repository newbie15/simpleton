<?php

require __DIR__.'/bootstrap.php';
require dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Sales_return.php';

$source = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Sales_return.php');
$migration = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'sql'.DIRECTORY_SEPARATOR.'sales_return_approval.sql');
$view = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'sales_return.php');
$ajax = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.
	'modules'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'Ajax.php');
$save_start = strpos($source, 'public function save()');
$approve_start = strpos($source, 'public function approve(');
$reject_start = strpos($source, 'public function reject(');
$save = substr($source, $save_start, $approve_start - $save_start);
$approve = substr($source, $approve_start, $reject_start - $approve_start);
$reject = substr($source, $reject_start);

$tests = array();
$assert = function ($condition, $message) use (&$tests) {
	$tests[] = array((bool) $condition, $message);
};

$assert(strpos($save, "'status' => 'pending'") !== FALSE,
	'Submitting a return creates a pending request.');
$assert(strpos($save, 'post_journal') === FALSE && strpos($save, "->update('stok'") === FALSE,
	'Submission does not post a journal or mutate stock.');
$assert(strpos($save, "return_header.status IN ('pending', 'approved', 'completed')") !== FALSE,
	'Pending returns reserve the remaining return quantity.');
$assert(strpos($approve, "return->status !== 'pending'") !== FALSE &&
	strpos($approve, '->update(\'stok\'') !== FALSE && strpos($approve, 'post_journal') !== FALSE,
	'Only the pending-return approval flow changes stock and posts the journal.');
$assert(strpos($approve, "'approved_by'") !== FALSE && strpos($approve, "'approved_at'") !== FALSE,
	'Approval records the manager and timestamp.');
$assert(strpos($reject, "'status' => 'rejected'") !== FALSE &&
	strpos($reject, "'rejection_reason' => $reason") !== FALSE,
	'Rejection records the decision and required reason.');
$assert(strpos($migration, "enum('pending','approved','rejected','completed','cancelled')") !== FALSE &&
	strpos($migration, 'rejection_reason') !== FALSE,
	'Migration adds approval states and decision audit fields.');
$assert(strpos($source, 'Setujui') !== FALSE && strpos($source, 'Tolak') !== FALSE &&
	strpos($source, 'Menunggu manager') !== FALSE && strpos($view, 'sales-return-history') !== FALSE,
	'Return controller exposes manager decisions and view renders the return history.');
$assert(strpos($ajax, "return_header.status IN ('approved', 'completed')") !== FALSE &&
	strpos($ajax, 'AS tertagih') !== FALSE &&
	strpos($ajax, 'AS retur_disetujui') !== FALSE,
	'Personal and monthly billing reports deduct approved returns and show net billable totals.');
$purchase_history_start = strpos($source, 'private function _purchase_history(');
$purchase_history_end = strpos($source, 'public function returns_data()', $purchase_history_start);
$purchase_history = substr($source, $purchase_history_start, $purchase_history_end - $purchase_history_start);
$assert(strpos($purchase_history, 'WHERE sale.id_karyawan = ?') !== FALSE &&
	strpos($purchase_history, 'GROUP BY sale.nota') !== FALSE &&
	strpos($purchase_history, 'LIMIT 50') !== FALSE &&
	strpos($purchase_history, 'AS qty_returned') !== FALSE,
	'NPK purchase history lists recent receipts and includes quantities already reserved for return.');
$assert(strpos($view, 'name="search_type"') !== FALSE &&
	strpos($view, 'value="npk"') !== FALSE &&
	strpos($view, 'id="npk-search-value"') !== FALSE &&
	strpos($view, 'Lihat isi belanja nota ini') !== FALSE &&
	strpos($view, 'Pilih nota ini untuk retur') !== FALSE,
	'The return page lets staff find a member by NPK, inspect receipt contents, and select a receipt.');
$assert(strpos($source, "CONCAT(nama, '-', npk) = ?") !== FALSE &&
	strpos($view, 'data-npk-source=') !== FALSE &&
	strpos($source, "jquery.easy-autocomplete.min.js") !== FALSE,
	'NPK lookup supports the same name-or-NPK suggestions used on the sales page.');
$return_script = file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.
	'purchasing'.DIRECTORY_SEPARATOR.'sales_return.js');
$assert(strpos($return_script, 'deferLoading: 0') === FALSE &&
	strpos($return_script, "url: table.data('source')") !== FALSE,
	'Return history loads its records when the page opens, independently of receipt search.');
$assert(strpos($source, "input->get('nota'") === FALSE &&
	strpos($source, "redirect('main/sales_return?nota=") === FALSE,
	'Receipt details only open from an explicit search and do not reopen automatically after a return action.');
$assert(strpos($view, "if (\$sale && \$search_type === 'nota' && \$search_value !== ''") !== FALSE &&
	strpos($view, '(string) $sale->nota === $search_value') !== FALSE,
	'Receipt details are rendered only when the submitted search matches the receipt number.');
$assert(strpos($save, 'Barang retur tidak berasal dari nota yang dipilih.') !== FALSE &&
	strpos($save, 'melebihi sisa yang dapat diretur') !== FALSE,
	'Selected receipt returns remain limited to items and quantities on that receipt.');

$failures = 0;
foreach ($tests as $test) {
	echo ($test[0] ? 'PASS' : 'FAIL').' '.$test[1].PHP_EOL;
	if (!$test[0]) {
		$failures++;
	}
}
echo count($tests).' test(s), '.$failures.' failure(s).'.PHP_EOL;
exit($failures === 0 ? 0 : 1);