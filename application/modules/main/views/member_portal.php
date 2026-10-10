<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#13795b">
	<title>Portal Anggota Koperasi</title>
	<style>
		:root { color-scheme: light; --green: #13795b; --ink: #18332c; --muted: #64756f; --line: #e1eae6; --paper: #f4f8f6; }
		* { box-sizing: border-box; }
		html { scroll-behavior: smooth; scroll-padding-top: 76px; }
		body { margin: 0; padding: 0 0 86px; color: var(--ink); background: var(--paper); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
		a { color: var(--green); }
		.top { padding: 22px 18px 26px; color: white; background: var(--green); }
		.top-inner, main { width: min(100% - 28px, 760px); margin: 0 auto; }
		.top-line { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
		.brand { margin: 0; font-size: 14px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .88; }
		.logout { padding: 8px 12px; color: white; border: 1px solid rgba(255,255,255,.55); border-radius: 10px; text-decoration: none; }
		.top h1 { margin: 24px 0 3px; font-size: clamp(25px, 6vw, 34px); line-height: 1.2; }
		.top .sub { margin: 0; color: rgba(255,255,255,.83); }
		main { padding-top: 16px; }
		.section { margin: 0 0 18px; padding: 18px; border: 1px solid var(--line); border-radius: 16px; background: white; box-shadow: 0 3px 12px rgba(24,51,44,.035); }
		.section h2 { margin: 0 0 14px; font-size: 20px; line-height: 1.3; }
		.section h3 { margin: 0; font-size: 16px; }
		.muted, .hint { color: var(--muted); }
		.hint { margin: 8px 0 0; font-size: 13px; }
		.notice { padding: 12px; border-radius: 10px; color: #6d4c00; background: #fff6d9; }
		.cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
		.metric { min-width: 0; padding: 13px; border-radius: 12px; background: #f1f7f4; }
		.metric .label { display: block; margin-bottom: 4px; color: var(--muted); font-size: 13px; }
		.metric strong { display: block; overflow-wrap: anywhere; font-size: 18px; }
		.list { display: grid; gap: 10px; }
		.entry { padding: 12px 0; border-top: 1px solid var(--line); }
		.entry:first-child { padding-top: 0; border-top: 0; }
		.entry-head { display: flex; justify-content: space-between; gap: 12px; }
		.entry-head strong { text-align: right; }
		.entry p { margin: 3px 0 0; }
		.entry details { margin-top: 8px; font-size: 14px; }
		.entry details ul { margin: 6px 0 0; padding-left: 20px; }
		.search { display: flex; gap: 8px; margin-bottom: 14px; }
		.search input { min-width: 0; flex: 1; padding: 10px 12px; border: 1px solid #cbd9d3; border-radius: 10px; font: inherit; }
		.button { padding: 10px 14px; color: white; border: 0; border-radius: 10px; background: var(--green); font: inherit; font-weight: 650; text-decoration: none; cursor: pointer; }
		.pager { display: flex; justify-content: space-between; gap: 10px; margin-top: 14px; }
		.pager a { padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; text-decoration: none; }
		.bottom-nav { position: fixed; z-index: 2; right: 0; bottom: 0; left: 0; display: grid; grid-template-columns: repeat(6, 1fr); padding: 8px max(8px, env(safe-area-inset-right)) calc(8px + env(safe-area-inset-bottom)) max(8px, env(safe-area-inset-left)); border-top: 1px solid var(--line); background: rgba(255,255,255,.97); }
		.bottom-nav a { padding: 5px 1px; color: var(--muted); font-size: 10px; text-align: center; text-decoration: none; }
		.bottom-nav a:focus, .bottom-nav a:hover { color: var(--green); }
		.bottom-nav span { display: block; font-size: 17px; }
		@media (min-width: 640px) {
			.top { padding: 28px 24px 34px; }
			main { padding-top: 22px; }
			.section { padding: 22px; }
			.cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
		}
	</style>
</head>
<body>
	<header class="top">
		<div class="top-inner">
			<div class="top-line">
				<p class="brand">Portal Anggota</p>
				<a class="logout" href="<?php echo site_url('auth/logout'); ?>">Keluar</a>
			</div>
			<h1>Halo, <?php echo html_escape($member_name !== '' ? $member_name : 'Anggota'); ?></h1>
			<p class="sub"><?php echo $member_npk !== '' ? 'NPK '.html_escape($member_npk) : 'Akun anggota'; ?></p>
		</div>
	</header>

	<main>
		<?php if (!$member_linked): ?>
		<section class="section notice" role="status">
			Akun ini belum terhubung ke data anggota. Hubungi pengelola untuk memastikan NPK pada akun sesuai dengan data anggota. Riwayat SHU dan belanja ditampilkan setelah data tersebut ditautkan.
		</section>
		<?php endif; ?>

		<section class="section" id="ringkasan">
			<h2>Ringkasan</h2>
			<div class="cards">
				<div class="metric"><span class="label">Total simpanan</span><strong>Rp <?php echo number_format($member_balance, 0, ',', '.'); ?></strong></div>
				<div class="metric"><span class="label">Total SHU tercatat</span><strong>Rp <?php echo number_format($shu_total, 0, ',', '.'); ?></strong></div>
				<div class="metric"><span class="label">Total transaksi</span><strong><?php echo number_format($purchase_count, 0, ',', '.'); ?></strong></div>
			</div>
			<p class="hint">Riwayat SHU menampilkan maksimal 50 mutasi terbaru. Belanja menampilkan maksimal 20 transaksi terbaru.</p>
		</section>

		<section class="section" id="shu">
			<h2>Riwayat SHU</h2>
			<?php if (count($shu_history) === 0): ?>
				<p class="muted">Belum ada mutasi SHU yang tercatat.</p>
			<?php else: ?>
				<div class="list">
					<?php foreach ($shu_history as $row): ?>
					<article class="entry">
						<div class="entry-head">
							<div><h3><?php echo html_escape($row->nomor_bukti); ?></h3><p class="muted"><?php echo html_escape(date('d/m/Y', strtotime($row->tgl_transaksi))); ?></p></div>
							<strong>Rp <?php echo number_format((float) $row->nominal, 0, ',', '.'); ?></strong>
						</div>
						<?php if (trim((string) $row->keterangan) !== ''): ?><p class="muted"><?php echo html_escape($row->keterangan); ?></p><?php endif; ?>
					</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="section" id="belanja">
			<h2>Riwayat Belanja</h2>
			<?php if (count($purchases) === 0): ?>
				<p class="muted">Belum ada transaksi belanja yang tercatat untuk akun ini.</p>
			<?php else: ?>
				<div class="list">
					<?php foreach ($purchases as $purchase): ?>
					<article class="entry">
						<div class="entry-head">
							<div><h3><?php echo html_escape($purchase->nota); ?></h3><p class="muted"><?php echo html_escape(date('d/m/Y H:i', strtotime($purchase->purchased_at))); ?> · <?php echo (int) $purchase->item_count; ?> baris barang</p></div>
							<strong>Rp <?php echo number_format((float) $purchase->amount, 0, ',', '.'); ?></strong>
						</div>
						<?php if (count($purchase->items) > 0): ?>
						<details><summary>Lihat barang</summary><ul>
							<?php foreach ($purchase->items as $item): ?>
							<li><?php echo html_escape($item->product_name ? $item->product_name : $item->kode); ?> × <?php echo number_format((float) $item->quantity, 0, ',', '.'); ?></li>
							<?php endforeach; ?>
						</ul></details>
						<?php endif; ?>
					</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="section" id="keuangan">
			<h2>Keuangan Koperasi · <?php echo html_escape($finance_year); ?></h2>
			<div class="cards">
				<div class="metric"><span class="label">Pemasukan tercatat</span><strong>Rp <?php echo number_format($financial_summary['income'], 0, ',', '.'); ?></strong></div>
				<div class="metric"><span class="label">Beban tercatat</span><strong>Rp <?php echo number_format($financial_summary['expenses'], 0, ',', '.'); ?></strong></div>
				<div class="metric"><span class="label">Selisih</span><strong>Rp <?php echo number_format($financial_summary['net'], 0, ',', '.'); ?></strong></div>
			</div>
			<p class="hint">Ringkasan ini mencakup jurnal keuangan yang sudah diposting sejak awal tahun; bukan laporan audit.</p>
		</section>

		<section class="section" id="stok">
			<h2>Stok Koperasi</h2>
			<form class="search" method="get" action="<?php echo site_url('main/member'); ?>">
				<label for="product-search" class="muted" style="position:absolute;left:-9999px">Cari nama atau barcode barang</label>
				<input id="product-search" type="search" name="q" value="<?php echo html_escape($product_query); ?>" placeholder="Cari nama atau barcode barang">
				<button class="button" type="submit">Cari</button>
			</form>
			<p class="hint"><?php echo number_format($product_count, 0, ',', '.'); ?> barang · stok diperbarui sesuai data koperasi</p>
			<?php if (count($products) === 0): ?>
				<p class="muted">Barang tidak ditemukan.</p>
			<?php else: ?>
				<div class="list">
					<?php foreach ($products as $product): ?>
					<article class="entry">
						<div class="entry-head">
							<div><h3><?php echo html_escape($product->nama); ?></h3><p class="muted"><?php echo html_escape($product->barcode); ?></p></div>
							<strong><?php echo number_format((float) $product->jumlah, 0, ',', '.'); ?> tersedia</strong>
						</div>
						<p class="muted">Harga Rp <?php echo number_format((float) $product->harga, 0, ',', '.'); ?></p>
					</article>
					<?php endforeach; ?>
				</div>
				<?php if ($product_pages > 1): ?>
				<nav class="pager" aria-label="Halaman stok">
					<?php if ($product_page > 1): ?><a href="<?php echo site_url('main/member').'?q='.rawurlencode($product_query).'&page='.($product_page - 1); ?>">‹ Sebelumnya</a><?php else: ?><span></span><?php endif; ?>
					<span class="muted">Halaman <?php echo (int) $product_page; ?> / <?php echo (int) $product_pages; ?></span>
					<?php if ($product_page < $product_pages): ?><a href="<?php echo site_url('main/member').'?q='.rawurlencode($product_query).'&page='.($product_page + 1); ?>">Berikutnya ›</a><?php endif; ?>
				</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="section" id="simpanan">
			<h2>Simpanan Saya</h2>
			<?php if (count($savings) === 0): ?>
				<p class="muted">Belum ada saldo simpanan tercatat.</p>
			<?php else: ?>
				<div class="list">
					<?php foreach ($savings as $saving): ?>
					<div class="entry entry-head">
						<h3><?php echo html_escape(ucfirst($saving->jenis_simpanan)); ?></h3>
						<strong>Rp <?php echo number_format((float) $saving->saldo, 0, ',', '.'); ?></strong>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	</main>

	<nav class="bottom-nav" aria-label="Navigasi portal anggota">
		<a href="#ringkasan"><span aria-hidden="true">⌂</span>Ringkasan</a>
		<a href="#shu"><span aria-hidden="true">◷</span>SHU</a>
		<a href="#belanja"><span aria-hidden="true">▤</span>Belanja</a>
		<a href="#keuangan"><span aria-hidden="true">▥</span>Keuangan</a>
		<a href="#stok"><span aria-hidden="true">▦</span>Stok</a>
		<a href="#simpanan"><span aria-hidden="true">◉</span>Simpanan</a>
	</nav>
</body>
</html>
