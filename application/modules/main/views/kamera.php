<style>
.bg {
  /* width: 520px; */
  background-image: url("<?php echo  base_url('assets/kartu/KTA KOPKAR KMS.png');?>");
  background-size:100% 100%;
  color:black;
}
.txt {  
  font-family: monospace;
  font-size: xx-large;
}
</style>

<script src="<?php echo base_url('assets/kartu/JsBarcode.all.js'); ?>"></script>
<script>
  Number.prototype.zeroPadding = function(){
    var ret = "" + this.valueOf();
    return ret.length == 1 ? "0" + ret : ret;
  };
</script>

<!-- <div id="single">
  <table class="bg" width="450px" height="250px">
    <tr>
      <td align="center"><?php echo "" ?></td>
    </tr>
    <tr>
      <td align="center">
        <span class="txt"><?php echo $nama."<br/>".$bagian."<br>"; ?></span>
        <br>
        <img id="barcode1"/>
        <script>JsBarcode("#barcode1", "<?php echo $npk; ?>");</script>
      </td>
    </tr>
  </table>
</div> -->

<div id="multi">
  <table class="bg" width="450px" height="250px">
    <tr>
      <td align="center"><?php echo "" ?></td>
    </tr>
    <tr>
      <td align="center">
        <span class="txt"><?php echo $nama."<br/>".$bagian."<br>"; ?></span>
        <br>
        <img id="barcode1"/>
        <script>JsBarcode("#barcode1", "<?php echo $npk; ?>");</script>
      </td>
      <!-- <td></td> -->
    </tr>
  </table>

  <table class="bg" width="450px" height="250px">
    <tr>
      <td align="center"><?php echo "" ?></td>
    </tr>
    <tr>
      <td align="center">
        <span class="txt"><?php echo $nama."<br/>".$bagian."<br>"; ?></span>
        <br>
        <img id="barcode2"/>
        <script>JsBarcode("#barcode2", "<?php echo $npk; ?>");</script>
      </td>
      <!-- <td></td> -->
    </tr>
  </table>
</div>

