<?php
$front_end_active = isset($m_penjualan) || isset($m_tiket);
$back_end_active = isset($m_stok) || isset($m_stock_opname) || isset($m_purchasing) ||
	isset($m_supplier) || isset($m_user) || isset($m_karyawan) || isset($m_kartu) ||
	isset($m_foto) || isset($m_update) || isset($m_rekap);
?>
<li class="header">Main Menu</li>

<li class="treeview<?php echo $front_end_active ? ' active' : ''; ?>">
	<a href="#">
		<i class="fa fa-desktop"></i> <span>Front End</span>
		<i class="fa fa-angle-left pull-right"></i>
	</a>
	<ul class="treeview-menu">
		<li<?php if (isset($m_penjualan)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/penjualan'); ?>">
				<i class="fa fa-shopping-cart"></i> <span>Penjualan</span>
			</a>
		</li>
		<li<?php if (isset($m_tiket)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/tiket'); ?>">
				<i class="fa fa-plane"></i> <span>Tiket Travel</span>
			</a>
		</li>
	</ul>
</li>

<li class="treeview<?php echo $back_end_active ? ' active' : ''; ?>">
	<a href="#">
		<i class="fa fa-cogs"></i> <span>Back End</span>
		<i class="fa fa-angle-left pull-right"></i>
	</a>
	<ul class="treeview-menu">
		<li<?php if (isset($m_stok)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/stok'); ?>">
				<i class="fa fa-dropbox"></i> <span>Stok</span>
			</a>
		</li>
		<li<?php if (isset($m_stock_opname)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/stock_opname'); ?>">
				<i class="fa fa-check-square-o"></i> <span>Stok Opname</span>
			</a>
		</li>
		<li<?php if (isset($m_purchasing)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/purchasing'); ?>">
				<i class="fa fa-truck"></i> <span>Purchasing</span>
			</a>
		</li>
		<li<?php if (isset($m_supplier)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/purchasing/suppliers'); ?>">
				<i class="fa fa-building"></i> <span>Supplier</span>
			</a>
		</li>
		<li<?php if (isset($m_user)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/user'); ?>">
				<i class="fa fa-user"></i> <span>User</span>
			</a>
		</li>
		<li<?php if (isset($m_karyawan)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/karyawan'); ?>">
				<i class="fa fa-users"></i> <span>Karyawan</span>
			</a>
		</li>
		<li<?php if (isset($m_kartu)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/kartu'); ?>">
				<i class="fa fa-credit-card"></i> <span>Kartu Anggota</span>
			</a>
		</li>
		<li<?php if (isset($m_foto)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/kamera'); ?>">
				<i class="fa fa-camera"></i> <span>Foto Anggota</span>
			</a>
		</li>
		<li<?php if (isset($m_update)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/update_data'); ?>">
				<i class="fa fa-upload"></i> <span>Update Data</span>
			</a>
		</li>
		<li<?php if (isset($m_rekap)) { ?> class="active"<?php } ?>>
			<a href="<?php echo site_url('main/examples/rekap_penjualan'); ?>">
				<i class="fa fa-line-chart"></i> <span>Rekap Penjualan</span>
			</a>
		</li>
	</ul>
</li>
