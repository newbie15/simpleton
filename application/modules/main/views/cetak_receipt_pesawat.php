<?php

// print_r($pesawat);

// print_r($penumpang);

$kode_booking = $pesawat[0][0]; 
$tanggal = $pesawat[0][1]; 
$waktu = $pesawat[0][2]; 
$tanggal_k = $pesawat[0][3]; 
$waktu_k = $pesawat[0][4]; 
$flight_number = $pesawat[0][5]; 
$asal = $pesawat[0][6]; 
$tujuan = $pesawat[0][7]; 
$maskapai = $pesawat[0][8]; 
$fasilitas = $pesawat[0][9];

$gambar = null;

$bandara_asal = "";
$bandara_tujuan = "";

$tgl = explode("-",$tanggal);
$tgl_k = explode("-",$tanggal_k);
$bulan["01"] = "Januari";
$bulan["02"] = "Ferbuari";
$bulan["03"] = "Maret";
$bulan["04"] = "April";
$bulan["05"] = "Mei";
$bulan["06"] = "Juni";
$bulan["07"] = "Juli";
$bulan["08"] = "Agustus";
$bulan["09"] = "September";
$bulan["10"] = "Oktober";
$bulan["11"] = "November";
$bulan["12"] = "Desember";

$tanggal = $tgl[2]." ".$bulan[$tgl[1]]." ".$tgl[0];
$tanggal_k = $tgl_k[2]." ".$bulan[$tgl_k[1]]." ".$tgl_k[0];


switch ($maskapai) {
  case 'Batik Air':
    # code...
    $gambar = base_url("assets/tiket/img/batik.jpg");
    break;
  case 'Citilink':
    # code...
    $gambar = base_url("assets/tiket/img/citilink.jpg");
    break;

  case 'Garuda Indonesia':
  # code...
    $gambar = base_url("assets/tiket/img/garuda.jpg");
    break;

  case 'Sriwijaya':
  # code...
    $gambar = base_url("assets/tiket/img/sriwijaya.jpg");
    break;

  case 'Wings Air':
  # code...
    $gambar = base_url("assets/tiket/img/wings_air.jpg");
    break;

  case 'Trigana':
  # code...
    $gambar = base_url("assets/tiket/img/trigana.jpg");
    break;
    
  case 'NAM Air':
  # code...
    $gambar = base_url("assets/tiket/img/NAM_Air.png");

    break;

  default:
    # code...
    break;
}

?>



<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head><meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>Receipt Tiket <?php echo $maskapai."/".$kode_booking."-".$asal."_".$tujuan; ?></title></head>
<body style="
    font-family: sans-serif;
">
<table style="text-align: left; width: 100%;" border="0" cellpadding="2" cellspacing="2">
  <tbody>
    <tr>
      <td colspan="4" rowspan="1" style="vertical-align: top; width: 179px;"><h1>Bukti Transaksi<br>
      Tiket Pesawat<br></h1>
      </td>
      <td colspan="2" rowspan="1" style="text-align:right; vertical-align: top; height: 147px; width: 221px;"><img width="75%" src="<?php echo base_url("assets/tiket/img/logo.png")?>"><br>
      </td>
    </tr>
  </tbody>
</table>
<br>
<hr>
<h2>Penerbangan</h2>
<strong>Maskapai</strong> : <?php echo $maskapai; ?>
<br><strong>Flight Number</strong> : <?php echo $flight_number; ?>
<br>
<br><strong>Asal</strong> : <?php echo $asal; ?>
<br><strong>Jadwal Keberangkatan</strong> : <?php echo $waktu." ,".$hari." ".$tanggal; ?>
<br>
<br><strong>Tujuan</strong> : <?php echo $tujuan; ?>
<br><strong>Jadwal Kedatangan</strong> : <?php echo $waktu_k." ,".$hari1." ".$tanggal_k; ?>

<h2>Detail Penumpang</h2>
<table style="text-align: left; width: 100%;" border="1" cellpadding="2" cellspacing="0">
  <tbody>
    <tr>
      <td style="vertical-align: top; width: 59px; text-align: center;">NO<br>
      </td>
      <td style="vertical-align: top; width: 179px; text-align: center;">Penumpang<br>
      </td>
      <td style="vertical-align: top; width: 221px; text-align: center;">No KTP<br>
      </td>
      <td style="vertical-align: top; text-align: center;">Biaya<br>
      <br>
      </td>
    </tr>

<?php
    $i = 1;
    $total = 0;
foreach ($penumpang as $key => $value) {
    // print_r($value);
    $nama = $value[0];
    $no_ktp = $value[1];
    $biaya = $value[3];
    $total += $biaya;
    
?>
    <tr>
      <td style="vertical-align: top; width: 59px; text-align: center;"><?php echo $i++; ?><br>
      </td>
      <td style="vertical-align: top; width: 179px; text-align: center;"><span id="nama"><?php echo $nama; ?></span><br>
      </td>
      <td style="vertical-align: top; width: 221px; text-align: center;"><?php echo $no_ktp; ?><br>
      </td>
      <td style="vertical-align: top; text-align: center;"><span id="fasilitas"><?php echo "IDR ".number_format($biaya,0,",","."); ?></span><br>
      </td>
    </tr>
<?php
  }
?>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td style="vertical-align: top; width: 59px; text-align: center;">&nbsp;<br>
      </td>
      <td colspan="2" style="vertical-align: top; width: 59px; text-align: center;">Grand Total<br>
      </td>
      <!-- <td style="vertical-align: top; width: 179px; text-align: center;"><span id="nama"><?php echo $nama; ?></span><br>
      </td>
      <td style="vertical-align: top; width: 221px; text-align: center;"><?php echo $no_ktp; ?><br>
      </td> -->
      <td style="vertical-align: top; text-align: center;"><span id="fasilitas"><?php echo "IDR ".number_format($total,0,",","."); ?></span><br>
      </td>
    </tr>
  </tbody>
</table>

  <br>

</body></html>