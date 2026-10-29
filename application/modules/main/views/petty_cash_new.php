<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$fund_type_labels = array('cashier' => 'Kasir', 'main' => 'Kas utama', 'petty' => 'Petty cash');
$status_labels = array('pending' => 'Menunggu persetujuan', 'posted' => 'Sudah diposting', 'rejected' => 'Ditolak');
$transaction_type_labels = array(
	'funding' => 'Penambahan dana',
	'expense' => 'Pengeluaran',
	'supplier_payment' => 'Pembayaran supplier'
);
$format_rupiah = function ($amount) {
	return 'Rp '.number_format((float) $amount, 2, ',', '.');
};
$pending_count = 0;
foreach ($transactions as $transaction) {
	if ($transaction->status === 'pending') {
		$pending_count++;
	}
}
$total_balance = 0;
foreach ($funds as $fund) {
	if ((int) $fund->is_active === 1) {
		$total_balance += (float) $fund->balance;
	}
}
?>

<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>
<div class="alert alert-warning">
	<strong>Ruang kerja baru, data bersama:</strong> saldo, permintaan, dan persetujuan tetap memakai dana serta jurnal yang sama dengan halaman lama. Pengeluaran baru membentuk jurnal hanya setelah disetujui.
</div>

<div class="row">
	<div class="col-md-4 col-sm-6">
		<div class="small-box bg-aqua"><div class="inner"><h3><?php echo $format_rupiah($total_balance); ?></h3><p>Total saldo buku dana aktif</p></div><div class="icon"><i class="fa fa-money"></i></div></div>
	</div>
	<div class="col-md-4 col-sm-6">
		<div class="small-box bg-yellow"><div class="inner"><h3><?php echo number_format($pending_count); ?></h3><p>Permintaan menunggu (100 transaksi terbaru)</p></div><div class="icon"><i class="fa fa-clock-o"></i></div></div>
	</div>
	<div class="col-md-4 col-sm-6">
		<div class="small-box bg-green"><div class="inner"><h3><?php echo number_format(count($active_funds)); ?></h3><p>Dana aktif</p></div><div class="icon"><i class="fa fa-briefcase"></i></div></div>
	</div>
</div>

<ul class="nav nav-tabs" style="margin-bottom:15px;">
	<li<?php echo $tab === 'overview' ? ' class="active"' : ''; ?>><a href="<?php echo site_url('main/petty_cash_new?tab=overview'); ?>">Ringkasan dana</a></li>
	<li<?php echo $tab === 'register' ? ' class="active"' : ''; ?>><a href="<?php echo site_url('main/petty_cash_new?tab=register'); ?>">Buku kas</a></li>
	<li<?php echo $tab === 'requests' ? ' class="active"' : ''; ?>><a href="<?php echo site_url('main/petty_cash_new?tab=requests'); ?>">Transaksi & persetujuan</a></li>
	<li<?php echo $tab === 'reconcile' ? ' class="active"' : ''; ?>><a href="<?php echo site_url('main/petty_cash_new?tab=reconcile'); ?>">Tutup kas</a></li>
</ul>

