<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<div class="box box-primary">
	<div class="box-header with-border">
		<h3 class="box-title">Ajukan pembayaran supplier</h3>
		<div class="box-tools pull-right"><a class="btn btn-default btn-sm" href="<?php echo site_url('main/management_payments'); ?>">Kembali ke Manage Payment</a></div>
	</div>
	<form method="post" enctype="multipart/form-data" action="<?php echo site_url('main/management_payments/submit_request'); ?>">
		<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
		<div class="box-body">
			<div class="alert alert-info">
				Pembayaran akan mengurangi hutang supplier pada akun <strong>2101 — Hutang Usaha</strong>. Saat disetujui, jurnal mendebit akun 2101 dan mengkredit akun sumber kas/bank yang dipilih.
			</div>
			<?php if (empty($receipts)) { ?>
				<div class="alert alert-warning">Tidak ada nota penerimaan dengan sisa hutang yang dapat dibayar.</div>
			<?php } ?>
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label for="payment-goods-receipt">Nota penerimaan supplier</label>
						<select class="form-control" id="payment-goods-receipt" name="goods_receipt_id" required>
							<option value="">Pilih nota penerimaan</option>
							<?php foreach ($receipts as $receipt) { ?>
								<option value="<?php echo (int) $receipt->id; ?>" data-outstanding="<?php echo html_escape(number_format((float) $receipt->outstanding, 2, '.', '')); ?>"<?php echo (int) $selected_receipt_id === (int) $receipt->id ? ' selected' : ''; ?>>
									<?php echo html_escape($receipt->order_number.' · '.$receipt->receipt_number.' · '.$receipt->supplier_name.' · sisa Rp '.number_format((float) $receipt->outstanding, 0, ',', '.').($receipt->purchase_order_status === 'closed' ? ' · PO ditutup' : '')); ?>
								</option>
							<?php } ?>
						</select>
					</div>
					<div class="form-group">
						<label for="payment-source-account">Sumber dana (akun kas/bank)</label>
						<select class="form-control" id="payment-source-account" name="source_account_id" required>
							<option value="">Pilih akun sumber</option>
							<?php foreach ($accounts as $account) { ?>
								<option value="<?php echo (int) $account->id; ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option>
							<?php } ?>
						</select>
						<p class="help-block">Pilih akun yang benar-benar digunakan untuk membayar, misalnya rekening bank atau kas.</p>
					</div>
					<div class="form-group">
						<label for="payment-date">Tanggal pembayaran</label>
						<input class="form-control" id="payment-date" type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
					</div>
					<div class="form-group">
						<label for="payment-amount">Nominal (Rp)</label>
						<input class="form-control" id="payment-amount" type="text" name="amount" inputmode="decimal" autocomplete="off" placeholder="Contoh: 1.250.000" required>
						<div id="payment-amount-warning" class="alert alert-danger" role="alert" style="display:none; margin-top:8px;"></div>
						<p class="help-block">Masukkan nominal sampai dengan sisa hutang nota. Format ribuan menggunakan titik.</p>
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<label for="payment-description">Keterangan</label>
						<textarea class="form-control" id="payment-description" name="description" maxlength="500" required></textarea>
					</div>
					<div class="form-group">
						<label for="payment-evidence">Nomor / referensi bukti transfer atau pembayaran</label>
						<input class="form-control" id="payment-evidence" type="text" name="evidence_reference" maxlength="128" required>
					</div>
					<div class="form-group">
						<label for="payment-proof">Bukti pembayaran (PDF/JPG/PNG, maks. 5 MB)</label>
						<input class="form-control" id="payment-proof" type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png" required>
					</div>
				</div>
			</div>
		</div>
		<div class="box-footer">
			<button class="btn btn-success" type="submit"<?php echo empty($receipts) || empty($accounts) ? ' disabled' : ''; ?>>Ajukan untuk persetujuan</button>
			<a class="btn btn-default" href="<?php echo site_url('main/management_payments'); ?>">Batal</a>
		</div>
	</form>
</div>
