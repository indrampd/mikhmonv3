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

	$getcookies = $API->comm("/ip/hotspot/cookie/print");
	$TotalReg = count($getcookies);

	$countcookies = $API->comm("/ip/hotspot/cookie/print", array(
		"count-only" => "",
	));

}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-sitemap"></i> Hotspot Cookies 
<?php
if ($countcookies < 2) {
	echo "$countcookies item";
} elseif ($countcookies > 1) {
	echo "$countcookies items";
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
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh">   	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
  	<th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
  	<th style="width:50px; text-align:center;"></th>
    <th class="pointer" data-sort="user" title="Click to sort"><i class="fa fa-sort"></i> User</th>
    <th class="pointer" data-sort="mac" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" data-sort="domain" title="Click to sort"><i class="fa fa-sort"></i> Domain</th>
    <th class="pointer" data-sort="exp" title="Click to sort"><i class="fa fa-sort"></i> Expires In</th>
  </tr>
  </thead>
  <tbody id="tbody"></tbody>
</table>
</div>
<?php
$rowsData = array();
for ($i = 0; $i < $TotalReg; $i++) {
	$cookies = $getcookies[$i];
	$rowsData[] = array(
		'id' => $cookies['.id'] ?? '',
		'user' => $cookies['user'] ?? '',
		'mac' => $cookies['mac-address'] ?? '',
		'domain' => $cookies['domain'] ?? '',
		'exp' => formatDTM($cookies['expires-in'] ?? ''),
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
var HS_COOKIES_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var HS_SESSION = <?= json_encode($session); ?>;
initBulkUI('cookie', { virtual: true });
initVirtualTableView({
  data: HS_COOKIES_DATA,
  defaultPerPage: 100,
  searchCols: ['user', 'mac', 'domain', 'exp'],
  renderRow: function(c, checked) {
    var id = tvEsc(c.id), user = tvEsc(c.user);
    var sessEnc = encodeURIComponent(HS_SESSION);
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + user + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><span class="pointer" title="Remove ' + user + '" onclick="loadpage(\'./?remove-cookie=' + id + '&session=' + sessEnc + '\')"><i class="fa fa-minus-square text-danger"></i></span></td>' +
      '<td>' + user + '</td>' +
      '<td>' + tvEsc(c.mac) + '</td>' +
      '<td>' + tvEsc(c.domain) + '</td>' +
      '<td>' + tvEsc(c.exp) + '</td>' +
      '</tr>';
  }
});
</script>