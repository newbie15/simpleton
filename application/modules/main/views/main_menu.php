<li class="header">Main Menu</li>

<li <?php if(isset($m_penjualan)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/penjualan')?>'><i class="fa fa-circle-o"></i> <span>Penjualan</span></a></li>
<li <?php if(isset($m_stok)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/stok')?>'><i class="fa fa-circle-o"></i> <span>Stok</a></span></li>
<li <?php if(isset($m_user)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/user')?>'><i class="fa fa-circle-o"></i> <span>User</a></span></li>
<li <?php if(isset($m_karyawan)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/karyawan')?>'><i class="fa fa-circle-o"></i> <span>Karyawan</a></span></li>
<li <?php if(isset($m_kartu)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/kartu')?>'><i class="fa fa-circle-o"></i> <span>Kartu Anggota</a></span></li>

<li <?php if(isset($m_ustok)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/update_data')?>'><i class="fa fa-circle-o"></i> <span>Update Data</a></span></li>
<li <?php if(isset($m_ustok)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/rekap_penjualan')?>'><i class="fa fa-circle-o"></i> <span>Rekap Penjualan</a></span></li>

