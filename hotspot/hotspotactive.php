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

// load session MikroTik
	$session = $_GET['session'];
	$serveractive = $_GET['server'];

// load config
	include('../include/config.php');
	include('../include/readcfg.php');
	
// lang
  include('../include/lang.php');
  include('../lang/'.$langid.'.php');

// routeros api
	include_once('../lib/routeros_api.class.php');
	include_once('../lib/formatbytesbites.php');
	$API = new RouterosAPI();
	$API->debug = false;
	$API->connect($iphost, $userhost, decrypt($passwdhost));

	if ($serveractive != "") {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print", array("?server" => "" . $serveractive . ""));
		$TotalReg = count($gethotspotactive);

		$counthotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"count-only" => "", "?server" => "" . $serveractive . ""
		));

	} else {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print");
		$TotalReg = count($gethotspotactive);

		$counthotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"count-only" => "",
		));
	}
}
?>
<div class="row">
<div id="reloadHotspotActive">
<div class="col-12">
	<div class="card">
		<div class="card-header">
    		<h3><i class="fa fa-wifi"></i> <?= $_hotspot_active ?> <?php
				if ($serveractive == "") {
				} else {
					echo $serveractive . " ";
				}
				if ($counthotspotactive < 2) {
					echo "$counthotspotactive item";
				} elseif ($counthotspotactive > 1) {
					echo "$counthotspotactive items";
				};
				if ($serveractive == "") {
				} else {
					echo " | <a href='./?hotspot=active&session=" . $session . "'> <i class='fa fa-search'></i> Show all</a>";
				}
				?>			</h3>
        </div>
         <div class="card-body overflow">
<div style="margin-bottom:8px;">
  <input id="tableSearch" type="text" class="form-control" placeholder="Search.." style="max-width:300px;">
</div>
<!-- Bulk bar: muncul saat ada checkbox terpilih -->
<div id="bulkBar" style="display:none; margin:8px 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
  <strong id="bulkCount">0</strong>&nbsp;terpilih:
  <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('kick')"><i class="fa fa-plug"></i> Kick / Putuskan</button>
</div>
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
    <th style="width:50px; text-align:center;"></th>
    <th class="pointer" data-sort="srv" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" data-sort="user" title="Click to sort"><i class="fa fa-sort"></i> User</th>
    <th class="pointer" data-sort="addr" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" data-sort="mac" title="Click to sort"><i class="fa fa-sort"></i> Mac Address</th>
    <th class="pointer text-right" data-sort="up" title="Click to sort"><i class="fa fa-sort"></i> Uptime</th>
    <th class="pointer text-right" data-sort="bi" title="Click to sort"><i class="fa fa-sort"></i> Bytes In</th>
    <th class="pointer text-right" data-sort="bo" title="Click to sort"><i class="fa fa-sort"></i> Bytes Out</th>
    <th class="pointer text-right" data-sort="left" title="Click to sort"><i class="fa fa-sort"></i> Time Left</th>
    <th class="pointer" data-sort="by" title="Click to sort"><i class="fa fa-sort"></i> Login By</th>
    <th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
  </tr>
  </thead>
  <tbody id="tbody"></tbody>
</table>
<?php
$rowsData = array();
for ($i = 0; $i < $TotalReg; $i++) {
	$hotspotactive = $gethotspotactive[$i];
	$rowsData[] = array(
		'id' => $hotspotactive['.id'] ?? '',
		'srv' => $hotspotactive['server'] ?? '',
		'user' => $hotspotactive['user'] ?? '',
		'addr' => $hotspotactive['address'] ?? '',
		'mac' => $hotspotactive['mac-address'] ?? '',
		'up' => formatDTM($hotspotactive['uptime'] ?? ''),
		'bi' => formatBytes($hotspotactive['bytes-in'] ?? 0, 2),
		'bo' => formatBytes($hotspotactive['bytes-out'] ?? 0, 2),
		'left' => formatDTM($hotspotactive['session-time-left'] ?? ''),
		'by' => $hotspotactive['login-by'] ?? '',
		'comm' => $hotspotactive['comment'] ?? '',
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
</div>

<script src="./js/tableview.js?v=<?= @filemtime(dirname(__DIR__) . '/js/tableview.js'); ?>"></script>
<script>
var HS_ACTIVE_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var HS_SESSION = <?= json_encode($session); ?>;
initBulkUI('hs-active', { virtual: true });
initVirtualTableView({
  data: HS_ACTIVE_DATA,
  defaultPerPage: 100,
  searchCols: ['srv', 'user', 'addr', 'mac', 'by', 'comm'],
  renderRow: function(a, checked) {
    var id = tvEsc(a.id), user = tvEsc(a.user), srv = tvEsc(a.srv);
    var sessEnc = encodeURIComponent(HS_SESSION);
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + user + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><span class="pointer" title="Remove ' + user + '" onclick="loadpage(\'./?remove-user-active=' + id + '&session=' + sessEnc + '\')"><i class="fa fa-minus-square text-danger"></i></span></td>' +
      '<td><a title="filter ' + srv + '" href="./?hotspot=active&server=' + encodeURIComponent(a.srv) + '&session=' + sessEnc + '"><i class="fa fa-server"></i> ' + srv + '</a></td>' +
      '<td><a title="Open User ' + user + '" href="./?hotspot-user=' + encodeURIComponent(a.user) + '&session=' + sessEnc + '"><i class="fa fa-edit"></i> ' + user + '</a></td>' +
      '<td>' + tvEsc(a.addr) + '</td>' +
      '<td>' + tvEsc(a.mac) + '</td>' +
      '<td style="text-align:right;">' + tvEsc(a.up) + '</td>' +
      '<td style="text-align:right;">' + tvEsc(a.bi) + '</td>' +
      '<td style="text-align:right;">' + tvEsc(a.bo) + '</td>' +
      '<td style="text-align:right;">' + tvEsc(a.left) + '</td>' +
      '<td>' + tvEsc(a.by) + '</td>' +
      '<td>' + tvEsc(a.comm) + '</td>' +
      '</tr>';
  }
});
</script>