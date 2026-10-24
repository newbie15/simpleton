<?php
$this->load->library(array('page_access', 'ion_auth'));
$can_show_menu_item = function ($item) {
	return $this->page_access->can_access($item[0]) &&
		(!isset($item[5]) || $this->ion_auth->in_group($item[5]));
};
$front_end_items = array(
	array('penjualan', 'Penjualan', 'main/penjualan', 'fa-shopping-cart', isset($m_penjualan)),
	array('tiket', 'Tiket Travel', 'main/tiket', 'fa-plane', isset($m_tiket)),
);
$back_end_items = array(
	array('stok', 'Stok', 'main/stok', 'fa-dropbox', isset($m_stok)),
	array('stock_opname', 'Stok Opname', 'main/stock_opname', 'fa-check-square-o', isset($m_stock_opname)),
	array('purchasing', 'Purchasing', 'main/purchasing', 'fa-truck', isset($m_purchasing)),
	array('product_conversion', 'Konversi Kemasan', 'main/product_conversion', 'fa-cubes', isset($m_product_conversion)),
	array('vouchers', 'Voucher', 'main/voucher', 'fa-ticket', isset($m_vouchers)),
	array('sales_return', 'Retur Penjualan', 'main/sales_return', 'fa-reply', isset($m_sales_return)),
	array('accounting', 'Tagihan & Validasi', 'main/sales_billing', 'fa-check-circle', isset($m_sales_billing)),
	array('supplier_return', 'Retur Supplier', 'main/supplier_return', 'fa-reply-all', isset($m_supplier_return)),
	array('accounting', 'Buku Akun (Gnucash-style)', 'main/accounting_book', 'fa-sitemap', isset($m_accounting_book)),
	array('accounting', 'Accounting V3 — Spreadsheet', 'main/accounting_grid', 'fa-table', isset($m_accounting_grid)),
	array('accounting', 'Accounting V2 — Buku Besar', 'main/accounting_new', 'fa-book', isset($m_accounting_new)),
	array('accounting', 'Accounting (Lama)', 'main/accounting', 'fa-book', isset($m_accounting)),
	array('petty_cash', 'Petty Cash V3 — Spreadsheet', 'main/petty_cash_grid', 'fa-table', isset($m_petty_cash_grid)),
	array('petty_cash', 'Pembayaran Supplier', 'main/management_payments', 'fa-credit-card', isset($m_management_payments), array('admin', 'management', 'pengurus')),
	array('petty_cash', 'Kas & Petty Cash V2', 'main/petty_cash_new', 'fa-money', isset($m_petty_cash_new)),
	array('petty_cash', 'Kas & Petty Cash (Lama)', 'main/petty_cash', 'fa-money', isset($m_petty_cash)),
	array('suppliers', 'Supplier', 'main/purchasing/suppliers', 'fa-building', isset($m_supplier)),
	array('users', 'Manajemen User', 'auth/index', 'fa-user', isset($m_user) || isset($m_auth_index)),
	array('karyawan', 'Karyawan', 'main/karyawan', 'fa-users', isset($m_karyawan)),
	array('kartu', 'Kartu Anggota', 'main/kartu/batch', 'fa-credit-card', isset($m_kartu)),
	array('kamera', 'Foto Anggota', 'main/kamera', 'fa-camera', isset($m_foto)),
	array('update_data', 'Update Data', 'main/data_update', 'fa-upload', isset($m_update)),
	array('rekap_penjualan', 'Rekap Penjualan', 'main/rekap_penjualan', 'fa-line-chart', isset($m_rekap)),
);
$front_end_visible = FALSE;
foreach ($front_end_items as $item) {
	if ($can_show_menu_item($item)) {
		$front_end_visible = TRUE;
		break;
	}
}
$back_end_visible = FALSE;
foreach ($back_end_items as $item) {
	if ($can_show_menu_item($item)) {
		$back_end_visible = TRUE;
		break;
	}
}
?>
<?php if ($this->page_access->can_access('member_portal')): ?>
<li class="header">AREA ANGGOTA</li>
<li<?php echo isset($m_member_portal) ? ' class="active"' : ''; ?>>
	<a href="<?php echo site_url('main/member'); ?>">
		<i class="fa fa-home"></i> <span>Portal Anggota</span>
	</a>
</li>
<?php endif; ?>

<?php if ($front_end_visible): ?>
<li class="treeview<?php echo (isset($m_penjualan) || isset($m_tiket)) ? ' active' : ''; ?>">
	<a href="#"><i class="fa fa-desktop"></i> <span>Front End</span><i class="fa fa-angle-left pull-right"></i></a>
	<ul class="treeview-menu">
		<?php foreach ($front_end_items as $item): ?>
			<?php if ($can_show_menu_item($item)): ?>
			<li<?php echo $item[4] ? ' class="active"' : ''; ?>>
				<a href="<?php echo site_url($item[2]); ?>"><i class="fa <?php echo $item[3]; ?>"></i> <span><?php echo $item[1]; ?></span></a>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</li>
<?php endif; ?>

<?php if ($back_end_visible): ?>
<li class="treeview<?php echo (isset($m_stok) || isset($m_stock_opname) || isset($m_purchasing) ||
	isset($m_supplier) || isset($m_user) || isset($m_karyawan) || isset($m_kartu) ||
	isset($m_foto) || isset($m_update) || isset($m_rekap) || isset($m_product_conversion) ||
	isset($m_vouchers) || isset($m_sales_return) || isset($m_supplier_return) || isset($m_sales_billing) || isset($m_accounting) ||
	isset($m_accounting_new) || isset($m_accounting_grid) || isset($m_accounting_book) || isset($m_petty_cash) ||
	isset($m_petty_cash_new) || isset($m_petty_cash_grid) || isset($m_management_payments) || isset($m_auth_index)) ? ' active' : ''; ?>">
	<a href="#"><i class="fa fa-cogs"></i> <span>Back End</span><i class="fa fa-angle-left pull-right"></i></a>
	<ul class="treeview-menu">
		<?php foreach ($back_end_items as $item): ?>
			<?php if ($can_show_menu_item($item)): ?>
			<li<?php echo $item[4] ? ' class="active"' : ''; ?>>
				<a href="<?php echo site_url($item[2]); ?>"><i class="fa <?php echo $item[3]; ?>"></i> <span><?php echo $item[1]; ?></span></a>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</li>
<?php endif; ?>
