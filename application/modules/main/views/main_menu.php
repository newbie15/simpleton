<?php
$this->load->library('page_access');
$front_end_items = array(
	array('penjualan', 'Penjualan', 'main/examples/penjualan', 'fa-shopping-cart', isset($m_penjualan)),
	array('tiket', 'Tiket Travel', 'main/tiket', 'fa-plane', isset($m_tiket)),
);
$back_end_items = array(
	array('stok', 'Stok', 'main/examples/stok', 'fa-dropbox', isset($m_stok)),
	array('stock_opname', 'Stok Opname', 'main/stock_opname', 'fa-check-square-o', isset($m_stock_opname)),
	array('purchasing', 'Purchasing', 'main/purchasing', 'fa-truck', isset($m_purchasing)),
	array('product_conversion', 'Konversi Kemasan', 'main/product_conversion', 'fa-cubes', isset($m_product_conversion)),
	array('sales_return', 'Retur Penjualan', 'main/sales_return', 'fa-reply', isset($m_sales_return)),
	array('supplier_return', 'Retur Supplier', 'main/supplier_return', 'fa-reply-all', isset($m_supplier_return)),
	array('accounting', 'Accounting', 'main/accounting', 'fa-book', isset($m_accounting)),
	array('suppliers', 'Supplier', 'main/purchasing/suppliers', 'fa-building', isset($m_supplier)),
	array('users', 'Manajemen User', 'auth/index', 'fa-user', isset($m_user) || isset($m_auth_index)),
	array('karyawan', 'Karyawan', 'main/examples/karyawan', 'fa-users', isset($m_karyawan)),
	array('kartu', 'Kartu Anggota', 'main/examples/kartu', 'fa-credit-card', isset($m_kartu)),
	array('kamera', 'Foto Anggota', 'main/kamera', 'fa-camera', isset($m_foto)),
	array('update_data', 'Update Data', 'main/examples/update_data', 'fa-upload', isset($m_update)),
	array('rekap_penjualan', 'Rekap Penjualan', 'main/examples/rekap_penjualan', 'fa-line-chart', isset($m_rekap)),
);
$front_end_visible = FALSE;
foreach ($front_end_items as $item) {
	if ($this->page_access->can_access($item[0])) {
		$front_end_visible = TRUE;
		break;
	}
}
$back_end_visible = FALSE;
foreach ($back_end_items as $item) {
	if ($this->page_access->can_access($item[0])) {
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
			<?php if ($this->page_access->can_access($item[0])): ?>
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
	isset($m_sales_return) || isset($m_supplier_return) || isset($m_accounting) ||
	isset($m_auth_index)) ? ' active' : ''; ?>">
	<a href="#"><i class="fa fa-cogs"></i> <span>Back End</span><i class="fa fa-angle-left pull-right"></i></a>
	<ul class="treeview-menu">
		<?php foreach ($back_end_items as $item): ?>
			<?php if ($this->page_access->can_access($item[0])): ?>
			<li<?php echo $item[4] ? ' class="active"' : ''; ?>>
				<a href="<?php echo site_url($item[2]); ?>"><i class="fa <?php echo $item[3]; ?>"></i> <span><?php echo $item[1]; ?></span></a>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</li>
<?php endif; ?>
