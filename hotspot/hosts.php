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

	if ($hotspot == "hostp") {
		$gethosts = $API->comm("/ip/hotspot/host/print", array(
			"?bypassed" => "yes",
		));
		$TotalReg = count($gethosts);

		$counthosts = $API->comm("/ip/hotspot/host/print", array(
			"?bypassed" => "yes",
			"count-only" => "",
		));

	} elseif ($hotspot == "hosta") {
		$gethosts = $API->comm("/ip/hotspot/host/print", array(
			"?authorized" => "yes",
		));
		$TotalReg = count($gethosts);

		$counthosts = $API->comm("/ip/hotspot/host/print", array(
			"?authorized" => "yes",
			"count-only" => "",
		));

	} else {
		$gethosts = $API->comm("/ip/hotspot/host/print");
		$TotalReg = count($gethosts);

		$counthosts = $API->comm("/ip/hotspot/host/print", array(
			"count-only" => "",
		));
	}
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
	<h3><i class="fa fa-laptop"></i> Hosts  
		<?php
	if ($counthosts < 2) {
		echo "$counthosts item";
	} elseif ($counthosts > 1) {
		echo "$counthosts items ";
	};
	?>&nbsp;
		 | <a class="text-info" title="All" href="./?hotspot=hosts&session=<?= $session; ?>">&nbsp;&nbsp;All&nbsp;&nbsp;</a>
		 | <a class="text-success" title="Authorized" href="./?hotspot=hosta&session=<?= $session; ?>">&nbsp;&nbsp;A&nbsp;&nbsp;</a>
		 | <a class="text-primary" title="Bypassed" href="./?hotspot=hostp&session=<?= $session; ?>">&nbsp;&nbsp;P&nbsp;&nbsp;</a>
		 | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i>
    </h3>
</div>
<!-- /.card-header -->
<div class="card-body">	
  <div class="w-6">
    <input id="tableSearch" type="text" class="form-control" placeholder="Search..">
  </div>
<!-- Bulk bar: muncul saat ada checkbox terpilih -->
<div id="bulkBar" style="display:none; margin:8px 0 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
  <strong id="bulkCount">0</strong>&nbsp;selected:
  <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Remove</button>
</div>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh"> 	   	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
    <th style="width:50px; text-align:center;"></th>
    <th style="width:50px; text-align:center;"></th>
    <th class="pointer" data-sort="mac" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" data-sort="addr" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" data-sort="toaddr" title="Click to sort"><i class="fa fa-sort"></i> To Address</th>
    <th class="pointer" data-sort="srv" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
	<th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
  </tr>
  </thead>
  <tbody id="tbody"></tbody>
</table>
</div>
<?php
$rowsData = array();
for ($i = 0; $i < $TotalReg; $i++) {
	$hosts = $gethosts[$i];
	$flag = '';
	$flagClass = '';
	$flagTitle = '';
	if (($hosts['authorized'] ?? '') == "true" && ($hosts['DHCP'] ?? '') == "true") {
		$flag = 'A H'; $flagClass = 'text-success'; $flagTitle = 'A - authorized, H - DHCP';
	} elseif (($hosts['authorized'] ?? '') == "true" && ($hosts['dynamic'] ?? '') == "true") {
		$flag = 'A D'; $flagClass = 'text-success'; $flagTitle = 'A - Authorized, D - dynamic';
	} elseif (($hosts['authorized'] ?? '') == "true") {
		$flag = 'A'; $flagClass = 'text-success'; $flagTitle = 'A - authorized';
	} elseif (($hosts['DHCP'] ?? '') == "true") {
		$flag = 'H'; $flagClass = 'text-success'; $flagTitle = 'H - DHCP';
	} elseif (($hosts['dynamic'] ?? '') == "true") {
		$flag = 'D'; $flagClass = 'text-success'; $flagTitle = 'D - dynamic';
	} elseif (($hosts['bypassed'] ?? '') == "true") {
		$flag = 'P'; $flagClass = 'text-primary'; $flagTitle = 'P - Bypassed';
	}
	$rowsData[] = array(
		'id' => $hosts['.id'] ?? '',
		'mac' => $hosts['mac-address'] ?? '',
		'addr' => $hosts['address'] ?? '',
		'toaddr' => $hosts['to-address'] ?? '',
		'srv' => $hosts['server'] ?? '',
		'comm' => $hosts['comment'] ?? '',
		'flag' => $flag,
		'fcls' => $flagClass,
		'ftitle' => $flagTitle,
	);
}
?>

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

<script src="./js/tableview.js?v=<?= @filemtime(dirname(__DIR__) . '/js/tableview.js'); ?>"></script>
<script>
var HS_HOSTS_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var HS_SESSION = <?= json_encode($session); ?>;
initBulkUI('host', { virtual: true });
initVirtualTableView({
  data: HS_HOSTS_DATA,
  defaultPerPage: 100,
  searchCols: ['mac', 'addr', 'toaddr', 'srv', 'comm', 'flag'],
  renderRow: function(h, checked) {
    var id = tvEsc(h.id), mac = tvEsc(h.mac);
    var sessEnc = encodeURIComponent(HS_SESSION);
    var flagHtml = h.flag ? '<b class="' + tvEsc(h.fcls) + '" title="' + tvEsc(h.ftitle) + '">' + tvEsc(h.flag) + '</b>' : '';
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + mac + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><span class="pointer" title="Remove ' + mac + '" onclick="loadpage(\'./?remove-host=' + id + '&session=' + sessEnc + '\')"><i class="fa fa-minus-square text-danger"></i></span></td>' +
      '<td style="text-align:center;">' + flagHtml + '</td>' +
      '<td>' + mac + '</td>' +
      '<td>' + tvEsc(h.addr) + '</td>' +
      '<td>' + tvEsc(h.toaddr) + '</td>' +
      '<td>' + tvEsc(h.srv) + '</td>' +
      '<td>' + tvEsc(h.comm) + '</td>' +
      '</tr>';
  }
});
</script>