<?php if ($tab === 'overview') { ?>
	<div class="box box-primary">
		<div class="box-header with-border">
			<h3 class="box-title">Dana kas</h3>
			<div class="box-tools pull-right"><a class="btn btn-xs btn-default" href="<?php echo site_url('main/petty_cash'); ?>">Buka tampilan lama</a></div>
		</div>
		<div class="box-body table-responsive">
			<table class="table table-hover">
				<thead><tr><th>Dana</th><th>Jenis</th><th>Pemegang</th><th class="text-right">Saldo buku</th><th class="text-right">Batas imprest</th><th>Status</th><th></th></tr></thead>
				<tbody>
				<?php foreach ($funds as $fund) { ?>
					<tr>
						<td><strong><?php echo html_escape($fund->fund_code); ?></strong><br><?php echo html_escape($fund->name); ?></td>
						<td><?php echo html_escape(isset($fund_type_labels[$fund->fund_type]) ? $fund_type_labels[$fund->fund_type] : $fund->fund_type); ?></td>
						<td><?php echo html_escape($fund->custodian_username ? $fund->custodian_username : 'Belum ditetapkan'); ?></td>
						<td class="text-right"><?php echo $format_rupiah($fund->balance); ?></td>
						<td class="text-right"><?php echo $fund->fund_type === 'petty' ? $format_rupiah($fund->imprest_limit) : '—'; ?></td>
						<td><?php echo (int) $fund->is_active === 1 ? '<span class="label label-success">Aktif</span>' : '<span class="label label-default">Nonaktif</span>'; ?></td>
						<td><?php if ((int) $fund->is_active === 1) { ?><a class="btn btn-xs btn-default" href="<?php echo site_url('main/petty_cash_new?tab=register&fund_id='.(int) $fund->id.'&report_date='.rawurlencode($report_date)); ?>">Buka buku</a><?php } ?></td>
					</tr>
				<?php } ?>
				<?php if (empty($funds)) { ?><tr><td colspan="7" class="text-muted">Belum ada dana. Pengurus dapat membuat dana baru pada tab transaksi.</td></tr><?php } ?>
				</tbody>
			</table>
			<p class="help-block">Kasir POS memakai akun 1101, kas utama 1106, dan petty cash 1107. Transfer antar-dana dicatat sebagai transaksi terpisah dan membentuk pasangan debit/kredit.</p>
		</div>
	</div>
	<div class="row">
		<div class="col-md-6">
			<div class="box box-info"><div class="box-header with-border"><h3 class="box-title">Mulai pencatatan</h3></div><div class="box-body">
				<ol>
					<li>Pilih atau buat dana petty cash dan tetapkan pemegangnya.</li>
					<li>Ajukan penambahan dana dari kas utama.</li>
					<li>Ajukan pengeluaran dengan bukti; penginput tidak dapat menyetujui permintaannya sendiri.</li>
					<li>Setujui transaksi sebagai approver sebelum menganggapnya sebagai jurnal resmi.</li>
				</ol>
				<a class="btn btn-primary" href="<?php echo site_url('main/petty_cash_new?tab=requests'); ?>">Buka transaksi & persetujuan</a>
			</div></div>
		</div>
		<div class="col-md-6">
			<div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Penutupan harian</h3></div><div class="box-body">
				<p>Bandingkan uang fisik dengan saldo sistem setiap akhir hari. Selisih dicatat sebagai rekonsiliasi; aplikasi tidak membuat jurnal penyesuaian otomatis.</p>
				<a class="btn btn-default" href="<?php echo site_url('main/petty_cash_new?tab=reconcile'); ?>">Buka tutup kas</a>
			</div></div>
		</div>
	</div>
<?php } elseif ($tab === 'register') { ?>
	<div class="box box-info">
		<div class="box-header with-border"><h3 class="box-title">Buku kas per dana</h3></div>
		<div class="box-body">
			<form class="form-inline" method="get" action="<?php echo site_url('main/petty_cash_new'); ?>">
				<input type="hidden" name="tab" value="register">
				<div class="form-group"><label for="new_fund_id">Dana</label>
					<select class="form-control" id="new_fund_id" name="fund_id" required>
						<?php foreach ($active_funds as $fund) { ?><option value="<?php echo (int) $fund->id; ?>" <?php echo $daily_fund && (int) $daily_fund->id === (int) $fund->id ? 'selected' : ''; ?>><?php echo html_escape($fund->fund_code.' — '.$fund->name); ?></option><?php } ?>
					</select>
				</div>
				<div class="form-group" style="margin-left:8px;"><label for="new_report_date">Tanggal</label><input class="form-control" type="date" id="new_report_date" name="report_date" value="<?php echo html_escape($report_date); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
				<button class="btn btn-primary" type="submit" style="margin-left:8px;">Tampilkan</button>
			</form>
			<?php if ($daily_fund) { ?>
			<div class="table-responsive" style="margin-top:15px;">
				<table class="table table-bordered table-striped">
					<thead><tr><th colspan="6"><?php echo html_escape($daily_fund->fund_code.' — '.$daily_fund->name.' · '.$report_date); ?></th></tr><tr><th>Jurnal</th><th>Referensi</th><th>Uraian</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th class="text-right">Saldo berjalan</th></tr></thead>
					<tbody>
						<tr class="active"><th colspan="5">Saldo awal</th><th class="text-right"><?php echo $format_rupiah($daily_opening); ?></th></tr>
						<?php foreach ($daily_rows as $row) { ?>
							<tr><td><?php echo html_escape($row->journal_number); ?></td><td><?php echo html_escape($row->transaction_number ? $row->transaction_number : $row->reference_type.':'.$row->reference_id); ?></td><td><?php echo html_escape($row->memo ? $row->memo : $row->description); ?><?php if ($row->counterparty) { ?><br><small><?php echo html_escape($row->counterparty); ?></small><?php } ?></td><td class="text-right"><?php echo (float) $row->debit > 0 ? $format_rupiah($row->debit) : '—'; ?></td><td class="text-right"><?php echo (float) $row->credit > 0 ? $format_rupiah($row->credit) : '—'; ?></td><td class="text-right"><?php echo $format_rupiah($row->running_balance); ?></td></tr>
						<?php } ?>
						<?php if (empty($daily_rows)) { ?><tr><td colspan="6" class="text-muted">Tidak ada mutasi pada tanggal ini.</td></tr><?php } ?>
					</tbody>
					<tfoot><tr><th colspan="5">Saldo akhir sistem</th><th class="text-right"><?php echo $format_rupiah($daily_balance); ?></th></tr></tfoot>
				</table>
			</div>
			<?php } else { ?><p class="text-muted" style="margin-top:15px;">Buat atau aktifkan dana kas untuk membuka buku kas.</p><?php } ?>
		</div>
	</div>
<?php } elseif ($tab === 'requests') { ?>
	<div class="row">
		<div class="col-md-6">
			<div class="box box-success"><div class="box-header with-border"><h3 class="box-title">Ajukan transaksi</h3></div><div class="box-body">
				<form method="post" enctype="multipart/form-data" action="<?php echo site_url('main/petty_cash/submit_transaction'); ?>" id="new-cash-transaction">
					<input type="hidden" name="return_to" value="main/petty_cash_new">
					<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
					<div class="form-group"><label for="new_cash_fund">Dana</label>
						<select class="form-control" id="new_cash_fund" name="fund_id" required><option value="">Pilih dana</option><?php foreach ($active_funds as $fund) { ?><option value="<?php echo (int) $fund->id; ?>" data-type="<?php echo html_escape($fund->fund_type); ?>"><?php echo html_escape($fund->fund_code.' — '.$fund->name); ?></option><?php } ?></select>
					</div>
					<div class="form-group"><label for="new_transaction_type">Jenis</label><select class="form-control" id="new_transaction_type" name="transaction_type" required><option value="expense">Pengeluaran petty cash</option><option value="funding">Penambahan / transfer masuk</option><option value="supplier_payment">Pembayaran hutang supplier</option></select></div>
					<div class="form-group"><label for="new_transaction_date">Tanggal</label><input class="form-control" id="new_transaction_date" type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
					<div class="form-group"><label for="new_cash_amount">Nominal (Rp)</label><input class="form-control" id="new_cash_amount" type="number" name="amount" min="0.01" max="9999999999999.99" step="0.01" required></div>
					<div class="form-group" id="new_expense_group"><label for="new_expense_account">Akun beban</label><select class="form-control" id="new_expense_account" name="expense_account_id"><option value="">Pilih akun</option><?php foreach ($expense_accounts as $account) { ?><option value="<?php echo (int) $account->id; ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option><?php } ?></select></div>
					<div class="form-group" id="new_funding_group"><label for="new_funding_source">Sumber dana</label><select class="form-control" id="new_funding_source" name="funding_source_account_id"><option value="">Pilih akun sumber</option><?php foreach ($funding_accounts as $account) { ?><option value="<?php echo (int) $account->id; ?>" data-code="<?php echo html_escape($account->code); ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option><?php } ?></select><p class="help-block">Kas utama dapat diisi dari kasir atau bank; petty cash diisi dari kas utama.</p></div>
					<div class="form-group" id="new_receipt_group"><label for="new_receipt">Hutang penerimaan supplier</label><select class="form-control" id="new_receipt" name="goods_receipt_id"><option value="">Pilih penerimaan</option><?php foreach ($supplier_receipts as $receipt) { ?><option value="<?php echo (int) $receipt->id; ?>" data-supplier="<?php echo html_escape($receipt->supplier_name); ?>"><?php echo html_escape($receipt->receipt_number.' — '.$receipt->supplier_name.' — sisa '.$format_rupiah($receipt->outstanding)); ?></option><?php } ?></select></div>
					<div class="form-group"><label for="new_counterparty">Penerima / pihak lain</label><input class="form-control" id="new_counterparty" type="text" name="counterparty" maxlength="128"></div>
					<div class="form-group"><label for="new_description">Keterangan</label><textarea class="form-control" id="new_description" name="description" maxlength="500" required></textarea></div>
					<div class="form-group"><label for="new_evidence_reference">Nomor / referensi bukti</label><input class="form-control" id="new_evidence_reference" type="text" name="evidence_reference" maxlength="128" required></div>
					<div class="form-group"><label for="new_proof_file">Lampiran bukti (PDF/JPG/PNG, maks. 5 MB)</label><input class="form-control" id="new_proof_file" type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png" required></div>
					<button class="btn btn-success" type="submit">Ajukan untuk persetujuan</button>
				</form>
			</div></div>
			<?php if ($is_approver) { ?>
			<div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Buat dana kas</h3></div><div class="box-body">
				<form method="post" action="<?php echo site_url('main/petty_cash/create_fund'); ?>">
					<input type="hidden" name="return_to" value="main/petty_cash_new">
					<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
					<div class="form-group"><label for="new_fund_code">Kode unik</label><input class="form-control" id="new_fund_code" type="text" name="fund_code" maxlength="32" pattern="[A-Za-z0-9_-]{2,32}" required></div>
					<div class="form-group"><label for="new_fund_name">Nama / lokasi</label><input class="form-control" id="new_fund_name" type="text" name="name" maxlength="100" required></div>
					<div class="form-group"><label for="new_fund_type">Jenis</label><select class="form-control" id="new_fund_type" name="fund_type" required><option value="petty">Petty cash (akun 1107)</option><option value="main">Kas utama (akun 1106)</option></select></div>
					<div class="form-group"><label for="new_custodian">Pemegang (opsional)</label><select class="form-control" id="new_custodian" name="custodian_user_id"><option value="">Belum ditetapkan</option><?php foreach ($users as $user) { ?><option value="<?php echo html_escape($user->id); ?>"><?php echo html_escape($user->username.' — '.trim($user->first_name.' '.$user->last_name)); ?></option><?php } ?></select></div>
					<div class="form-group" id="new_imprest_group"><label for="new_imprest_limit">Batas dana tetap (Rp)</label><input class="form-control" id="new_imprest_limit" type="number" name="imprest_limit" min="0.01" max="9999999999999.99" step="0.01" value="1000000.00"></div>
					<button class="btn btn-default" type="submit">Buat dana</button>
				</form>
			</div></div>
			<?php } ?>
		</div>
		<div class="col-md-6">
			<div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Permintaan terbaru</h3></div><div class="box-body">
				<p class="help-block">Penginput tidak dapat menyetujui atau menolak permintaannya sendiri. Permintaan ditolak tidak membentuk jurnal.</p>
				<?php foreach ($transactions as $transaction) { ?>
					<div class="panel panel-<?php echo $transaction->status === 'pending' ? 'warning' : ($transaction->status === 'posted' ? 'success' : 'default'); ?>">
						<div class="panel-heading"><strong><?php echo html_escape($transaction->transaction_number); ?></strong><span class="pull-right"><?php echo html_escape(isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : $transaction->status); ?></span></div>
						<div class="panel-body">
							<strong><?php echo html_escape($transaction->fund_code.' — '.$transaction->fund_name); ?></strong><br>
							<?php echo html_escape($transaction->transaction_date.' · '.(isset($transaction_type_labels[$transaction->transaction_type]) ? $transaction_type_labels[$transaction->transaction_type] : $transaction->transaction_type)); ?>
							<span class="pull-right"><?php echo $format_rupiah($transaction->amount); ?></span><br>
							<?php echo html_escape($transaction->description); ?><br>
							<small>Oleh <?php echo html_escape($transaction->creator_name); ?> · Bukti <?php echo html_escape($transaction->evidence_reference); ?><?php if ($transaction->proof_filename) { ?> · <a href="<?php echo site_url('main/petty_cash/proof/'.(int) $transaction->id); ?>" class="js-proof-preview">Lihat lampiran</a><?php } ?></small>
							<?php if ($transaction->status === 'rejected' && $transaction->rejection_reason) { ?><p class="text-danger"><?php echo html_escape($transaction->rejection_reason); ?></p><?php } ?>
							<?php if ($is_approver && $transaction->status === 'pending' && (string) $transaction->created_by !== (string) $this->ion_auth->get_user_id()) { ?>
								<hr>
								<form method="post" action="<?php echo site_url('main/petty_cash/approve/'.(int) $transaction->id); ?>" style="display:inline-block;margin-right:5px;">
									<input type="hidden" name="return_to" value="main/petty_cash_new"><input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>"><button class="btn btn-xs btn-success" type="submit" onclick="return confirm('Setujui dan posting jurnal transaksi?');">Setujui & posting</button>
								</form>
								<form method="post" action="<?php echo site_url('main/petty_cash/reject/'.(int) $transaction->id); ?>">
									<input type="hidden" name="return_to" value="main/petty_cash_new"><input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>"><div class="input-group input-group-sm"><input class="form-control" type="text" name="rejection_reason" maxlength="500" placeholder="Alasan penolakan" required><span class="input-group-btn"><button class="btn btn-danger" type="submit">Tolak</button></span></div>
								</form>
							<?php } ?>
						</div>
					</div>
				<?php } ?>
				<?php if (empty($transactions)) { ?><p class="text-muted">Belum ada transaksi.</p><?php } ?>
				<?php if (count($transactions) === 100) { ?><p class="text-warning">Menampilkan 100 transaksi terbaru.</p><?php } ?>
			</div></div>
		</div>
	</div>
<?php } else { ?>
	<div class="row">
		<div class="col-md-5">
			<div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Sahkan tutup kas</h3></div><div class="box-body">
				<?php if ($is_approver) { ?>
				<form method="post" action="<?php echo site_url('main/petty_cash/close_day'); ?>">
					<input type="hidden" name="return_to" value="main/petty_cash_new">
					<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
					<div class="form-group"><label for="new_closing_fund">Dana</label><select class="form-control" id="new_closing_fund" name="fund_id" required><option value="">Pilih dana</option><?php foreach ($active_funds as $fund) { ?><option value="<?php echo (int) $fund->id; ?>"><?php echo html_escape($fund->fund_code.' — '.$fund->name); ?></option><?php } ?></select></div>
					<div class="form-group"><label for="new_closing_date">Tanggal tutup</label><input class="form-control" id="new_closing_date" type="date" name="closing_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
					<div class="form-group"><label for="new_counted_balance">Uang fisik dihitung (Rp)</label><input class="form-control" id="new_counted_balance" type="number" name="counted_balance" min="0" max="9999999999999.99" step="0.01" required></div>
					<div class="form-group"><label for="new_closing_notes">Catatan selisih / kondisi</label><textarea class="form-control" id="new_closing_notes" name="notes" maxlength="500"></textarea></div>
					<button class="btn btn-warning" type="submit" onclick="return confirm('Sahkan saldo fisik? Tanggal tutup tidak dapat diubah.');">Sahkan tutup kas</button>
				</form>
				<?php } else { ?><p class="text-muted">Hanya admin, management, atau pengurus yang dapat mengesahkan tutup kas.</p><?php } ?>
			</div></div>
		</div>
		<div class="col-md-7">
			<div class="box box-info"><div class="box-header with-border"><h3 class="box-title">Riwayat rekonsiliasi</h3></div><div class="box-body table-responsive">
				<table class="table table-striped"><thead><tr><th>Tanggal</th><th>Dana</th><th class="text-right">Saldo sistem</th><th class="text-right">Fisik</th><th class="text-right">Selisih</th><th>Catatan</th></tr></thead><tbody>
				<?php foreach ($closings as $closing) { ?><tr><td><?php echo html_escape($closing->closing_date); ?></td><td><?php echo html_escape($closing->fund_code.' — '.$closing->fund_name); ?></td><td class="text-right"><?php echo $format_rupiah($closing->system_balance); ?></td><td class="text-right"><?php echo $format_rupiah($closing->counted_balance); ?></td><td class="text-right"><?php echo $format_rupiah($closing->variance); ?></td><td><?php echo html_escape($closing->notes ? $closing->notes : '—'); ?></td></tr><?php } ?>
				<?php if (empty($closings)) { ?><tr><td colspan="6" class="text-muted">Belum ada tutup kas.</td></tr><?php } ?>
				</tbody></table>
				<?php if (count($closings) === 60) { ?><p class="text-warning">Menampilkan 60 rekonsiliasi terbaru.</p><?php } ?>
			</div></div>
		</div>
	</div>
<?php } ?>

<script>
(function () {
	var type = document.getElementById('new_transaction_type');
	if (type) {
		var fund = document.getElementById('new_cash_fund');
		var expense = document.getElementById('new_expense_group');
		var expenseSelect = document.getElementById('new_expense_account');
		var funding = document.getElementById('new_funding_group');
		var source = document.getElementById('new_funding_source');
		var receiptGroup = document.getElementById('new_receipt_group');
		var receipt = document.getElementById('new_receipt');
		function updateTransactionFields() {
			var fundingSelected = type.value === 'funding';
			var receiptSelected = type.value === 'supplier_payment';
			var isPetty = fund.options[fund.selectedIndex] && fund.options[fund.selectedIndex].getAttribute('data-type') === 'petty';
			expense.style.display = type.value === 'expense' ? '' : 'none';
			expenseSelect.required = type.value === 'expense';
			funding.style.display = fundingSelected ? '' : 'none';
			source.required = fundingSelected;
			receiptGroup.style.display = receiptSelected ? '' : 'none';
			receipt.required = receiptSelected;
			Array.prototype.forEach.call(source.options, function (option) {
				if (option.value) {
					option.disabled = isPetty ? option.getAttribute('data-code') !== '1106' :
						(option.getAttribute('data-code') !== '1101' && option.getAttribute('data-code') !== '1102');
				}
			});
		}
		type.addEventListener('change', updateTransactionFields);
		fund.addEventListener('change', updateTransactionFields);
		receipt.addEventListener('change', function () {
			var option = receipt.options[receipt.selectedIndex];
			document.getElementById('new_counterparty').value = option && option.getAttribute('data-supplier') ? option.getAttribute('data-supplier') : '';
		});
		updateTransactionFields();
	}
	var fundType = document.getElementById('new_fund_type');
	if (fundType) {
		var limitGroup = document.getElementById('new_imprest_group');
		var limit = document.getElementById('new_imprest_limit');
		var updateFundFields = function () {
			var isPetty = fundType.value === 'petty';
			limitGroup.style.display = isPetty ? '' : 'none';
			limit.required = isPetty;
		};
		fundType.addEventListener('change', updateFundFields);
		updateFundFields();
	}
}());
</script>
