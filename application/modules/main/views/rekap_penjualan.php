<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div id="exTab2" class="container" data-ajax-base="<?php echo html_escape($ajax_base); ?>">
	<ul class="nav nav-tabs">
		<li class="active"><a id="h" href="#harian">Harian</a></li>
		<li><a id="m" href="#perorangan">Perorangan</a></li>
		<li><a id="b" href="#bulanan">Bulanan</a></li>
		<li><a id="t" href="#tahunan">Tahunan</a></li>
	</ul>
	<div class="tab-content">
		<div id="harian">
			<br>Tanggal: <input type="date" name="tanggal" id="tanggal">
			<button type="button" id="download-csv-harian">Download CSV</button>
			<div style="max-width:100%;overflow-x:auto;"><div id="harian-sheet"></div></div>
		</div>
		<div id="perorangan">
			<br>
			<table><tr>
				<td align="right">NPK&nbsp;&nbsp;</td>
				<td><div class="easy-autocomplete" style="width:196px;float:left"><input type="text" name="npk" id="npk" placeholder="Nama atau NPK karyawan" autocomplete="off"></div></td>
				<td>&nbsp;&nbsp;Tahun: <select id="tahun_perorangan"><option>tahun</option></select> Bulan:
					<select id="bulan_perorangan">
						<option value="01">17 Des - 20 Jan</option><option value="02">21 Jan - 20 Feb</option>
						<option value="03">21 Feb - 20 Mar</option><option value="04">21 Mar - 20 Apr</option>
						<option value="05">21 Apr - 20 Mei</option><option value="06">21 Mei - 20 Jun</option>
						<option value="07">21 Jun - 20 Jul</option><option value="08">21 Jul - 20 Agu</option>
						<option value="09">21 Agu - 20 Sep</option><option value="10">21 Sep - 20 Okt</option>
						<option value="11">21 Okt - 20 Nov</option><option value="12">21 Nov - 16 Des</option>
					</select>
				</td>
				<td align="right">&nbsp;&nbsp;<button type="button" id="download-csv-perorangan">Download CSV</button></td>
			</tr></table>
			<br><div id="perorangan-sheet"></div>
		</div>
		<div id="bulanan">
			<br>Tahun: <select id="tahun_bulanan"><option>tahun</option></select>
			Bulan Pembukuan <select id="bulan_bulanan">
				<option value="01">17 Desember - 20 Januari</option><option value="02">21 Januari - 20 Februari</option>
				<option value="03">21 Februari - 20 Maret</option><option value="04">21 Maret - 20 April</option>
				<option value="05">21 April - 20 Mei</option><option value="06">21 Mei - 20 Juni</option>
				<option value="07">21 Juni - 20 Juli</option><option value="08">21 Juli - 20 Agustus</option>
				<option value="09">21 Agustus - 20 September</option><option value="10">21 September - 20 Oktober</option>
				<option value="11">21 Oktober - 20 November</option><option value="12">21 November - 16 Desember</option>
			</select>
			<button type="button" id="download-csv-bulanan">Download CSV</button>
			<div id="bulanan-sheet"></div>
		</div>
		<div id="tahunan">
			<br>Tahun: <select id="tahun_tahunan"><option>tahun</option></select>
			<button type="button" id="download-csv-tahunan">Download CSV</button>
			<div id="tahunan-sheet"></div>
		</div>
	</div>
</div>