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

  case 'Trans Nusa':
  # code...
    $gambar = base_url("assets/tiket/img/trans_nusa.png");

    break;

  default:
    # code...
    break;
}

?>



<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head><meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>tiket KMS</title></head>
<body style="
    font-family: sans-serif;
">
<table style="text-align: left; width: 100%;" border="0" cellpadding="2" cellspacing="2">
  <tbody>
    <tr>
      <td colspan="4" rowspan="1" style="vertical-align: top; width: 179px;"><h1>Tiket Elektronik<br>
Jadwal Keberangkatan Penerbangan<br></h1>
      </td>
      <td colspan="2" rowspan="1" style="text-align:right; vertical-align: top; height: 147px; width: 221px;"><img width="75%" src="<?php echo base_url("assets/tiket/img/logo.png")?>"><br>
      </td>
    </tr>
    <tr>
      <td colspan="2" rowspan="4" style="vertical-align: top; width: 179px; text-align: center;">
      <img src="<?php echo $gambar; ?>">
      </td>
      <td colspan="2" rowspan="1" style="text-align: center; vertical-align: middle; height: 50px; width: 250px;"><span id="tanggal"><strong>Flight Number : <?php echo $flight_number; ?></strong></span><br>
      </td>
      <!-- <td style="vertical-align: top; height: 40px; width: 221px;"><br>
      </td>
      <td style="vertical-align: top; height: 40px;"><br>
      </td> -->

      <td rowspan="4" style="vertical-align: top; height: 50px; width: 221px; text-align: center;"><h2>Airline Booking Code (PNR)</h2><br>
      </td>
      <td rowspan="4" style="vertical-align: top; height: 50px; text-align: center;"><h1 style="color: #007aff;"><?php echo $kode_booking; ?></h1><br>
      </td>
    </tr>
    <tr>
      <td style="vertical-align: top; height: 40px; width: 225px; text-align: center;"><span id="waktu"><?php echo $waktu; ?></span><br><?php echo $hari.", ".$tanggal; ?><br>
      </td>
      <td style="vertical-align: top; height: 40px; width: 250px;">Keberangkatan<br><strong><span id="asal"><?php echo $asal; ?></span></strong><br>
<?php echo $bandara_asal; ?><br>
      </td>

      <!-- <td style="vertical-align: top; height: 50px; width: 221px; text-align: center;"><h2>Airline Booking Code (PNR)</h2><br>
      </td>
      <td style="vertical-align: middle; height: 50px; text-align: center;"><h1 style="color: #007aff;"><?php echo $kode_booking; ?></h1><br>
      </td> -->
    </tr>
    <tr>
      <td style="vertical-align: top; width: 225px; height: 46px;"><br>
      </td>
      <td style="vertical-align: top; width: 250px; height: 46px;"><br>
      </td>
      <!-- <td style="vertical-align: top; width: 221px; height: 46px;"><br>
      </td>
      <td style="vertical-align: top; height: 46px;"><br>
      </td> -->
    </tr>
    <tr>
      <td style="vertical-align: top; width: 225px; height: 68px; text-align: center;"><?php echo $waktu_k; ?></span><br><?php echo $hari1.", ".$tanggal_k; ?><br>
      </td>
      <td style="vertical-align: top; width: 250px; height: 68px;">Kedatangan<br><strong><span id="tujuan"><?php echo $tujuan; ?></span></strong><br>
<?php echo $bandara_tujuan; ?><br>
      </td>
      <!-- <td style="vertical-align: top; width: 221px; height: 68px;"><br>
      </td>
      <td style="vertical-align: top; height: 68px;"><br>
      </td> -->
    </tr>
    <!-- <tr>
      <td style="vertical-align: top; width: 59px;"><br>
      </td>
      <td style="vertical-align: top; width: 179px;"><br>
      </td>
      <td style="vertical-align: top;"><br>
      </td>
      <td style="vertical-align: top; width: 250px;"><br>
      </td>
      <td style="vertical-align: top; width: 221px;"><br>
      </td>
      <td style="vertical-align: top;"><br>
      </td>
    </tr> -->
  </tbody>
</table>
<br>
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
      <td style="vertical-align: top; text-align: center;">Fasilitas ( Bagasi )<br>
      <br>
      </td>
    </tr>

<?php
    $i = 1;
foreach ($penumpang as $key => $value) {
    // print_r($value);
    $nama = $value[0];
    $no_ktp = $value[1];
    
?>
    <tr>
      <td style="vertical-align: top; width: 59px; text-align: center;"><?php echo $i++; ?><br>
      </td>
      <td style="vertical-align: top; width: 179px; text-align: center;"><span id="nama"><?php echo $nama; ?></span><br>
      </td>
      <td style="vertical-align: top; width: 221px; text-align: center;"><?php echo $no_ktp; ?><br>
      </td>
      <td style="vertical-align: top; text-align: center;"><span id="fasilitas"><?php echo $fasilitas; ?></span><br>
      </td>
    </tr>
<?php
  }
?>
  </tbody>
</table>

  <br>

</body></html>