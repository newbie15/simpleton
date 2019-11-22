<li class="header">Main Menu</li>

<li <?php if(isset($m_penjualan)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/penjualan')?>'><i class="fa fa-shopping-cart"></i> <span>Penjualan</span></a></li>
<li <?php if(isset($m_stok)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/stok')?>'><i class="fa fa-dropbox"></i> <span>Stok</a></span></li>


<li <?php if(isset($m_user)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/user')?>'><i class="fa fa-user"></i> <span>User</a></span></li>
<li <?php if(isset($m_karyawan)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/karyawan')?>'><i class="fa fa-users"></i> <span>Karyawan</a></span></li>
<li <?php if(isset($m_kartu)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/kartu')?>'><i class="fa fa-credit-card"></i> <span>Kartu Anggota</a></span></li>
<li <?php if(isset($m_foto)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/kamera')?>'><i class="fa fa-camera"></i> <span>Foto Anggota</a></span></li>

<li <?php if(isset($m_update)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/update_data')?>'><i class="fa fa-upload"></i> <span>Update Data</a></span></li>
<li <?php if(isset($m_rekap)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/rekap_penjualan')?>'><i class="fa fa-line-chart"></i> <span>Rekap Penjualan</a></span></li>

<li <?php if(isset($m_tiket)) { ?>class = "active" <?php } ?>><a href='<?php echo site_url('main/examples/tiket')?>'><i class="fa fa-plane"></i> <span>Tiket Travel</a></span></li>