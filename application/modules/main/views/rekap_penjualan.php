<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<style>
	#exTab2 {
		width: 100%;
		padding-left: 0;
		padding-right: 0;
	}

	#exTab2 .jexcel_container {
		display: block;
		width: 100% !important;
		box-sizing: border-box;
	}

	#exTab2 .jexcel_content {
		display: block;
		width: auto !important;
		overflow-x: auto;
	}

	#exTab2 .jexcel {
		display: table;
		width: auto !important;
		table-layout: fixed;
	}

	#exTab2 .easy-autocomplete {
		width: 100% !important;
	}

	#exTab2 .easy-autocomplete input {
		width: 100% !important;
	}

	.nota-clickable {
		color: #3c8dbc;
		font-weight: bold;
		text-decoration: underline;
		cursor: pointer;
	}
	.nota-clickable:hover {
		color: #23527c;
		background-color: #f0f7fd;
	}

	.kpi-card {
		border-radius: 4px;
		padding: 12px 15px;
		margin-bottom: 15px;
		box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	}
	.kpi-card h4 {
		margin: 0 0 5px 0;
		font-size: 13px;
		text-transform: uppercase;
		opacity: 0.85;
	}
	.kpi-card .kpi-value {
		font-size: 22px;
		font-weight: bold;
		margin: 0;
	}
</style>

