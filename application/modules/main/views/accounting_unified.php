<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$rp = function ($value) { return 'Rp '.number_format((float) $value, 2, ',', '.'); };
$rp_int = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); };
$net_income = $sums['income']['balance'] - $sums['expense']['balance'];
$net_income_period = $sums['income']['movement'] - $sums['expense']['movement'];

$render_tree = function ($nodes, $depth) use (&$render_tree, $rp, $month, $start_date, $end_date) {
	foreach ($nodes as $node) {
		echo '<tr><td style="padding-left:'.(8 + $depth * 20).'px"><code>'.html_escape($node->code).'</code> &nbsp;'
			.'<a href="'.site_url('main/accounting_unified?tab=ledger&account_id='.(int) $node->id.'&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)).'"><strong>'.html_escape($node->name).'</strong></a></td>'
			.'<td class="text-right">'.$rp($node->total_movement).'</td>'
			.'<td class="text-right">'.$rp($node->total).'</td></tr>';
		$render_tree($node->children, $depth + 1);
	}
};

$tabs = array(
	'dashboard' => array('fa-dashboard', 'Dashboard & Tren'),
	'reports' => array('fa-file-text-o', 'Laporan Keuangan'),
	'ledger' => array('fa-book', 'Buku Besar'),
	'journals' => array('fa-list-alt', 'Daftar Jurnal'),
	'input' => array('fa-pencil-square-o', 'Input Transaksi'),
	'accounts' => array('fa-sitemap', 'Bagan Akun'),
	'periods' => array('fa-calendar-check-o', 'Periode Akuntansi')
);
?>

<style>
.accounting-unified .nav-tabs-custom > .nav-tabs > li.active { border-top-color: #00a65a; }
.accounting-unified .badge-pill { border-radius: 10px; padding: 3px 8px; font-weight: normal; }
.accounting-unified .table-header-dark th { background: #f4f5f7; }
.grid-workspace .jexcel_content { max-width: 100%; }
</style>

<div class="accounting-unified">
	<?php if ($notice) { ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?php echo html_escape($notice); ?></div><?php } ?>
	<?php if ($error) { ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?php echo html_escape($error); ?></div><?php } ?>

	<?php
	$unposted_total = isset($unposted) ? array_sum($unposted) : 0;
	if ($unposted_total > 0) {
	?>
	<div class="alert alert-warning alert-dismissible">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<h4><i class="fa fa-warning"></i> Perhatian: Terdapat <?php echo number_format($unposted_total); ?> transaksi belum terposting!</h4>
		<?php if ($unposted['POS'] > 0) { ?><span><i class="fa fa-shopping-cart"></i> POS: <?php echo number_format($unposted['POS']); ?> nota | </span><?php } ?>
		<?php if ($unposted['PURCHASING'] > 0) { ?><span><i class="fa fa-truck"></i> Penerimaan Barang: <?php echo number_format($unposted['PURCHASING']); ?> | </span><?php } ?>
		<?php if ($unposted['SALES_RETURN'] > 0) { ?><span><i class="fa fa-reply"></i> Retur Penjualan: <?php echo number_format($unposted['SALES_RETURN']); ?> | </span><?php } ?>
		<?php if ($unposted['PETTY_CASH'] > 0) { ?><span><i class="fa fa-money"></i> Kas Pending: <?php echo number_format($unposted['PETTY_CASH']); ?> | </span><?php } ?>
		<a href="<?php echo site_url('main/accounting_unified?tab=periods'); ?>" class="btn btn-xs btn-default" style="margin-left:10px;color:#333;">Periksa di Periode</a>
	</div>
	<?php } ?>

	<!-- FILTER BARIS ATAS -->
	<div class="box box-solid" style="margin-bottom:15px; border-radius:4px;">
		<div class="box-body" style="padding:10px 15px; background:#fff;">
			<form class="form-inline" method="get" action="<?php echo site_url('main/accounting_unified'); ?>">
				<input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
				<div class="form-group">
					<label><i class="fa fa-calendar"></i> Rentang:&nbsp;</label>
					<input type="date" name="start_date" class="form-control input-sm" value="<?php echo html_escape($start_date); ?>" required>
				</div>
				<div class="form-group" style="margin-left:6px;">
					<label>s.d.&nbsp;</label>
					<input type="date" name="end_date" class="form-control input-sm" value="<?php echo html_escape($end_date); ?>" required>
				</div>
				<button class="btn btn-sm btn-primary" type="submit" style="margin-left:8px;"><i class="fa fa-filter"></i> Terapkan</button>
				<a href="<?php echo site_url('main/accounting_unified?tab='.$tab.'&month='.date('Y-m')); ?>" class="btn btn-sm btn-default" style="margin-left:4px;">Bulan Ini</a>
				
				<div class="pull-right" style="margin-top:2px;">
					<a href="<?php echo site_url('main/bank_reconciliation'); ?>" class="btn btn-sm btn-info"><i class="fa fa-university"></i> Rekonsiliasi Bank</a>
				</div>
			</form>
		</div>
	</div>

	<!-- TAB NAVIGATION -->
	<div class="nav-tabs-custom">
		<ul class="nav nav-tabs">
			<?php foreach ($tabs as $k => $info) { ?>
			<li<?php echo $tab === $k ? ' class="active"' : ''; ?>>
				<a href="<?php echo site_url('main/accounting_unified?tab='.$k.'&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)); ?>">
					<i class="fa <?php echo $info[0]; ?>"></i> <?php echo $info[1]; ?>
				</a>
			</li>
			<?php } ?>
		</ul>

		<div class="tab-content" style="padding:15px;">
			<!-- ========================================== -->
			<!-- TAB 1: DASHBOARD & TREN GRAFIK             -->
			<!-- ========================================== -->
			<?php if ($tab === 'dashboard') { ?>
			<div class="row">
				<div class="col-md-3 col-sm-6">
					<div class="small-box bg-green">
						<div class="inner">
							<h3><?php echo $rp_int($totals['income']); ?></h3>
							<p>Pendapatan Periode</p>
						</div>
						<div class="icon"><i class="fa fa-arrow-down"></i></div>
					</div>
				</div>
				<div class="col-md-3 col-sm-6">
					<div class="small-box bg-red">
						<div class="inner">
							<h3><?php echo $rp_int($totals['expenses'] + $totals['cogs']); ?></h3>
							<p>Beban + HPP Periode</p>
						</div>
						<div class="icon"><i class="fa fa-arrow-up"></i></div>
					</div>
				</div>
				<div class="col-md-3 col-sm-6">
					<div class="small-box bg-aqua">
						<div class="inner">
							<h3><?php echo $rp_int($net_income_period); ?></h3>
							<p>Laba / Rugi Bersih</p>
						</div>
						<div class="icon"><i class="fa fa-balance-scale"></i></div>
					</div>
				</div>
				<div class="col-md-3 col-sm-6">
					<div class="small-box bg-yellow">
						<div class="inner">
							<h3><?php echo $rp_int($totals['assets']); ?></h3>
							<p>Total Aset (s.d. <?php echo html_escape($end_date); ?>)</p>
						</div>
						<div class="icon"><i class="fa fa-university"></i></div>
					</div>
				</div>
			</div>

			<!-- SANKEY DIAGRAM ALIRAN PENDAPATAN & BEBAN -->
			<div class="box box-success" id="sankey-box">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-random"></i> Diagram Aliran Dana / Sankey (Pendapatan &rarr; Alokasi Beban &rarr; Laba Bersih)</h3>
					<div class="box-tools pull-right">
						<button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
					</div>
				</div>
				<div class="box-body">
					<p class="text-muted" style="margin-bottom:12px;">
						Visualisasi aliran arus kas masuk dari sumber pendapatan operasional toko, didistribusikan ke Harga Pokok Penjualan (HPP), beban operasional, dan laba bersih periode berjalan.
					</p>
					<div id="sankey-canvas-container" style="position:relative; width:100%; overflow-x:auto;">
						<canvas id="sankey-canvas" width="1050" height="380" style="display:block; width:100%; max-width:1050px; background:#fafafa; border:1px solid #e3e3e3; border-radius:4px;"></canvas>
					</div>
					<div id="sankey-summary" style="margin-top:10px; font-size:13px;"></div>
				</div>
			</div>

			<!-- GRAFIK KUSTOM INTERAKTIF (DARI ACCOUNTING_BOOK) -->
			<div class="box box-info" id="chart-box">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-bar-chart"></i> Analisis & Visualisasi Grafik Interaktif</h3>
				</div>
				<div class="box-body">
					<div class="row">
						<div class="col-sm-3 form-group">
							<label>Mode Data</label>
							<select id="ch-mode" class="form-control input-sm">
								<option value="trend">Tren Perkembangan per Bulan</option>
								<option value="breakdown">Rincian per Akun (Bulan Terpilih)</option>
							</select>
						</div>
						<div class="col-sm-2 form-group">
							<label>Kelompok Akun</label>
							<select id="ch-group" class="form-control input-sm">
								<option value="income">Pendapatan (Income)</option>
								<option value="expense">Beban (Expense)</option>
								<option value="net">Laba / Rugi Bersih</option>
								<option value="asset">Aset (Asset)</option>
								<option value="liability">Kewajiban (Liability)</option>
								<option value="equity">Modal (Equity)</option>
							</select>
						</div>
						<div class="col-sm-2 form-group">
							<label>Bentuk Grafik</label>
							<select id="ch-type" class="form-control input-sm">
								<option value="bar">Diagram Batang</option>
								<option value="line">Diagram Garis</option>
								<option value="pie">Diagram Pai (Breakdown)</option>
							</select>
						</div>
						<div class="col-sm-2 form-group">
							<label>Rentang Waktu</label>
							<input type="number" id="ch-months" class="form-control input-sm" min="2" max="24" value="6">
						</div>
						<div class="col-sm-1 form-group">
							<label>Warna</label>
							<input type="color" id="ch-color" class="form-control input-sm" value="#00a65a">
						</div>
						<div class="col-sm-2 form-group" style="padding-top:20px;">
							<label><input type="checkbox" id="ch-values" checked> Tampilkan Angka</label>
						</div>
					</div>
					<canvas id="ch-canvas" width="1000" height="340" style="width:100%;max-width:1000px;height:auto;margin:10px 0;"></canvas>
					<p class="text-muted" id="ch-note" style="font-size:12px;"></p>
				</div>
			</div>

			<!-- RINGKASAN POSISI & JURNAL TERBARU -->
			<div class="row">
				<div class="col-md-5">
					<div class="box box-default">
						<div class="box-header with-border"><h3 class="box-title"><i class="fa fa-pie-chart"></i> Ringkasan Neraca Saldo</h3></div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-striped">
								<tr><td>Total Aset</td><td class="text-right"><strong><?php echo $rp($totals['assets']); ?></strong></td></tr>
								<tr><td>Total Kewajiban</td><td class="text-right"><strong><?php echo $rp($totals['liabilities']); ?></strong></td></tr>
								<tr><td>Total Modal Terbuku</td><td class="text-right"><strong><?php echo $rp($totals['equity']); ?></strong></td></tr>
								<tr><td>Laba Periode Berjalan</td><td class="text-right"><strong><?php echo $rp($net_income_period); ?></strong></td></tr>
								<?php $diff = round($totals['assets'] - $totals['liabilities'] - $totals['equity'] - $net_income, 2); ?>
								<tr class="<?php echo $diff == 0 ? 'success' : 'warning'; ?>">
									<td><strong>Keseimbangan Neraca (A = L + E)</strong></td>
									<td class="text-right"><strong><?php echo $diff == 0 ? '<span class="text-green"><i class="fa fa-check"></i> SEIMBANG</span>' : '<span class="text-red">Selisih: '.$rp($diff).'</span>'; ?></strong></td>
								</tr>
							</table>
						</div>
					</div>
				</div>
				<div class="col-md-7">
					<div class="box box-default">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-history"></i> 10 Jurnal Terkini</h3>
							<div class="box-tools pull-right">
								<a href="<?php echo site_url('main/accounting_unified?tab=journals&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)); ?>" class="btn btn-xs btn-default">Lihat Semua</a>
							</div>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-condensed table-hover">
								<thead>
									<tr><th>Tanggal</th><th>No. Jurnal</th><th>Keterangan</th><th class="text-right">Total Debit</th></tr>
								</thead>
								<tbody>
									<?php foreach ($journals as $j) { ?>
									<tr>
										<td><?php echo html_escape($j->date); ?></td>
										<td><code><?php echo html_escape($j->journal_number); ?></code></td>
										<td><?php echo html_escape($j->description); ?></td>
										<td class="text-right"><?php echo $rp($j->total_debit); ?></td>
									</tr>
									<?php } ?>
									<?php if (empty($journals)) { ?><tr><td colspan="4" class="text-muted text-center" style="padding:15px;">Belum ada jurnal terposting pada rentang ini.</td></tr><?php } ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>

			<!-- ========================================== -->
			<!-- TAB 2: LAPORAN KEUANGAN LENGKAP           -->
			<!-- ========================================== -->
			<?php if ($tab === 'reports') { ?>
			<div class="row">
				<!-- LABA RUGI HIERARKI -->
				<div class="col-md-6">
					<div class="box box-success">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-line-chart"></i> Laporan Laba Rugi</h3>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-condensed table-hover">
								<thead class="table-header-dark">
									<tr><th>Akun</th><th class="text-right">Periode Ini</th><th class="text-right">Kumulatif</th></tr>
								</thead>
								<tbody>
									<tr class="info"><td colspan="3"><strong>1. PENDAPATAN</strong></td></tr>
									<?php $render_tree($tree['income'], 0); ?>
									<tr style="background:#e8f8f5;">
										<th>Total Pendapatan</th>
										<th class="text-right"><?php echo $rp($sums['income']['movement']); ?></th>
										<th class="text-right"><?php echo $rp($sums['income']['balance']); ?></th>
									</tr>
									<tr class="info"><td colspan="3"><strong>2. BEBAN & HPP</strong></td></tr>
									<?php $render_tree($tree['expense'], 0); ?>
									<tr style="background:#fdebd0;">
										<th>Total Beban</th>
										<th class="text-right"><?php echo $rp($sums['expense']['movement']); ?></th>
										<th class="text-right"><?php echo $rp($sums['expense']['balance']); ?></th>
									</tr>
								</tbody>
								<tfoot>
									<tr style="background:#d4efdf; font-size:15px;">
										<th>LABA / (RUGI) BERSIH</th>
										<th class="text-right"><?php echo $rp($net_income_period); ?></th>
										<th class="text-right"><?php echo $rp($net_income); ?></th>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>

				<!-- NERACA SALDO HIERARKI -->
				<div class="col-md-6">
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-balance-scale"></i> Neraca Saldo (Balance Sheet)</h3>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-condensed table-hover">
								<thead class="table-header-dark">
									<tr><th>Akun</th><th class="text-right">Mutasi Periode</th><th class="text-right">Saldo Akhir</th></tr>
								</thead>
								<tbody>
									<tr class="info"><td colspan="3"><strong>ASET</strong></td></tr>
									<?php $render_tree($tree['asset'], 0); ?>
									<tr style="background:#f4f6f7;">
										<th>Total Aset</th>
										<th class="text-right"><?php echo $rp($sums['asset']['movement']); ?></th>
										<th class="text-right"><?php echo $rp($sums['asset']['balance']); ?></th>
									</tr>

									<tr class="info"><td colspan="3"><strong>KEWAJIBAN</strong></td></tr>
									<?php $render_tree($tree['liability'], 0); ?>
									<tr style="background:#f4f6f7;">
										<th>Total Kewajiban</th>
										<th class="text-right"><?php echo $rp($sums['liability']['movement']); ?></th>
										<th class="text-right"><?php echo $rp($sums['liability']['balance']); ?></th>
									</tr>

									<tr class="info"><td colspan="3"><strong>MODAL (EQUITY)</strong></td></tr>
									<?php $render_tree($tree['equity'], 0); ?>
									<tr>
										<td style="padding-left:8px;"><em>Laba Berjalan (Pendapatan − Beban)</em></td>
										<td class="text-right"><?php echo $rp($net_income_period); ?></td>
										<td class="text-right"><?php echo $rp($net_income); ?></td>
									</tr>
									<tr style="background:#f4f6f7;">
										<th>Total Modal + Laba Berjalan</th>
										<th class="text-right"><?php echo $rp($sums['equity']['movement'] + $net_income_period); ?></th>
										<th class="text-right"><?php echo $rp($sums['equity']['balance'] + $net_income); ?></th>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<!-- ARUS KAS & RINGKASAN PPN (DARI ACCOUNTING LAMA) -->
			<div class="row">
				<div class="col-md-6">
					<div class="box box-info">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-money"></i> Arus Kas Terklasifikasi</h3>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-striped">
								<thead><tr><th>Aktivitas</th><th class="text-right">Arus Kas</th></tr></thead>
								<tbody>
									<tr><td>Aktivitas Operasi</td><td class="text-right"><?php echo $rp($cashflow['operating']); ?></td></tr>
									<tr><td>Aktivitas Investasi</td><td class="text-right"><?php echo $rp($cashflow['investing']); ?></td></tr>
									<tr><td>Aktivitas Pendanaan</td><td class="text-right"><?php echo $rp($cashflow['financing']); ?></td></tr>
									<tr><td>Internal / Tidak Terklasifikasi</td><td class="text-right"><?php echo $rp($cashflow['unclassified']); ?></td></tr>
								</tbody>
								<tfoot>
									<tr class="info">
										<th>Perubahan Bersih Kas & Bank</th>
										<th class="text-right"><?php echo $rp($cash_movement); ?></th>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>

				<div class="col-md-6">
					<div class="box box-warning">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-calculator"></i> Ringkasan PPN dari Jurnal</h3>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-striped">
								<thead><tr><th>Akun Pajak</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Mutasi Bersih</th></tr></thead>
								<tbody>
									<?php foreach ($vat_accounts as $va) { ?>
									<tr>
										<td><code><?php echo html_escape($va->code); ?></code> <?php echo html_escape($va->name); ?></td>
										<td class="text-right"><?php echo $rp($va->debit); ?></td>
										<td class="text-right"><?php echo $rp($va->credit); ?></td>
										<td class="text-right"><?php echo $rp($va->movement); ?></td>
									</tr>
									<?php } ?>
								</tbody>
								<tfoot>
									<tr class="warning">
										<th colspan="3">PPN Keluaran − PPN Masukan</th>
										<th class="text-right"><?php echo $rp($vat_summary['output'] - $vat_summary['input']); ?></th>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>

			<!-- ========================================== -->
			<!-- TAB 3: BUKU BESAR (REGISTER)              -->
			<!-- ========================================== -->
			<?php if ($tab === 'ledger') { ?>
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-book"></i> Buku Besar: <?php echo $selected_account ? html_escape($selected_account->code.' — '.$selected_account->name) : 'Semua Akun'; ?></h3>
					<div class="box-tools pull-right">
						<form class="form-inline" method="get" action="<?php echo site_url('main/accounting_unified'); ?>">
							<input type="hidden" name="tab" value="ledger">
							<input type="hidden" name="start_date" value="<?php echo html_escape($start_date); ?>">
							<input type="hidden" name="end_date" value="<?php echo html_escape($end_date); ?>">
							<label>Pilih Akun:&nbsp;</label>
							<select class="form-control input-sm" name="account_id" onchange="this.form.submit()">
								<?php foreach ($account_options as $opt) { ?>
								<option value="<?php echo $opt['id']; ?>" <?php echo $selected_account && (int)$selected_account->id === (int)$opt['id'] ? 'selected' : ''; ?>>
									<?php echo html_escape($opt['name']); ?>
								</option>
								<?php } ?>
							</select>
						</form>
					</div>
				</div>
				<div class="box-body table-responsive">
					<?php if ($selected_account) { ?>
					<div class="well well-sm" style="background:#fcfcfc;">
						<strong>Akun:</strong> <?php echo html_escape($selected_account->code.' '.$selected_account->name); ?> &nbsp;|&nbsp;
						<strong>Tipe:</strong> <?php echo ucfirst($selected_account->type); ?> &nbsp;|&nbsp;
						<strong>Saldo Normal:</strong> <?php echo ucfirst($selected_account->normal_balance); ?> &nbsp;|&nbsp;
						<strong>Saldo Awal (sebelum <?php echo html_escape($start_date); ?>):</strong> <span class="badge bg-gray"><?php echo $rp($ledger_opening); ?></span> &nbsp;|&nbsp;
						<strong>Saldo Akhir:</strong> <span class="badge bg-blue"><?php echo $rp($ledger_closing); ?></span>
					</div>

					<table class="table table-bordered table-striped table-hover">
						<thead>
							<tr class="table-header-dark">
								<th>Tanggal</th>
								<th>No. Jurnal</th>
								<th>Referensi</th>
								<th>Keterangan / Memo</th>
								<th>Lawan Akun</th>
								<th class="text-right">Debit</th>
								<th class="text-right">Kredit</th>
								<th class="text-right">Saldo Berjalan</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td colspan="7"><em>Saldo Awal per <?php echo html_escape($start_date); ?></em></td>
								<td class="text-right"><strong><?php echo $rp($ledger_opening); ?></strong></td>
							</tr>
							<?php foreach ($ledger_rows as $row) { ?>
							<tr>
								<td><?php echo html_escape($row->date); ?></td>
								<td><code><?php echo html_escape($row->journal_number); ?></code></td>
								<td><?php echo html_escape($row->reference_type.($row->reference_id ? ':'.$row->reference_id : '')); ?></td>
								<td><?php echo html_escape($row->memo ? $row->memo : $row->description); ?></td>
								<td><small class="text-muted"><?php echo html_escape($row->counter ? $row->counter : '-'); ?></small></td>
								<td class="text-right"><?php echo (float)$row->debit > 0 ? $rp($row->debit) : '-'; ?></td>
								<td class="text-right"><?php echo (float)$row->credit > 0 ? $rp($row->credit) : '-'; ?></td>
								<td class="text-right"><strong><?php echo $rp($row->running_balance); ?></strong></td>
							</tr>
							<?php } ?>
							<?php if (empty($ledger_rows)) { ?>
							<tr><td colspan="8" class="text-muted text-center" style="padding:20px;">Tidak ada mutasi transaksi pada rentang tanggal ini.</td></tr>
							<?php } ?>
						</tbody>
						<tfoot>
							<tr style="background:#eaf2f8; font-size:14px;">
								<th colspan="7">Saldo Akhir per <?php echo html_escape($end_date); ?></th>
								<th class="text-right"><?php echo $rp($ledger_closing); ?></th>
							</tr>
						</tfoot>
					</table>
					<?php } ?>
				</div>
			</div>
			<?php } ?>

			<!-- ========================================== -->
			<!-- TAB 4: DAFTAR JURNAL UMUM                 -->
			<!-- ========================================== -->
			<?php if ($tab === 'journals') { ?>
			<div class="box box-default">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-list-alt"></i> Jurnal Umum Terposting (<?php echo number_format($journal_count); ?> transaksi)</h3>
				</div>
				<div class="box-body">
					<?php foreach ($journals as $j) { ?>
					<div class="table-responsive" style="margin-bottom:15px;">
						<table class="table table-bordered table-condensed">
							<thead>
								<tr class="info">
									<th colspan="4">
										<i class="fa fa-bookmark"></i> <strong><?php echo html_escape($j->journal_number); ?></strong> &nbsp;|&nbsp;
										<i class="fa fa-calendar"></i> <?php echo html_escape($j->date); ?> &nbsp;|&nbsp;
										<span class="label label-primary"><?php echo html_escape($j->reference_type.':'.$j->reference_id); ?></span>
										<br><small><?php echo html_escape($j->description); ?></small>
									</th>
								</tr>
								<tr><th>Kode & Nama Akun</th><th>Memo Baris</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr>
							</thead>
							<tbody>
								<?php if (isset($journal_items[(int) $j->id])) { foreach ($journal_items[(int) $j->id] as $line) { ?>
								<tr>
									<td><code><?php echo html_escape($line->code); ?></code> <?php echo html_escape($line->name); ?></td>
									<td><?php echo html_escape($line->memo); ?></td>
									<td class="text-right"><?php echo (float)$line->debit > 0 ? $rp($line->debit) : '-'; ?></td>
									<td class="text-right"><?php echo (float)$line->credit > 0 ? $rp($line->credit) : '-'; ?></td>
								</tr>
								<?php }} ?>
							</tbody>
							<tfoot>
								<tr style="background:#fcfcfc;">
									<th colspan="2" class="text-right">Total:</th>
									<th class="text-right"><?php echo $rp($j->total_debit); ?></th>
									<th class="text-right"><?php echo $rp($j->total_credit); ?></th>
								</tr>
							</tfoot>
						</table>
					</div>
					<?php } ?>
					<?php if (empty($journals)) { ?>
					<p class="text-muted text-center" style="padding:30px;">Tidak ada jurnal pada rentang tanggal ini.</p>
					<?php } ?>
				</div>
			</div>
			<?php } ?>

			<!-- ========================================== -->
			<!-- TAB 5: INPUT TRANSAKSI (3 METODE LENGKAP) -->
			<!-- ========================================== -->
			<?php if ($tab === 'input') { ?>
			<div class="row">
				<!-- METODE 1: TRANSFER CEPAT 2 AKUN -->
				<div class="col-md-5">
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-exchange"></i> Cara 1: Transfer Kas / Akun Cepat</h3>
						</div>
						<form method="post" action="<?php echo site_url('main/accounting_unified/post_transfer'); ?>">
							<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
							<div class="box-body">
								<p class="help-block">Cocok untuk transaksi sederhana 2 akun (misal: setor kas ke bank, bayar beban operasional, tarik tunai).</p>
								<div class="form-group">
									<label>Tanggal</label>
									<input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
								</div>
								<div class="form-group">
									<label>Keterangan</label>
									<input type="text" name="description" class="form-control" placeholder="Contoh: Setor kas harian ke bank" required maxlength="255">
								</div>
								<div class="form-group">
									<label>Dari Akun (Kredit)</label>
									<select name="from_account" class="form-control" required>
										<option value="">-- Pilih Akun Asal --</option>
										<?php foreach ($account_options as $opt) { ?>
										<option value="<?php echo $opt['id']; ?>"><?php echo html_escape($opt['name']); ?></option>
										<?php } ?>
									</select>
								</div>
								<div class="form-group">
									<label>Ke Akun (Debit)</label>
									<select name="to_account" class="form-control" required>
										<option value="">-- Pilih Akun Tujuan --</option>
										<?php foreach ($account_options as $opt) { ?>
										<option value="<?php echo $opt['id']; ?>"><?php echo html_escape($opt['name']); ?></option>
										<?php } ?>
									</select>
								</div>
								<div class="form-group">
									<label>Nominal (Rp)</label>
									<input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required autocomplete="off">
								</div>
							</div>
							<div class="box-footer">
								<button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Posting Transfer</button>
							</div>
						</form>
					</div>
				</div>

				<!-- METODE 2: JURNAL MULTI BARIS (BERPASANGAN DEBIT-KREDIT) -->
				<div class="col-md-7">
					<div class="box box-success">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-pencil-square"></i> Cara 2: Formulir Jurnal Majemuk</h3>
						</div>
						<form method="post" action="<?php echo site_url('main/accounting_unified/post_journal'); ?>" id="multi-journal-form">
							<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
							<div class="box-body">
								<p class="help-block">Cocok untuk transaksi dengan banyak baris (misal: pembelian dengan PPN, penggajian, penyesuaian).</p>
								<div class="row">
									<div class="col-sm-4 form-group">
										<label>Tanggal</label>
										<input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
									</div>
									<div class="col-sm-8 form-group">
										<label>Keterangan Jurnal</label>
										<input type="text" name="description" class="form-control" placeholder="Keterangan umum transaksi" required maxlength="2000">
									</div>
								</div>

								<div class="table-responsive">
									<table class="table table-bordered table-condensed" id="multi-journal-lines">
										<thead>
											<tr class="table-header-dark">
												<th>Akun</th>
												<th>Memo Baris</th>
												<th width="140">Debit (Rp)</th>
												<th width="140">Kredit (Rp)</th>
												<th width="40"></th>
											</tr>
										</thead>
										<tbody>
											<?php for ($i = 0; $i < 2; $i++) { ?>
											<tr>
												<td>
													<select class="form-control input-sm" name="account_id[]" required>
														<option value="">Pilih akun</option>
														<?php foreach ($account_options as $opt) { ?>
														<option value="<?php echo $opt['id']; ?>"><?php echo html_escape($opt['name']); ?></option>
														<?php } ?>
													</select>
												</td>
												<td><input type="text" name="memo[]" class="form-control input-sm" placeholder="Memo"></td>
												<td><input type="text" name="debit[]" value="0" class="form-control input-sm j-amount rupiah-input text-right" required autocomplete="off"></td>
												<td><input type="text" name="credit[]" value="0" class="form-control input-sm j-amount rupiah-input text-right" required autocomplete="off"></td>
												<td><button type="button" class="btn btn-xs btn-danger remove-line" <?php echo $i < 2 ? 'disabled' : ''; ?>><i class="fa fa-trash"></i></button></td>
											</tr>
											<?php } ?>
										</tbody>
										<tfoot>
											<tr class="info">
												<th colspan="2" class="text-right">Total:</th>
												<th id="tot-debit">Rp 0,00</th>
												<th id="tot-credit">Rp 0,00</th>
												<th></th>
											</tr>
										</tfoot>
									</table>
								</div>

								<button type="button" class="btn btn-sm btn-default" id="add-multi-line"><i class="fa fa-plus"></i> Tambah Baris</button>
								<span id="balance-badge" class="pull-right" style="margin-top:6px; font-weight:bold;"></span>
							</div>
							<div class="box-footer">
								<button type="submit" class="btn btn-success" id="btn-post-multi"><i class="fa fa-check"></i> Posting Jurnal Majemuk</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- METODE 3: SPREADSHEET BATCH GRID (ACCOUNTING V3) -->
			<div class="box box-warning grid-workspace" style="margin-top:15px;">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-table"></i> Cara 3: Input Spreadsheet Excel (Batch Massal)</h3>
				</div>
				<div class="box-body">
					<p class="help-block">Ketik seperti di Excel. Anda dapat mengisi beberapa jurnal sekaligus (maks. 50 baris), lalu simpan bersamaan.</p>
					<form method="post" action="<?php echo site_url('main/accounting_unified/post_grid'); ?>" id="grid-post-form">
						<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
						<input type="hidden" name="rows" id="grid-rows-input">
						<div id="unified-grid"></div>
						<div style="margin-top:15px;">
							<button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Simpan Semua Baris Spreadsheet</button>
						</div>
					</form>
				</div>
			</div>
			<?php } ?>

			<!-- ========================================== -->
			<!-- TAB 6: BAGAN AKUN (COA)                   -->
			<!-- ========================================== -->
			<?php if ($tab === 'accounts') { ?>
			<div class="row">
				<div class="col-md-8">
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-sitemap"></i> Bagan Akun (Chart of Accounts)</h3>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-striped table-hover">
								<thead>
									<tr class="table-header-dark">
										<th>Kode</th>
										<th>Nama Akun</th>
										<th>Kelompok</th>
										<th>Saldo Normal</th>
										<th class="text-right">Saldo Saat Ini</th>
										<th>Aksi</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($by_id as $acc) { ?>
									<tr>
										<td><code><?php echo html_escape($acc->code); ?></code></td>
										<td><strong><?php echo html_escape($acc->name); ?></strong></td>
										<td><span class="label label-default"><?php echo ucfirst($acc->type); ?></span></td>
										<td><?php echo ucfirst($acc->normal_balance); ?></td>
										<td class="text-right"><?php echo $rp($acc->balance); ?></td>
										<td>
											<a href="<?php echo site_url('main/accounting_unified?tab=ledger&account_id='.(int)$acc->id.'&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)); ?>" class="btn btn-xs btn-default">
												<i class="fa fa-book"></i> Buku Besar
											</a>
										</td>
									</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<div class="col-md-4">
					<div class="box box-default">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-plus"></i> Tambah Akun Baru</h3>
						</div>
						<form method="post" action="<?php echo site_url('main/accounting_unified/create_account'); ?>">
							<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
							<div class="box-body">
								<div class="form-group">
									<label>Kode Akun</label>
									<input type="text" name="code" class="form-control" placeholder="Contoh: 1108" required maxlength="32">
								</div>
								<div class="form-group">
									<label>Nama Akun</label>
									<input type="text" name="name" class="form-control" placeholder="Contoh: Kas Bank Operasional B" required maxlength="128">
								</div>
								<div class="form-group">
									<label>Kelompok Akun</label>
									<select name="group" class="form-control" required>
										<?php foreach ($groups as $gk => $gval) { ?>
										<option value="<?php echo $gk; ?>"><?php echo html_escape($gval[0]); ?></option>
										<?php } ?>
									</select>
								</div>
								<div class="form-group">
									<label>Akun Induk (Opsional)</label>
									<select name="parent_id" class="form-control">
										<option value="0">-- Tanpa Induk --</option>
										<?php foreach ($account_options as $opt) { ?>
										<option value="<?php echo $opt['id']; ?>"><?php echo html_escape($opt['name']); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<div class="box-footer">
								<button type="submit" class="btn btn-primary btn-block"><i class="fa fa-save"></i> Buat Akun</button>
							</div>
						</form>
					</div>
				</div>
			</div>
			<?php } ?>

			<!-- ========================================== -->
			<!-- TAB 7: PERIODE AKUNTANSI & CLOSING        -->
			<!-- ========================================== -->
			<?php if ($tab === 'periods') { ?>
			<?php if ($period_notice) { ?><div class="alert alert-success"><?php echo html_escape($period_notice); ?></div><?php } ?>
			<?php if ($period_error) { ?><div class="alert alert-danger"><?php echo html_escape($period_error); ?></div><?php } ?>

			<div class="row">
				<div class="col-md-4">
					<div class="box box-warning">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-calendar-plus-o"></i> Buat Periode Buku</h3>
						</div>
						<form method="post" action="<?php echo site_url('main/accounting_unified/create_period'); ?>">
							<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
							<div class="box-body">
								<div class="form-group">
									<label>Nama Periode</label>
									<input type="text" name="period_name" class="form-control" placeholder="Contoh: November 2026" required maxlength="32">
								</div>
								<div class="form-group">
									<label>Tanggal Mulai</label>
									<input type="date" name="period_start_date" class="form-control" required>
								</div>
								<div class="form-group">
									<label>Tanggal Akhir</label>
									<input type="date" name="period_end_date" class="form-control" required>
								</div>
							</div>
							<div class="box-footer">
								<button type="submit" class="btn btn-warning btn-block"><i class="fa fa-plus"></i> Simpan Periode</button>
							</div>
						</form>
					</div>
				</div>

				<div class="col-md-8">
					<div class="box box-default">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-history"></i> Daftar Periode Akuntansi</h3>
						</div>
						<div class="box-body no-padding table-responsive">
							<table class="table table-striped table-hover">
								<thead>
									<tr class="table-header-dark">
										<th>Nama Periode</th>
										<th>Rentang Waktu</th>
										<th>Status</th>
										<th>Waktu Tutup</th>
										<th>Aksi</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($accounting_periods as $p) { ?>
									<tr>
										<td><strong><?php echo html_escape($p->period_name); ?></strong></td>
										<td><?php echo html_escape($p->start_date.' s.d. '.$p->end_date); ?></td>
										<td>
											<?php if ((int)$p->is_closed === 1) { ?>
											<span class="label label-danger"><i class="fa fa-lock"></i> Terkunci</span>
											<?php } else { ?>
											<span class="label label-success"><i class="fa fa-unlock"></i> Terbuka</span>
											<?php } ?>
										</td>
										<td><?php echo $p->closed_at ? html_escape($p->closed_at) : '-'; ?></td>
										<td>
											<a href="<?php echo site_url('main/accounting_unified?tab=reports&start_date='.rawurlencode($p->start_date).'&end_date='.rawurlencode($p->end_date)); ?>" class="btn btn-xs btn-default">
												<i class="fa fa-file-text-o"></i> Lihat Laporan
											</a>
											<?php if ((int)$p->is_closed !== 1 && $p->end_date < date('Y-m-d')) { ?>
											<form method="post" action="<?php echo site_url('main/accounting_unified/close_period'); ?>" style="display:inline-block;">
												<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
												<input type="hidden" name="period_id" value="<?php echo (int)$p->id; ?>">
												<button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Kunci posting jurnal pada rentang ini? Tindakan ini tidak dapat dibatalkan.');">
													<i class="fa fa-lock"></i> Kunci Periode
												</button>
											</form>
											<?php } ?>
										</td>
									</tr>
									<?php } ?>
									<?php if (empty($accounting_periods)) { ?>
									<tr><td colspan="5" class="text-muted text-center" style="padding:20px;">Belum ada periode akuntansi.</td></tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<?php } ?>

		</div>
	</div>
</div>

<!-- TEMPLATE ROW UNTUK JURNAL MULTI -->
<template id="tmpl-journal-line">
	<tr>
		<td>
			<select class="form-control input-sm" name="account_id[]" required>
				<option value="">Pilih akun</option>
				<?php foreach ($account_options as $opt) { ?>
				<option value="<?php echo $opt['id']; ?>"><?php echo html_escape($opt['name']); ?></option>
				<?php } ?>
			</select>
		</td>
		<td><input type="text" name="memo[]" class="form-control input-sm" placeholder="Memo"></td>
		<td><input type="text" name="debit[]" value="0" class="form-control input-sm j-amount rupiah-input text-right" required autocomplete="off"></td>
		<td><input type="text" name="credit[]" value="0" class="form-control input-sm j-amount rupiah-input text-right" required autocomplete="off"></td>
		<td><button type="button" class="btn btn-xs btn-danger remove-line"><i class="fa fa-trash"></i></button></td>
	</tr>
</template>

<script>
// ==========================================
// 0. FORMAT RUPIAH / RIBUAN INPUT HELPER
// ==========================================
function formatRupiahDisplay(numStr) {
	if (numStr === '' || numStr === null || numStr === undefined) return '';
	var clean = String(numStr).replace(/[^\d]/g, '');
	if (clean === '') return '';
	return new Intl.NumberFormat('id-ID').format(parseInt(clean, 10));
}

function parseRupiahRaw(str) {
	if (!str) return 0;
	var clean = String(str).replace(/[^\d]/g, '');
	return clean ? parseFloat(clean) : 0;
}

$(document).on('input', '.rupiah-input', function () {
	var cursor = this.selectionStart;
	var raw = $(this).val();
	var formatted = formatRupiahDisplay(raw);
	$(this).val(formatted);
});

// ==========================================
// 1. CHART DASHBOARD & SANKEY JS
// ==========================================
<?php if ($tab === 'dashboard') { ?>
(function () {
	// --- SANKEY DIAGRAM ---
	var sankeyUrl = <?php echo json_encode(site_url('main/accounting_unified/sankey_data')); ?>;
	var sCanvas = document.getElementById('sankey-canvas');
	var sCtx = sCanvas ? sCanvas.getContext('2d') : null;

	function fmtRp(n) {
		return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n);
	}

	function drawSankey(data) {
		if (!sCtx || !data || !data.links || !data.links.length) {
			if (sCtx) {
				sCtx.clearRect(0, 0, sCanvas.width, sCanvas.height);
				sCtx.fillStyle = '#777';
				sCtx.font = '14px sans-serif';
				sCtx.fillText('Belum ada transaksi operasional/pendapatan pada rentang ini untuk digambar ke diagram.', 30, 40);
			}
			return;
		}

		var W = sCanvas.width, H = sCanvas.height;
		sCtx.clearRect(0, 0, W, H);

		// Kelompokkan Links ke 2 kolom: Left -> Center -> Right
		// Left: Sumber Pendapatan
		// Center: Total Pendapatan Operasional
		// Right: Alokasi HPP, Beban, Laba Bersih
		var leftLinks = [], rightLinks = [];
		var centerName = 'Total Pendapatan Operasional';

		data.links.forEach(function (l) {
			if (l.to === centerName) {
				leftLinks.push(l);
			} else {
				rightLinks.push(l);
			}
		});

		var totalLeftVal = leftLinks.reduce(function (a, b) { return a + b.value; }, 0) || 1;
		var totalRightVal = rightLinks.reduce(function (a, b) { return a + b.value; }, 0) || 1;

		var padTop = 35, padBottom = 30;
		var availH = H - padTop - padBottom;

		var colX = { left: 40, center: 430, right: 780 };
		var nodeWidth = 22;

		// Hitung posisi vertikal node kiri
		var curYLeft = padTop;
		var gapLeft = Math.max(8, (availH - (availH * 0.85)) / Math.max(1, leftLinks.length));
		var usableHLeft = availH - gapLeft * (leftLinks.length - 1);
		leftLinks.forEach(function (l) {
			var h = Math.max(14, (l.value / totalLeftVal) * usableHLeft);
			l.srcY = curYLeft;
			l.srcH = h;
			curYLeft += h + gapLeft;
		});

		// Node tengah
		var centerH = Math.min(availH * 0.9, 280);
		var centerY = padTop + (availH - centerH) / 2;

		// Hitung posisi vertikal node kanan
		var curYRight = padTop;
		var gapRight = Math.max(8, (availH - (availH * 0.85)) / Math.max(1, rightLinks.length));
		var usableHRight = availH - gapRight * (rightLinks.length - 1);
		rightLinks.forEach(function (l) {
			var h = Math.max(14, (l.value / totalRightVal) * usableHRight);
			l.destY = curYRight;
			l.destH = h;
			curYRight += h + gapRight;
		});

		var colorsLeft = ['#00a65a', '#00c0ef', '#39cccc', '#3c8dbc', '#2e7d32'];
		var colorsRight = ['#f39c12', '#dd4b39', '#e74c3c', '#d81b60', '#8e44ad', '#27ae60', '#34495e'];

		// Gambar pita aliran kiri -> tengah
		var centerLeftCursorY = centerY;
		leftLinks.forEach(function (l, idx) {
			var flowHCenter = (l.value / totalLeftVal) * centerH;
			var color = colorsLeft[idx % colorsLeft.length];

			sCtx.beginPath();
			sCtx.moveTo(colX.left + nodeWidth, l.srcY);
			sCtx.bezierCurveTo(
				colX.left + nodeWidth + 160, l.srcY,
				colX.center - 160, centerLeftCursorY,
				colX.center, centerLeftCursorY
			);
			sCtx.lineTo(colX.center, centerLeftCursorY + flowHCenter);
			sCtx.bezierCurveTo(
				colX.center - 160, centerLeftCursorY + flowHCenter,
				colX.left + nodeWidth + 160, l.srcY + l.srcH,
				colX.left + nodeWidth, l.srcY + l.srcH
			);
			sCtx.closePath();
			sCtx.fillStyle = color;
			sCtx.globalAlpha = 0.35;
			sCtx.fill();
			sCtx.globalAlpha = 1.0;

			// Node kotak kiri
			sCtx.fillStyle = color;
			sCtx.fillRect(colX.left, l.srcY, nodeWidth, l.srcH);

			// Label kiri
			sCtx.fillStyle = '#222';
			sCtx.font = 'bold 11px sans-serif';
			var labelText = l.from;
			if (labelText.length > 25) labelText = labelText.substring(0, 24) + '…';
			sCtx.fillText(labelText, colX.left, Math.max(16, l.srcY - 4));
			sCtx.font = '11px sans-serif';
			sCtx.fillStyle = '#008744';
			sCtx.fillText(fmtRp(l.value), colX.left, Math.min(H - 6, l.srcY + l.srcH + 12));

			centerLeftCursorY += flowHCenter;
		});

		// Node tengah
		sCtx.fillStyle = '#3c8dbc';
		sCtx.fillRect(colX.center, centerY, nodeWidth, centerH);
		sCtx.font = 'bold 12px sans-serif';
		sCtx.fillStyle = '#1b4f72';
		sCtx.fillText('Pendapatan Operasional', colX.center - 20, Math.max(18, centerY - 6));
		sCtx.font = '11px sans-serif';
		sCtx.fillStyle = '#444';
		sCtx.fillText(fmtRp(data.total_income), colX.center - 10, centerY + centerH + 15);

		// Gambar pita aliran tengah -> kanan
		var centerRightCursorY = centerY;
		rightLinks.forEach(function (l, idx) {
			var flowHCenter = (l.value / totalRightVal) * centerH;
			var isProfit = l.to.indexOf('Laba') !== -1;
			var color = isProfit ? '#00a65a' : colorsRight[idx % colorsRight.length];

			sCtx.beginPath();
			sCtx.moveTo(colX.center + nodeWidth, centerRightCursorY);
			sCtx.bezierCurveTo(
				colX.center + nodeWidth + 160, centerRightCursorY,
				colX.right - 160, l.destY,
				colX.right, l.destY
			);
			sCtx.lineTo(colX.right, l.destY + l.destH);
			sCtx.bezierCurveTo(
				colX.right - 160, l.destY + l.destH,
				colX.center + nodeWidth + 160, centerRightCursorY + flowHCenter,
				colX.center + nodeWidth, centerRightCursorY + flowHCenter
			);
			sCtx.closePath();
			sCtx.fillStyle = color;
			sCtx.globalAlpha = 0.35;
			sCtx.fill();
			sCtx.globalAlpha = 1.0;

			// Node kotak kanan
			sCtx.fillStyle = color;
			sCtx.fillRect(colX.right, l.destY, nodeWidth, l.destH);

			// Label kanan
			sCtx.fillStyle = '#222';
			sCtx.font = 'bold 11px sans-serif';
			var labelRight = l.to;
			if (labelRight.length > 28) labelRight = labelRight.substring(0, 27) + '…';
			sCtx.fillText(labelRight, colX.right + nodeWidth + 8, l.destY + Math.min(12, l.destH / 2 + 3));
			sCtx.font = '11px sans-serif';
			sCtx.fillStyle = isProfit ? '#00a65a' : '#c0392b';
			sCtx.fillText(fmtRp(l.value) + (isProfit ? ' (Surplus)' : ''), colX.right + nodeWidth + 8, l.destY + Math.min(26, l.destH / 2 + 16));

			centerRightCursorY += flowHCenter;
		});

		// Summary text bawah
		var sumEl = document.getElementById('sankey-summary');
		if (sumEl) {
			sumEl.innerHTML = '<span class="label label-success" style="font-size:12px;"><i class="fa fa-arrow-down"></i> Total Pendapatan: ' + fmtRp(data.total_income) + '</span> ' +
				'<span class="label label-danger" style="font-size:12px; margin-left:6px;"><i class="fa fa-arrow-up"></i> Total Beban: ' + fmtRp(data.total_expense) + '</span> ' +
				'<span class="label ' + (data.net_profit >= 0 ? 'label-primary' : 'label-warning') + '" style="font-size:12px; margin-left:6px;"><i class="fa fa-balance-scale"></i> ' + (data.net_profit >= 0 ? 'Laba Bersih' : 'Defisit') + ': ' + fmtRp(Math.abs(data.net_profit)) + '</span>';
		}
	}

	function loadSankey() {
		var sDate = <?php echo json_encode($start_date); ?>;
		var eDate = <?php echo json_encode($end_date); ?>;
		fetch(sankeyUrl + '?start_date=' + encodeURIComponent(sDate) + '&end_date=' + encodeURIComponent(eDate), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) { drawSankey(res); })
			.catch(function () {});
	}
	loadSankey();

	// --- CHART CANVAS TREN & BREAKDOWN ---
	var month = <?php echo json_encode($month); ?>;
	var url = <?php echo json_encode(site_url('main/accounting_unified/chart_data')); ?>;
	var palette = ['#00a65a', '#3c8dbc', '#f39c12', '#dd4b39', '#605ca8', '#00c0ef', '#d81b60', '#39cccc', '#ff851b', '#001f3f'];
	var canvas = document.getElementById('ch-canvas');
	if (!canvas) return;
	var ctx = canvas.getContext('2d');
	var ids = ['ch-mode', 'ch-group', 'ch-type', 'ch-months', 'ch-color', 'ch-values'];
	var el = {};
	ids.forEach(function (id) { el[id] = document.getElementById(id); });
	var last = null;

	function fmt(v) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(v); }

	function draw() {
		if (!last) return;
		var W = canvas.width, H = canvas.height, type = el['ch-type'].value, color = el['ch-color'].value;
		var labels = last.labels, values = last.values, showVals = el['ch-values'].checked;
		ctx.clearRect(0, 0, W, H);
		ctx.font = '12px sans-serif';
		if (!values.length) { ctx.fillStyle = '#777'; ctx.fillText('Tidak ada data untuk pilihan ini.', 20, 30); return; }

		if (type === 'pie') {
			var pos = values.map(function (v) { return Math.max(v, 0); });
			var total = pos.reduce(function (a, b) { return a + b; }, 0);
			if (!total) { ctx.fillStyle = '#777'; ctx.fillText('Nilai nol atau negatif tidak dapat digambar sebagai pai.', 20, 30); return; }
			var cx = 200, cy = H / 2, r = 130, angle = -Math.PI / 2;
			pos.forEach(function (v, i) {
				var a = v / total * Math.PI * 2;
				ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, r, angle, angle + a); ctx.closePath();
				ctx.fillStyle = palette[i % palette.length]; ctx.fill(); angle += a;
				ctx.fillRect(400, 20 + i * 24, 14, 14);
				ctx.fillStyle = '#333';
				ctx.fillText(labels[i] + (showVals ? ' — Rp ' + fmt(values[i]) + ' (' + Math.round(v / total * 100) + '%)' : ''), 422, 32 + i * 24);
			});
			return;
		}

		var L = 90, R = 20, T = 20, B = 50, pw = W - L - R, ph = H - T - B;
		var max = Math.max.apply(null, values.concat([0])), min = Math.min.apply(null, values.concat([0]));
		if (max === min) { max = min + 1; }
		function y(v) { return T + ph - (v - min) / (max - min) * ph; }
		ctx.strokeStyle = '#eee'; ctx.fillStyle = '#666'; ctx.textAlign = 'right';
		for (var g = 0; g <= 4; g++) {
			var gv = min + (max - min) * g / 4;
			ctx.beginPath(); ctx.moveTo(L, y(gv)); ctx.lineTo(W - R, y(gv)); ctx.stroke();
			ctx.fillText('Rp ' + fmt(gv), L - 6, y(gv) + 4);
		}
		var step = pw / values.length;
		ctx.textAlign = 'center';
		values.forEach(function (v, i) {
			var x = L + step * i + step / 2;
			ctx.fillStyle = '#666';
			var lab = labels[i].length > 14 ? labels[i].slice(0, 13) + '…' : labels[i];
			ctx.fillText(lab, x, H - B + 18);
			if (type === 'bar') {
				ctx.fillStyle = color;
				var bw = Math.min(step * 0.6, 60);
				ctx.fillRect(x - bw / 2, Math.min(y(v), y(0)), bw, Math.abs(y(v) - y(0)));
			}
			if (showVals) { ctx.fillStyle = '#111'; ctx.fillText('Rp ' + fmt(v), x, y(v) + (v >= 0 ? -6 : 14)); }
		});
		if (type === 'line') {
			ctx.strokeStyle = color; ctx.lineWidth = 2.5; ctx.beginPath();
			values.forEach(function (v, i) {
				var x = L + step * i + step / 2;
				if (i) ctx.lineTo(x, y(v)); else ctx.moveTo(x, y(v));
			});
			ctx.stroke(); ctx.lineWidth = 1; ctx.fillStyle = color;
			values.forEach(function (v, i) { ctx.beginPath(); ctx.arc(L + step * i + step / 2, y(v), 4, 0, 7); ctx.fill(); });
		}
		ctx.textAlign = 'left';
	}

	function load() {
		var q = '?month=' + encodeURIComponent(month) + '&mode=' + el['ch-mode'].value +
			'&group=' + el['ch-group'].value + '&months=' + encodeURIComponent(el['ch-months'].value || 6);
		fetch(url + q, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
			last = d;
			document.getElementById('ch-note').textContent = el['ch-mode'].value === 'trend'
				? 'Tren per bulan sampai ' + month + '. (Aset/Kewajiban/Modal = saldo akhir; Pendapatan/Beban = mutasi periode).'
				: 'Maksimal 12 akun terbesar pada periode ini.';
			draw();
		}).catch(function () { document.getElementById('ch-note').textContent = 'Gagal memuat data grafik.'; });
	}

	['ch-mode', 'ch-group', 'ch-months'].forEach(function (id) { el[id].addEventListener('change', load); });
	['ch-type', 'ch-color', 'ch-values'].forEach(function (id) { el[id].addEventListener('input', draw); });
	el['ch-mode'].addEventListener('change', function () { el['ch-months'].disabled = el['ch-mode'].value === 'breakdown'; });
	load();
}());
<?php } ?>

// ==========================================
// 2. FORM JURNAL MULTI BARIS DENGAN FORMAT RIBUAN
// ==========================================
<?php if ($tab === 'input') { ?>
(function () {
	var tbody = document.querySelector('#multi-journal-lines tbody');
	var tmpl = document.getElementById('tmpl-journal-line');
	var addBtn = document.getElementById('add-multi-line');
	var form = document.getElementById('multi-journal-form');
	if (!tbody || !tmpl) return;

	function calc() {
		var d = 0, c = 0;
		tbody.querySelectorAll('tr').forEach(function (tr) {
			var deb = parseRupiahRaw(tr.querySelector('[name="debit[]"]').value);
			var cred = parseRupiahRaw(tr.querySelector('[name="credit[]"]').value);
			d += deb; c += cred;
		});
		document.getElementById('tot-debit').textContent = 'Rp ' + d.toLocaleString('id-ID', {minimumFractionDigits: 0});
		document.getElementById('tot-credit').textContent = 'Rp ' + c.toLocaleString('id-ID', {minimumFractionDigits: 0});
		var balanced = d > 0 && Math.abs(d - c) < 0.01;
		var badge = document.getElementById('balance-badge');
		if (balanced) {
			badge.innerHTML = '<span class="text-green"><i class="fa fa-check"></i> Debit & Kredit Seimbang (Rp ' + d.toLocaleString('id-ID') + ')</span>';
		} else {
			badge.innerHTML = '<span class="text-red"><i class="fa fa-times"></i> Belum Seimbang (Selisih: Rp ' + Math.abs(d - c).toLocaleString('id-ID') + ')</span>';
		}
		return balanced;
	}

	addBtn.addEventListener('click', function () {
		if (tbody.children.length < 50) {
			tbody.appendChild(tmpl.content.cloneNode(true));
			tbody.querySelectorAll('.remove-line').forEach(function (btn) {
				btn.disabled = tbody.children.length <= 2;
			});
		}
	});

	tbody.addEventListener('click', function (e) {
		if (e.target.closest('.remove-line') && tbody.children.length > 2) {
			e.target.closest('tr').remove();
			tbody.querySelectorAll('.remove-line').forEach(function (btn) {
				btn.disabled = tbody.children.length <= 2;
			});
			calc();
		}
	});

	tbody.addEventListener('input', calc);
	form.addEventListener('submit', function (e) {
		if (!calc()) {
			e.preventDefault();
			alert('Total debit dan kredit harus seimbang dan lebih dari 0 sebelum diposting.');
		}
	});
	calc();

	// ==========================================
	// 3. JEXCEL SPREADSHEET BATCH
	// ==========================================
	if (typeof jexcel !== 'undefined') {
		var accOpts = <?php echo json_encode($account_options); ?>;
		var gridRows = [];
		for (var k = 0; k < 10; k++) { gridRows.push(['', '', '', '', '']); }
		var grid = jexcel(document.getElementById('unified-grid'), {
			data: gridRows,
			columns: [
				{ type: 'text', title: 'Tanggal (YYYY-MM-DD)', width: 140 },
				{ type: 'text', title: 'Uraian Transaksi', width: 260 },
				{ type: 'dropdown', title: 'Akun Debit', width: 240, source: accOpts },
				{ type: 'dropdown', title: 'Akun Kredit', width: 240, source: accOpts },
				{ type: 'numeric', title: 'Nominal (Rp)', width: 140, mask: '#.##0', decimal: ',' }
			],
			minDimensions: [5, 10],
			allowInsertRow: true,
			allowDeleteRow: true,
			tableOverflow: true,
			tableHeight: '400px'
		});

		document.getElementById('grid-post-form').addEventListener('submit', function (ev) {
			var data = grid.getData();
			var filled = data.filter(function (r) {
				return r.some(function (val) { return String(val == null ? '' : val).trim() !== ''; });
			});
			if (!filled.length) {
				ev.preventDefault();
				alert('Isi minimal satu baris transaksi pada spreadsheet.');
				return;
			}
			document.getElementById('grid-rows-input').value = JSON.stringify(filled);
		});
	}
}());
<?php } ?>
</script>
