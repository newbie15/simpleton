<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $fund_type_labels = array('cashier' => 'Laci kasir', 'main' => 'Kas utama', 'petty' => 'Petty cash'); ?>

<?php if ($notice) { ?><div class="alert alert-success"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger"><?php echo html_escape($error); ?></div><?php } ?>

<div class="alert alert-info">
	Transaksi sumber tetap diposting otomatis. Permintaan kas di bawah ini membentuk jurnal hanya setelah disetujui.
	Penjualan tunai tetap berada di akun kas kasir 1101; transfer ke kas utama dan petty cash harus dicatat terpisah.
</div>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Saldo dana kas</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped">
			<thead><tr><th>Kode</th><th>Dana</th><th>Jenis</th><th>Pemegang</th><th class="text-right">Saldo buku</th><th class="text-right">Batas imprest</th><th>Status</th></tr></thead>
			<tbody>
				<?php if (count($funds) === 0) { ?><tr><td colspan="7" class="text-muted">Belum ada dana kas. Admin, management, atau pengurus dapat membuat dana terlebih dahulu.</td></tr><?php } ?>
				<?php foreach ($funds as $fund) { ?>
					<tr>
						<td><?php echo html_escape($fund->fund_code); ?></td>
						<td><?php echo html_escape($fund->name); ?></td>
						<td><?php echo html_escape(isset($fund_type_labels[$fund->fund_type]) ? $fund_type_labels[$fund->fund_type] : $fund->fund_type); ?></td>
						<td><?php echo html_escape($fund->custodian_username ? $fund->custodian_username : '-'); ?></td>
						<td class="text-right">Rp <?php echo number_format((float) $fund->balance, 2, ',', '.'); ?></td>
						<td class="text-right"><?php echo $fund->fund_type === 'petty' ? 'Rp '.number_format((float) $fund->imprest_limit, 2, ',', '.') : '-'; ?></td>
						<td><?php echo (int) $fund->is_active === 1 ? 'Aktif' : 'Nonaktif'; ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<p class="help-block">Saldo kasir POS terpisah dan dilaporkan pada akun 1101. Kas utama memakai 1106; petty cash memakai 1107.</p>
	</div>
</div>

<div class="box box-info">
	<div class="box-header with-border"><h3 class="box-title">Buku kas harian</h3></div>
	<div class="box-body">
		<form class="form-inline" method="get" action="<?php echo site_url('main/petty_cash'); ?>">
			<div class="form-group">
				<label for="daily_fund_id">Dana</label>
				<select class="form-control" id="daily_fund_id" name="fund_id" required>
					<?php foreach ($funds as $fund) { if ((int) $fund->is_active === 1) { ?>
						<option value="<?php echo (int) $fund->id; ?>" <?php echo $daily_fund && (int) $daily_fund->id === (int) $fund->id ? 'selected' : ''; ?>>
							<?php echo html_escape($fund->fund_code.' — '.$fund->name); ?>
						</option>
					<?php } } ?>
				</select>
			</div>
			<div class="form-group" style="margin-left:8px;">
				<label for="report_date">Tanggal</label>
				<input class="form-control" type="date" id="report_date" name="report_date" value="<?php echo html_escape($report_date); ?>" max="<?php echo date('Y-m-d'); ?>" required>
			</div>
			<button class="btn btn-primary" type="submit" style="margin-left:8px;">Lihat buku kas</button>
		</form>
		<?php if ($daily_fund) { ?>
			<div class="table-responsive" style="margin-top:15px;">
				<table class="table table-bordered table-striped">
					<thead>
						<tr><th colspan="6"><?php echo html_escape($daily_fund->fund_code.' — '.$daily_fund->name.' — '.$report_date); ?></th></tr>
						<tr><th>Jurnal</th><th>Referensi</th><th>Keterangan</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th class="text-right">Saldo berjalan</th></tr>
					</thead>
					<tbody>
						<tr class="active"><th colspan="5">Saldo awal</th><th class="text-right">Rp <?php echo number_format($daily_opening, 2, ',', '.'); ?></th></tr>
						<?php if (count($daily_rows) === 0) { ?><tr><td colspan="6" class="text-muted">Tidak ada mutasi pada tanggal ini.</td></tr><?php } ?>
						<?php foreach ($daily_rows as $row) { ?>
							<tr>
								<td><?php echo html_escape($row->journal_number); ?></td>
								<td><?php echo html_escape($row->transaction_number ? $row->transaction_number : $row->reference_type.':'.$row->reference_id); ?></td>
								<td><?php echo html_escape($row->memo ? $row->memo : $row->description); ?>
									<?php if ($row->counterparty) { ?><br><small><?php echo html_escape($row->counterparty); ?></small><?php } ?>
								</td>
								<td class="text-right"><?php echo (float) $row->debit > 0 ? 'Rp '.number_format((float) $row->debit, 2, ',', '.') : '-'; ?></td>
								<td class="text-right"><?php echo (float) $row->credit > 0 ? 'Rp '.number_format((float) $row->credit, 2, ',', '.') : '-'; ?></td>
								<td class="text-right">Rp <?php echo number_format($row->running_balance, 2, ',', '.'); ?></td>
							</tr>
						<?php } ?>
					</tbody>
					<tfoot><tr><th colspan="5">Saldo akhir sistem</th><th class="text-right">Rp <?php echo number_format($daily_balance, 2, ',', '.'); ?></th></tr></tfoot>
				</table>
			</div>
		<?php } else { ?>
			<p class="text-muted" style="margin-top:15px;">Buat atau aktifkan dana kas untuk melihat buku kas hariannya.</p>
		<?php } ?>
	</div>
</div>

<div class="row">
	<div class="col-md-6">
		<div class="box box-success">
			<div class="box-header with-border"><h3 class="box-title">Ajukan transaksi kas</h3></div>
			<div class="box-body">
				<form method="post" enctype="multipart/form-data" action="<?php echo site_url('main/petty_cash/submit_transaction'); ?>">
					<div class="form-group">
						<label for="fund_id">Dana</label>
						<select class="form-control" id="fund_id" name="fund_id" required>
							<option value="">Pilih dana</option>
							<?php foreach ($funds as $fund) { if ((int) $fund->is_active === 1) { ?>
								<option value="<?php echo (int) $fund->id; ?>" data-type="<?php echo html_escape($fund->fund_type); ?>">
									<?php echo html_escape($fund->fund_code.' — '.$fund->name.' ('.$fund_type_labels[$fund->fund_type].')'); ?>
								</option>
							<?php } } ?>
						</select>
					</div>
					<div class="form-group">
						<label for="transaction_type">Jenis transaksi</label>
						<select class="form-control" id="transaction_type" name="transaction_type" required>
							<option value="expense">Pengeluaran</option>
							<option value="supplier_payment">Pembayaran hutang supplier barang</option>
							<option value="funding">Penambahan / transfer masuk</option>
						</select>
					</div>
					<div class="form-group">
						<label for="transaction_date">Tanggal transaksi</label>
						<input class="form-control" type="date" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
					</div>
					<div class="form-group">
						<label for="amount">Nominal (Rp)</label>
						<input class="form-control" type="number" id="amount" name="amount" min="0.01" max="9999999999999.99" step="0.01" required>
					</div>
					<div class="form-group" id="expense_account_group">
						<label for="expense_account_id">Akun beban</label>
						<select class="form-control" id="expense_account_id" name="expense_account_id">
							<option value="">Pilih akun beban</option>
							<?php foreach ($expense_accounts as $account) { ?>
								<option value="<?php echo (int) $account->id; ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="form-group" id="funding_source_group">
						<label for="funding_source_account_id">Sumber dana</label>
						<select class="form-control" id="funding_source_account_id" name="funding_source_account_id">
							<option value="">Pilih akun sumber</option>
							<?php foreach ($funding_accounts as $account) { ?>
								<option value="<?php echo (int) $account->id; ?>" data-code="<?php echo html_escape($account->code); ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option>
							<?php } ?>
						</select>
						<p class="help-block">Kas utama dapat diisi dari kasir atau bank; petty cash diisi dari kas utama. Saldo sumber diperiksa saat persetujuan.</p>
					</div>
					<div class="form-group" id="supplier_receipt_group">
						<label for="goods_receipt_id">Faktur / penerimaan supplier yang dibayar</label>
						<select class="form-control" id="goods_receipt_id" name="goods_receipt_id">
							<option value="">Pilih hutang supplier</option>
							<?php foreach ($supplier_receipts as $receipt) { ?>
								<option value="<?php echo (int) $receipt->id; ?>" data-supplier="<?php echo html_escape($receipt->supplier_name); ?>">
									<?php echo html_escape($receipt->receipt_number.' — '.$receipt->supplier_name.' — sisa Rp '.number_format((float) $receipt->outstanding, 2, ',', '.')); ?>
								</option>
							<?php } ?>
						</select>
						<p class="help-block">Pembayaran ditautkan ke hutang penerimaan, mengurangi akun 2101 dan saldo kas. Bukan beban operasional/persediaan kedua kali.</p>
					</div>
					<div class="form-group">
						<label for="counterparty">Penerima / supplier</label>
						<input class="form-control" type="text" id="counterparty" name="counterparty" maxlength="128">
					</div>
					<div class="form-group">
						<label for="description">Keterangan</label>
						<textarea class="form-control" id="description" name="description" maxlength="500" required></textarea>
					</div>
					<div class="form-group">
						<label for="evidence_reference">Nomor / referensi bukti</label>
						<input class="form-control" type="text" id="evidence_reference" name="evidence_reference" maxlength="128" required>
					</div>
					<div class="form-group">
						<label for="proof_file">Lampiran bukti (PDF/JPG/PNG, maks. 5 MB)</label>
						<input class="form-control" type="file" id="proof_file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png" required>
					</div>
					<button class="btn btn-success" type="submit">Ajukan</button>
				</form>
			</div>
		</div>
	</div>

	<div class="col-md-6">
		<div class="box box-warning">
			<div class="box-header with-border"><h3 class="box-title">Tutup dan hitung kas harian</h3></div>
			<div class="box-body">
				<?php if ($is_approver) { ?>
					<form method="post" action="<?php echo site_url('main/petty_cash/close_day'); ?>">
						<div class="form-group">
							<label for="closing_fund_id">Dana</label>
							<select class="form-control" id="closing_fund_id" name="fund_id" required>
								<option value="">Pilih dana</option>
								<?php foreach ($funds as $fund) { if ((int) $fund->is_active === 1) { ?>
									<option value="<?php echo (int) $fund->id; ?>"><?php echo html_escape($fund->fund_code.' — '.$fund->name); ?></option>
								<?php } } ?>
							</select>
						</div>
						<div class="form-group">
							<label for="closing_date">Tanggal tutup</label>
							<input class="form-control" type="date" id="closing_date" name="closing_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
						</div>
						<div class="form-group">
							<label for="counted_balance">Uang fisik dihitung (Rp)</label>
							<input class="form-control" type="number" id="counted_balance" name="counted_balance" min="0" max="9999999999999.99" step="0.01" required>
						</div>
						<div class="form-group">
							<label for="closing_notes">Catatan selisih / kondisi</label>
							<textarea class="form-control" id="closing_notes" name="notes" maxlength="500"></textarea>
						</div>
						<button class="btn btn-warning" type="submit" onclick="return confirm('Sahkan saldo fisik untuk dana dan tanggal ini? Tanggal tutup tidak dapat diubah.');">Sahkan tutup kas</button>
					</form>
				<?php } else { ?>
					<p class="text-muted">Tutup kas disahkan oleh admin, management, atau pengurus setelah transaksi tertunda diproses.</p>
				<?php } ?>
			</div>
		</div>
		<?php if ($is_approver) { ?>
			<div class="box box-default">
				<div class="box-header with-border"><h3 class="box-title">Buat dana kas</h3></div>
				<div class="box-body">
					<form method="post" action="<?php echo site_url('main/petty_cash/create_fund'); ?>">
						<div class="form-group">
							<label for="fund_code">Kode unik</label>
							<input class="form-control" type="text" id="fund_code" name="fund_code" maxlength="32" pattern="[A-Za-z0-9_-]{2,32}" required>
						</div>
						<div class="form-group">
							<label for="fund_name">Nama dana / lokasi</label>
							<input class="form-control" type="text" id="fund_name" name="name" maxlength="100" required>
						</div>
						<div class="form-group">
							<label for="fund_type">Jenis</label>
							<select class="form-control" id="fund_type" name="fund_type" required>
								<option value="petty">Petty cash (akun 1107)</option>
								<option value="main">Kas utama (akun 1106)</option>
							</select>
						</div>
						<div class="form-group">
							<label for="custodian_user_id">Pemegang dana (opsional)</label>
							<select class="form-control" id="custodian_user_id" name="custodian_user_id">
								<option value="">Belum ditetapkan</option>
								<?php foreach ($users as $user) { ?>
									<option value="<?php echo html_escape($user->id); ?>"><?php echo html_escape($user->username.' — '.trim($user->first_name.' '.$user->last_name)); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="form-group" id="imprest_limit_group">
							<label for="imprest_limit">Batas dana tetap (Rp)</label>
							<input class="form-control" type="number" id="imprest_limit" name="imprest_limit" min="0.01" max="9999999999999.99" step="0.01" value="1000000.00">
						</div>
						<button class="btn btn-default" type="submit">Buat dana</button>
					</form>
				</div>
			</div>
		<?php } ?>
	</div>
</div>

<div class="box box-info">
	<div class="box-header with-border"><h3 class="box-title">Permintaan & transaksi kas</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Tanggal / nomor</th><th>Dana / tipe</th><th>Nominal</th><th>Rincian / bukti</th><th>Penginput / status</th><th>Persetujuan</th><?php if ($is_approver) { ?><th>Aksi</th><?php } ?></tr></thead>
			<tbody>
				<?php if (count($transactions) === 0) { ?><tr><td colspan="<?php echo $is_approver ? 7 : 6; ?>" class="text-muted">Belum ada transaksi kas.</td></tr><?php } ?>
				<?php foreach ($transactions as $transaction) { ?>
					<tr>
						<td><?php echo html_escape($transaction->transaction_date); ?><br><small><?php echo html_escape($transaction->transaction_number); ?></small></td>
						<td><?php echo html_escape($transaction->fund_code.' — '.$transaction->fund_name); ?><br><?php
							$type_labels = array(
								'funding' => 'Penambahan dana',
								'expense' => 'Pengeluaran',
								'supplier_payment' => 'Pembayaran supplier'
							);
							echo html_escape(isset($type_labels[$transaction->transaction_type])
								? $type_labels[$transaction->transaction_type]
								: $transaction->transaction_type);
						?></td>
						<td class="text-right">Rp <?php echo number_format((float) $transaction->amount, 2, ',', '.'); ?></td>
						<td>
							<?php if ($transaction->transaction_type === 'expense') { ?>
								<?php echo html_escape($transaction->expense_code.' '.$transaction->expense_name); ?><br>
							<?php } elseif ($transaction->transaction_type === 'supplier_payment') { ?>
								Hutang: <?php echo html_escape($transaction->receipt_number ? $transaction->receipt_number : 'penerimaan'); ?><br>
							<?php } else { ?>
								Sumber: <?php echo html_escape($transaction->source_code.' '.$transaction->source_name); ?><br>
							<?php } ?>
							<?php echo html_escape($transaction->counterparty ? $transaction->counterparty.' — ' : ''); ?>
							<?php echo html_escape($transaction->description); ?><br>
							Bukti: <?php echo html_escape($transaction->evidence_reference); ?>
							<?php if ($transaction->proof_filename) { ?><br><a href="<?php echo site_url('main/petty_cash/proof/'.(int) $transaction->id); ?>">Lihat lampiran</a><?php } ?>
						</td>
						<td><?php echo html_escape($transaction->creator_name); ?><br>
							<?php
								$status_labels = array('pending' => 'Menunggu persetujuan', 'posted' => 'Sudah diposting', 'rejected' => 'Ditolak');
								echo html_escape(isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : $transaction->status);
							?>
							<?php if ($transaction->status === 'rejected') { ?><br><span class="text-danger"><?php echo html_escape($transaction->rejection_reason); ?></span><?php } ?>
						</td>
						<td><?php echo $transaction->approver_name ? html_escape($transaction->approver_name.' — '.$transaction->approved_at) : '-'; ?></td>
						<?php if ($is_approver) { ?>
							<td>
								<?php if ($transaction->status === 'pending' && (string) $transaction->created_by !== (string) $this->ion_auth->get_user_id()) { ?>
									<form method="post" action="<?php echo site_url('main/petty_cash/approve/'.(int) $transaction->id); ?>" style="margin-bottom:5px;">
										<button class="btn btn-xs btn-success" type="submit" onclick="return confirm('Setujui dan posting jurnal transaksi ini?');">Setujui & posting</button>
									</form>
									<form method="post" action="<?php echo site_url('main/petty_cash/reject/'.(int) $transaction->id); ?>">
										<input class="form-control input-sm" type="text" name="rejection_reason" maxlength="500" placeholder="Alasan penolakan" required>
										<button class="btn btn-xs btn-danger" type="submit" style="margin-top:4px;">Tolak</button>
									</form>
								<?php } else { echo '-'; } ?>
							</td>
						<?php } ?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<p class="help-block">Transaksi yang sudah diposting tidak diedit atau dihapus; koreksi dilakukan melalui transaksi reversal yang terlacak.</p>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Rekonsiliasi harian</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped">
			<thead><tr><th>Tanggal</th><th>Dana</th><th class="text-right">Saldo sistem</th><th class="text-right">Kas fisik</th><th class="text-right">Selisih</th><th>Catatan</th><th>Disahkan oleh</th></tr></thead>
			<tbody>
				<?php if (count($closings) === 0) { ?><tr><td colspan="7" class="text-muted">Belum ada tutup kas.</td></tr><?php } ?>
				<?php foreach ($closings as $closing) { ?>
					<tr class="<?php echo abs((float) $closing->variance) > 0.009 ? 'warning' : ''; ?>">
						<td><?php echo html_escape($closing->closing_date); ?></td>
						<td><?php echo html_escape($closing->fund_code.' — '.$closing->fund_name); ?></td>
						<td class="text-right">Rp <?php echo number_format((float) $closing->system_balance, 2, ',', '.'); ?></td>
						<td class="text-right">Rp <?php echo number_format((float) $closing->counted_balance, 2, ',', '.'); ?></td>
						<td class="text-right">Rp <?php echo number_format((float) $closing->variance, 2, ',', '.'); ?></td>
						<td><?php echo html_escape($closing->notes ? $closing->notes : '-'); ?></td>
						<td><?php echo html_escape($closing->closed_by_name.' — '.$closing->closed_at); ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<p class="help-block">Selisih dicatat untuk pemeriksaan dan tidak dibukukan sebagai beban/pendapatan otomatis.</p>
	</div>
</div>

<script>
(function () {
	var type = document.getElementById('transaction_type');
	var fund = document.getElementById('fund_id');
	var expenseType = type.querySelector('option[value="expense"]');
	var fundingType = type.querySelector('option[value="funding"]');
	var expenseGroup = document.getElementById('expense_account_group');
	var expense = document.getElementById('expense_account_id');
	var sourceGroup = document.getElementById('funding_source_group');
	var source = document.getElementById('funding_source_account_id');
	var receiptGroup = document.getElementById('supplier_receipt_group');
	var receipt = document.getElementById('goods_receipt_id');
	var fundType = document.getElementById('fund_type');
	var limitGroup = document.getElementById('imprest_limit_group');
	var limit = document.getElementById('imprest_limit');
	function updateTransactionFields() {
		var funding = type.value === 'funding';
		var supplierPayment = type.value === 'supplier_payment';
		var selected = fund.options[fund.selectedIndex];
		var isPetty = selected && selected.getAttribute('data-type') === 'petty';
		var isCashier = selected && selected.getAttribute('data-type') === 'cashier';
		expenseType.disabled = !isPetty;
		fundingType.disabled = isCashier;
		if ((!isPetty && type.value === 'expense') || (isCashier && type.value === 'funding')) {
			type.value = isCashier ? 'supplier_payment' : 'funding';
			funding = type.value === 'funding';
			supplierPayment = type.value === 'supplier_payment';
		}
		expenseGroup.style.display = funding || supplierPayment ? 'none' : '';
		sourceGroup.style.display = funding ? '' : 'none';
		receiptGroup.style.display = supplierPayment ? '' : 'none';
		expense.required = !funding && !supplierPayment;
		source.required = funding;
		receipt.required = supplierPayment;
		Array.prototype.forEach.call(source.options, function (option) {
			var code = option.getAttribute('data-code');
			option.disabled = code && (isPetty ? code !== '1106' : (code !== '1101' && code !== '1102'));
		});
		if (source.selectedIndex >= 0 && source.options[source.selectedIndex].disabled) {
			source.value = '';
		}
		if (supplierPayment && receipt.selectedIndex > 0) {
			document.getElementById('counterparty').value =
				receipt.options[receipt.selectedIndex].getAttribute('data-supplier') || '';
		}
	}
	function updateFundFields() {
		var petty = fundType.value === 'petty';
		limitGroup.style.display = petty ? '' : 'none';
		limit.required = petty;
	}
	type.addEventListener('change', updateTransactionFields);
	fund.addEventListener('change', updateTransactionFields);
	receipt.addEventListener('change', updateTransactionFields);
	fundType.addEventListener('change', updateFundFields);
	updateTransactionFields();
	updateFundFields();
}());
</script>