<div class="row">
	<div class="col-md-12">
		<div class="box box-primary">
			<div class="box-header with-border">
				<h3 class="box-title"><i class="fa fa-line-chart"></i> Rekapitulasi & Analisis Penjualan Toko</h3>
				<div class="box-tools pull-right">
					<button type="button" class="btn btn-default btn-sm" onclick="location.reload();">
						<i class="fa fa-refresh"></i> Refresh
					</button>
				</div>
			</div>
			<div class="box-body" style="padding-top: 5px;">
				<div id="exTab2" data-ajax-base="<?php echo html_escape($ajax_base); ?>" data-rekap-base="<?php echo html_escape($rekap_base); ?>">
					<ul class="nav nav-tabs" style="margin-bottom: 15px;">
						<li class="active"><a id="h" href="#harian"><i class="fa fa-calendar"></i> Harian</a></li>
						<li><a id="u" href="#tunai"><i class="fa fa-money"></i> Penjualan Tunai</a></li>
						<li><a id="m" href="#perorangan"><i class="fa fa-user"></i> Perorangan (Anggota)</a></li>
						<li><a id="b" href="#bulanan"><i class="fa fa-calendar-check-o"></i> Bulanan</a></li>
						<li><a id="t" href="#tahunan"><i class="fa fa-bar-chart"></i> Tahunan</a></li>
						<li><a id="an" href="#analitik"><i class="fa fa-pie-chart text-orange"></i> Analisis & Top Produk</a></li>
					</ul>

					<div class="tab-content">
						<!-- TAB 1: HARIAN -->
						<div id="harian" class="tab-pane active">
							<div class="well well-sm" style="background: #fdfdfd; margin-bottom: 15px;">
								<div class="row">
									<div class="col-md-3">
										<label for="tanggal"><i class="fa fa-calendar"></i> Tanggal Penjualan:</label>
										<input type="date" class="form-control input-sm" name="tanggal" id="tanggal">
									</div>
									<div class="col-md-3">
										<label for="filter-metode-harian"><i class="fa fa-credit-card"></i> Metode Pembayaran:</label>
										<select id="filter-metode-harian" class="form-control input-sm">
											<option value="semua">Semua Metode (Tunai & Kredit)</option>
											<option value="tunai">Hanya Tunai (Cash)</option>
											<option value="potong_gaji">Hanya Potong Gaji (Kredit)</option>
										</select>
									</div>
									<div class="col-md-6 text-right" style="padding-top: 24px;">
										<button type="button" class="btn btn-sm btn-success" id="download-excel-harian">
											<i class="fa fa-file-excel-o"></i> Download Excel (.xlsx)
										</button>
										<button type="button" class="btn btn-sm btn-default" id="download-csv-harian">
											<i class="fa fa-file-text-o"></i> Download CSV
										</button>
									</div>
								</div>
							</div>

							<!-- KPI Cards Harian -->
							<div class="row">
								<div class="col-sm-3">
									<div class="kpi-card bg-green">
										<h4>Total Penjualan (Net)</h4>
										<div class="kpi-value" id="kpi-harian-omset">Rp 0</div>
										<small id="kpi-harian-qty">0 Qty terjual</small>
									</div>
								</div>
								<div class="col-sm-3">
									<div class="kpi-card bg-aqua">
										<h4>Total Transaksi / Nota</h4>
										<div class="kpi-value" id="kpi-harian-nota">0 Nota</div>
										<small>Frekuensi belanja kasir</small>
									</div>
								</div>
								<div class="col-sm-3">
									<div class="kpi-card bg-yellow">
										<h4>Total Tunai (Cash)</h4>
										<div class="kpi-value" id="kpi-harian-tunai">Rp 0</div>
										<small id="kpi-harian-tunai-count">0 transaksi tunai</small>
									</div>
								</div>
								<div class="col-sm-3">
									<div class="kpi-card bg-purple">
										<h4>Potong Gaji (Kredit)</h4>
										<div class="kpi-value" id="kpi-harian-kredit">Rp 0</div>
										<small id="kpi-harian-kredit-count">0 transaksi kredit</small>
									</div>
								</div>
							</div>

							<div style="max-width:100%;overflow-x:auto;">
								<div id="harian-sheet"></div>
							</div>
							<p class="text-muted small" style="margin-top: 8px;">
								<i class="fa fa-info-circle"></i> <em>Tip: Klik pada nomor nota (berwarna biru bergaris bawah) di lembar kerja untuk melihat struk / rincian belanja.</em>
							</p>
						</div>

						<!-- TAB 2: PENJUALAN TUNAI -->
						<div id="tunai" class="tab-pane">
							<div class="well well-sm" style="background: #fdfdfd; margin-bottom: 15px;">
								<div class="row">
									<div class="col-md-3">
										<label for="tanggal-tunai"><i class="fa fa-calendar"></i> Tanggal Transaksi:</label>
										<input type="date" class="form-control input-sm" id="tanggal-tunai">
									</div>
									<div class="col-md-9 text-right" style="padding-top: 24px;">
										<button type="button" class="btn btn-sm btn-info" id="btn-open-cash-count" style="margin-right: 5px;">
											<i class="fa fa-calculator"></i> Preview & Hitung Fisik Laci
										</button>
										<button type="button" class="btn btn-sm btn-default" id="download-csv-tunai">
											<i class="fa fa-download"></i> Download CSV
										</button>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-sm-6">
									<div class="panel panel-default">
										<div class="panel-heading"><i class="fa fa-clock-o"></i> Penjualan Tunai Hari Ini</div>
										<div class="panel-body">
											<h3 style="margin-top: 5px; color: #00a65a;" id="tunai-hari-total">Memuat...</h3>
											<div id="tunai-hari-count" class="text-muted"></div>
										</div>
									</div>
								</div>
								<div class="col-sm-6">
									<div class="panel panel-default">
										<div class="panel-heading"><i class="fa fa-calendar-check-o"></i> Penjualan Tunai Bulan Ini</div>
										<div class="panel-body">
											<h3 style="margin-top: 5px; color: #3c8dbc;" id="tunai-bulan-total">Memuat...</h3>
											<div id="tunai-bulan-count" class="text-muted"></div>
										</div>
									</div>
								</div>
							</div>
							<p id="tunai-summary-error" class="text-danger" role="alert"></p>
							<div style="max-width:100%;overflow-x:auto;"><div id="tunai-sheet"></div></div>
						</div>

						<!-- TAB 3: PERORANGAN -->
						<div id="perorangan" class="tab-pane">
							<div class="well well-sm" style="background: #fdfdfd; margin-bottom: 15px;">
								<div class="row">
									<div class="col-md-5">
										<label><i class="fa fa-user"></i> Anggota (NPK / Nama):</label>
										<div class="easy-autocomplete" style="width: 100% !important;">
											<input type="text" class="form-control input-sm" name="npk" id="npk" placeholder="Ketik NPK atau Nama anggota" autocomplete="off" style="width: 100% !important;">
										</div>
									</div>
									<div class="col-md-2">
										<label>Tahun:</label>
										<select id="tahun_perorangan" class="form-control input-sm"></select>
									</div>
									<div class="col-md-3">
										<label>Bulan Pembukuan:</label>
										<select id="bulan_perorangan" class="form-control input-sm">
											<option value="01">17 Des - 20 Jan</option><option value="02">21 Jan - 20 Feb</option>
											<option value="03">21 Feb - 20 Mar</option><option value="04">21 Mar - 20 Apr</option>
											<option value="05">21 Apr - 20 Mei</option><option value="06">21 Mei - 20 Jun</option>
											<option value="07">21 Jun - 20 Jul</option><option value="08">21 Jul - 20 Agu</option>
											<option value="09">21 Agu - 20 Sep</option><option value="10">21 Sep - 20 Okt</option>
											<option value="11">21 Okt - 20 Nov</option><option value="12">21 Nov - 16 Des</option>
										</select>
									</div>
									<div class="col-md-3 text-right" style="padding-top: 24px;">
										<button type="button" class="btn btn-sm btn-default" id="download-csv-perorangan">
											<i class="fa fa-download"></i> Download CSV
										</button>
									</div>
								</div>
							</div>
							<div style="max-width:100%;overflow-x:auto;"><div id="perorangan-sheet"></div></div>
						</div>

						<!-- TAB 4: BULANAN -->
						<div id="bulanan" class="tab-pane">
							<div class="well well-sm" style="background: #fdfdfd; margin-bottom: 15px;">
								<div class="row">
									<div class="col-md-2">
										<label>Tahun:</label>
										<select id="tahun_bulanan" class="form-control input-sm"></select>
									</div>
									<div class="col-md-4">
										<label>Bulan Pembukuan:</label>
										<select id="bulan_bulanan" class="form-control input-sm">
											<option value="01">17 Desember - 20 Januari</option><option value="02">21 Januari - 20 Februari</option>
											<option value="03">21 Februari - 20 Maret</option><option value="04">21 Maret - 20 April</option>
											<option value="05">21 April - 20 Mei</option><option value="06">21 Mei - 20 Juni</option>
											<option value="07">21 Juni - 20 Juli</option><option value="08">21 Juli - 20 Agustus</option>
											<option value="09">21 Agustus - 20 September</option><option value="10">21 September - 20 Oktober</option>
											<option value="11">21 Oktober - 20 November</option><option value="12">21 November - 16 Desember</option>
										</select>
									</div>
									<div class="col-md-6 text-right" style="padding-top: 24px;">
										<button type="button" class="btn btn-sm btn-default" id="download-csv-bulanan">
											<i class="fa fa-download"></i> Download CSV
										</button>
									</div>
								</div>
							</div>
							<div style="max-width:100%;overflow-x:auto;"><div id="bulanan-sheet"></div></div>
						</div>

						<!-- TAB 5: TAHUNAN -->
						<div id="tahunan" class="tab-pane">
							<div class="well well-sm" style="background: #fdfdfd; margin-bottom: 15px;">
								<div class="row">
									<div class="col-md-2">
										<label>Tahun:</label>
										<select id="tahun_tahunan" class="form-control input-sm"></select>
									</div>
									<div class="col-md-10 text-right" style="padding-top: 24px;">
										<button type="button" class="btn btn-sm btn-default" id="download-csv-tahunan">
											<i class="fa fa-download"></i> Download CSV
										</button>
									</div>
								</div>
							</div>
							<div style="max-width:100%;overflow-x:auto;"><div id="tahunan-sheet"></div></div>
						</div>

						<!-- TAB 6: ANALISIS & TOP PRODUK -->
						<div id="analitik" class="tab-pane">
							<div class="well well-sm" style="background: #fdfdfd; margin-bottom: 15px;">
								<div class="row">
									<div class="col-md-3">
										<label>Dari Tanggal:</label>
										<input type="date" class="form-control input-sm" id="chart_start_date" value="<?= date('Y-m-01') ?>">
									</div>
									<div class="col-md-3">
										<label>Sampai Tanggal:</label>
										<input type="date" class="form-control input-sm" id="chart_end_date" value="<?= date('Y-m-d') ?>">
									</div>
									<div class="col-md-2" style="padding-top: 24px;">
										<button type="button" class="btn btn-sm btn-primary" id="btn-filter-chart">
											<i class="fa fa-filter"></i> Terapkan
										</button>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-7">
									<div class="box box-info">
										<div class="box-header with-border">
											<h3 class="box-title"><i class="fa fa-area-chart"></i> Tren Omset Harian Toko</h3>
										</div>
										<div class="box-body">
											<canvas id="salesTrendChart" style="height: 280px;"></canvas>
										</div>
									</div>
								</div>
								<div class="col-md-5">
									<div class="box box-success">
										<div class="box-header with-border">
											<h3 class="box-title"><i class="fa fa-star"></i> Top 10 Produk Terlaris</h3>
										</div>
										<div class="box-body table-responsive" style="max-height: 300px; overflow-y: auto;">
											<table class="table table-bordered table-striped table-condensed" style="font-size: 13px;">
												<thead>
													<tr class="bg-gray">
														<th>#</th>
														<th>Nama Item</th>
														<th class="text-right">Qty</th>
														<th class="text-right">Total Omset</th>
													</tr>
												</thead>
												<tbody id="top-products-tbody">
													<tr><td colspan="4" class="text-center text-muted">Memuat data top produk...</td></tr>
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- MODAL DETAIL NOTA / STRUK BELANJA -->
<div class="modal fade" id="modal-detail-nota" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detail Transaksi & Struk Kasir</h4>
			</div>
			<div class="modal-body" id="modal-detail-nota-body">
				<div class="text-center" style="padding: 30px;">
					<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
					<p style="margin-top: 10px;">Mengambil data nota...</p>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
				<a href="#" target="_blank" class="btn btn-info" id="btn-print-receipt">
					<i class="fa fa-print"></i> Cetak Ulang Struk
				</a>
			</div>
		</div>
	</div>
