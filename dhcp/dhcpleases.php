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
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

	$getlease = $API->comm("/ip/dhcp-server/lease/print");
	$TotalReg = count($getlease);

	$countlease = $API->comm("/ip/dhcp-server/lease/print", array(
		"count-only" => "",
	));

}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-sitemap"></i> DHCP Leases 
<?php
if ($countlease < 2) {
	echo "$countlease item";
} elseif ($countlease > 1) {
	echo "$countlease items";
};
?>
&nbsp;&nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i>
    </h3>
</div>
<div class="card-body">	   
<div class="w-6">
    <input id="tableSearch" type="text" class="form-control" placeholder="Search..">
  </div>
<!-- Bulk bar: muncul saat ada checkbox terpilih -->
<div id="bulkBar" style="display:none; margin:8px 0 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
  <strong id="bulkCount">0</strong>&nbsp;selected:
  <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Remove</button>
</div>
<style>
/* Anti-flash: baris disembunyikan sampai initTableView() mem-apply pagination
   (fungsi itu selalu set display eksplisit per baris, jadi setelah init aturan
   ini tidak berpengaruh). Mencegah kedipan tabel polos saat script dimuat. */
#dataTable tbody tr.row-data { display: none; }
</style>
<noscript><style>#dataTable tbody tr.row-data { display: table-row; }</style></noscript>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh">   	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
  	<th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
  	<th style="width:50px; text-align:center;"></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Active Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Active MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Active Host Name</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Status</th>
  </tr>
  </thead>
  <tbody> 
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$lease = $getlease[$i];
	$id = $lease['.id'];


	$addr = $lease['address'];
	$maca = $lease['mac-address'];
	$server = $lease['server'];
	$aaddr = $lease['active-address'];
	$amaca = $lease['active-mac-address'];
	$ahostname = $lease['host-name'];
	$status = $lease['status'];


	echo "<tr class='row-data'>";
	echo "<td style='text-align:center;'><input type='checkbox' class='row-check' data-id='" . htmlspecialchars($id, ENT_QUOTES) . "' data-name='" . htmlspecialchars($addr, ENT_QUOTES) . "'></td>";
	echo "<td style='text-align:center;'>";
	if ($lease['dynamic'] == "true") {
		echo "<b title='D - dynamic'>D</b>";
	} else {
		echo "<b title='S - static'>S</b>";
	}
	echo "</td>";
	echo "<td>" . $addr . "</td>";
	echo "<td>" . $maca . "</td>";
	echo "<td>" . $server . "</td>";
	echo "<td>" . $aaddr . "</td>";
	echo "<td>" . $amaca . "</td>";
	echo "<td>" . $ahostname . "</td>";
	echo "<td>" . $status . "</td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
</div>

<!-- Footer pagination -->
<div class="pd-t-5" style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; justify-content:space-between;">
  <div style="display:flex; align-items:center; gap:8px; font-size:12px;">
    <span id="pageInfo">&nbsp;</span>
    <select id="perPageSel" class="form-control" style="width:auto; padding:2px 6px; font-size:12px;" title="Rows per page">
      <option value="50">50</option>
      <option value="100" selected>100</option>
      <option value="200">200</option>
      <option value="500">500</option>
    </select>
  </div>
  <div id="pagePager" style="display:flex; gap:4px; flex-wrap:wrap;"></div>
</div>
</div>
</div>
</div>
</div>

<script src="./js/tableview.js?v=<?= @filemtime(dirname(__DIR__) . '/js/tableview.js'); ?>"></script>
<script>
initTableView(100);
initBulkUI('dhcp-lease');
</script>