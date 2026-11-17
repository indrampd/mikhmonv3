<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  ?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-print"></i> <?= $_quick_print ?> &nbsp;&nbsp; | &nbsp;&nbsp;<a class="pointer" onclick="location.href='./?hotspot=list-quick-print&session=<?= $session ?>';"  title="Quick Print List"><i class="fa fa-list"></i> List</a></h3>
</div>
<div class="card-body">
<div class="overflow" style="max-height: 80vh">	
<div class="row">
<?php
// get quick print
$getquickprint = $API->comm("/system/script/print", array("?comment" => "QuickPrintMikhmon"));
$TotalReg = count($getquickprint);
if ($TotalReg === 0) {
    // Panel sempit + terpusat: alert selebar kartu membuat pesan kosong terlihat
    // seperti blok abu-abu raksasa. Lebar dibatasi 520px, tanpa latar, jadi
    // proporsinya sama di layar lebar maupun sempit.
    echo "<div class='col-12'>";
    echo "<div style='min-height:46vh;display:flex;align-items:center;justify-content:center;'>";
    echo "<div style='max-width:440px;padding:0 8px;text-align:center;'>";
    echo "<div style='width:62px;height:62px;margin:0 auto 16px;border-radius:50%;background-color:rgba(32,168,216,.22);display:flex;align-items:center;justify-content:center;'>";
    echo "<i class='fa fa-print text-primary' style='font-size:26px;line-height:1;'></i>";
    echo "</div>";
    echo "<div class='text-bold' style='font-size:16px;margin-bottom:7px;'>Belum ada Template Paket Cetak Cepat</div>";
    echo "<div class='text-secondary' style='font-size:12.5px;line-height:1.75;margin-bottom:20px;'>Cetak cepat memakai template paket dari menu List. Buat satu template dulu, lalu tombol cetak muncul di sini.</div>";
    echo "<a href='./?hotspot=list-quick-print&session=" . $session . "' class='btn bg-primary radius-3 pd-5'><i class='fa fa-plus'></i> Buat Template Paket</a>";
    echo "</div>";
    echo "</div>";
}
for ($i = 0; $i < $TotalReg; $i++) {
  $quickprintdetails = $getquickprint[$i];
  $qpname = $quickprintdetails['name'];
  $qpid = $quickprintdetails['.id'];
  $quickprintsource = explode("#",$quickprintdetails['source']);
  $package = $quickprintsource[1];
  $server = $quickprintsource[2];
  $usermode = $quickprintsource[3];
  $userlength = $quickprintsource[4];
  $prefix = $quickprintsource[5];
  $char = $quickprintsource[6];
  $profile = $quickprintsource[7];
  $timelimit = $quickprintsource[8];
  $datalimit = $quickprintsource[9];
  $comment = $quickprintsource[10];
  $validity = $quickprintsource[11];
  $getprice = explode("_",$quickprintsource[12])[0];
  $getsprice = explode("_",$quickprintsource[12])[1];
  $userlock = $quickprintsource[13];
  if ($currency == in_array($currency, $cekindo['indo'])) {
    $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
    $sprice = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
} else {
    $price = $currency . " " . number_format((float)$getprice);
    $sprice = $currency . " " . number_format((float)$getsprice);
}
  ?>
	     <div class="col-4">
        <div id='./hotspot/quickuser.php?quickprint=<?= $qpname ?>&session=<?= $session; ?>' class="quick pointer box bmh-75 box-bordered <?= $color[rand(1, 11)]; ?>" title='<?= $_print.' '.$_package.' '. $package; ?>'>
          <div class="box-group">
            <div class="box-group-icon">
            	<i class="fa fa-print"></i>
            </div>
              <div class="box-group-area">
                <h3 ><?= $_package ?> : <?= $package; ?> <br></h3>
                <span><?= $_time_limit ?>  : <?= $timelimit ?> | <?= $_data_limit ?>  : <?= formatBytes($datalimit, 2) ?> <br> <?= $_validity ?>  : <?= $validity ?> | <?= $_price ?>  : <?= $price ?> | <?= $_selling_price ?>  : <?= $sprice ?></span>
              </div>
            </div>
            
          </div>
        </div>
        <?php 
      }
    }
    ?>
    </div>
    </div>
</div>
</div>
</div>
</div>

<script>
$(document).ready(function(){
  $(".quick").click(function(){

    loadpage(this.id);
    
  });

});
</script>