</div>

<!-- MODAL HITUNG & PREVIEW FISIK LACI KASIR POS -->
<div class="modal fade" id="modal-cash-count" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><i class="fa fa-calculator"></i> Preview & Hitung Fisik Laci Kasir POS</h4>
			</div>
			<div class="modal-body">
				<div class="well well-sm" style="background: #fdfdfd; border-left: 4px solid #3c8dbc; margin-bottom: 15px;">
					<div class="row">
						<div class="col-sm-6">
							<label style="margin-bottom: 4px;"><i class="fa fa-money"></i> Modal Awal Laci Kasir (Float):</label>
							<div class="input-group" style="max-width: 250px;">
								<span class="input-group-addon">Rp</span>
								<input type="text" class="form-control input-sm" id="modal-cash-float" value="0" placeholder="0" autocomplete="off" style="font-weight: bold; color: #3c8dbc;">
							</div>
							<small class="text-muted">Modal uang kembalian kasir di pagi hari.</small>
						</div>
						<div class="col-sm-6">
							<span style="font-size: 12px; color: #777;">PENJUALAN TUNAI HARI INI</span>
							<h4 style="margin: 3px 0 0; color: #00a65a; font-weight: bold;" id="modal-cash-today-sales">Rp 0</h4>
							<small class="text-muted" id="modal-cash-today-count">0 transaksi tunai</small>
						</div>
					</div>
				</div>

				<div class="row text-center" style="margin-bottom: 15px;">
					<div class="col-xs-4" style="border-right: 1px solid #eee;">
						<span style="font-size: 12px; color: #777;">MODAL AWAL</span>
						<h4 style="margin: 4px 0 0; color: #3c8dbc; font-weight: bold;" id="modal-display-float">Rp 0</h4>
					</div>
					<div class="col-xs-4" style="border-right: 1px solid #eee;">
						<span style="font-size: 12px; color: #777;">PENJUALAN TUNAI</span>
						<h4 style="margin: 4px 0 0; color: #00a65a; font-weight: bold;" id="modal-display-sales">Rp 0</h4>
					</div>
					<div class="col-xs-4">
						<span style="font-size: 12px; color: #777;">TARGET UANG DI LACI</span>
						<h3 style="margin: 2px 0 0; color: #008d4c; font-weight: bold;" id="modal-target-display" data-sales="0">Rp 0</h3>
					</div>
				</div>

				<div class="row">
					<div class="col-md-7">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
							<h4 style="margin: 0; font-size: 15px;"><i class="fa fa-list-ol"></i> Hitung Uang Fisik Laci</h4>
							<button type="button" class="btn btn-default btn-xs" id="btn-modal-reset-count">
								<i class="fa fa-refresh"></i> Reset Hitungan
							</button>
						</div>
						<div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
							<table class="table table-condensed table-bordered" style="margin-bottom: 0;">
								<thead>
									<tr class="bg-gray-light">
										<th>Pecahan</th>
										<th class="text-center" style="width: 120px;">Jumlah Lembar / Koin</th>
										<th class="text-right" style="width: 150px;">Subtotal</th>
									</tr>
								</thead>
								<tbody>
								<?php foreach (array(100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100) as $denom) { ?>
									<tr>
										<td><strong>Rp <?php echo number_format($denom, 0, ',', '.'); ?></strong></td>
										<td class="text-center">
											<input type="number" min="0" step="1" inputmode="numeric" class="form-control input-sm modal-count-input" data-denomination="<?php echo $denom; ?>" value="0" style="text-align: center; font-weight: bold; height: 28px;">
										</td>
										<td class="text-right modal-denom-subtotal">Rp 0</td>
									</tr>
								<?php } ?>
								</tbody>
								<tfoot>
									<tr class="bg-gray-light" style="font-size: 13px;">
										<th colspan="2">TOTAL UANG FISIK TERHITUNG</th>
										<th class="text-right" id="modal-count-total" style="color: #00a65a; font-weight: bold;">Rp 0</th>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
					<div class="col-md-5">
						<div class="box box-solid box-default" style="border: 1px solid #d2d6de; background: #fafafa; margin-top: 25px;">
							<div class="box-body text-center" style="padding: 20px 15px;">
								<span style="font-size: 13px; color: #777; font-weight: 600;">SELISIH KAS (FISIK − TARGET)</span>
								<div id="modal-count-variance" style="font-size: 26px; margin: 8px 0; font-weight: bold;">
									Rp 0
								</div>
								<div id="modal-count-status" class="help-block" style="font-size: 13px; margin-bottom: 15px;">
									Isi jumlah lembar / koin pada tabel di sebelah kiri.
								</div>
								<hr style="margin: 12px 0;">
								<button type="button" class="btn btn-primary btn-block" id="btn-modal-print-slip">
									<i class="fa fa-print"></i> Cetak Slip Opname Kasir
								</button>
								<small class="text-muted" style="display: block; margin-top: 6px;">Format struk termal / tanda terima rekap kasir</small>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
			</div>
		</div>
	</div>
</div>
