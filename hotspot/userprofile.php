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
	echo '
<html>
<head><title>403 Forbidden</title></head>
<body bgcolor="white">
<center><h1>403 Forbidden</h1></center>
<hr><center>nginx/1.14.0</center>
</body>
</html>
';
} else {


// get user profile
	$getprofile = $API->comm("/ip/hotspot/user/profile/print");
	$TotalReg = count($getprofile);
// count user profile
	$countprofile = $API->comm("/ip/hotspot/user/profile/print", array(
		"count-only" => "",
	));
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class=" fa fa-pie-chart"></i> User Profile 
    &nbsp; | &nbsp; <a href="./?user-profile=add&session=<?= $session; ?>" title="Add User"><i class="fa fa-user-plus"></i> Add</a>
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
		<th style="min-width:50px;" class="text-center" >
		<?php
	if ($countprofile < 2) {
		echo "$countprofile item  ";
	} elseif ($countprofile > 1) {
		echo "$countprofile items   ";
	}
	?></th>
		<th class="align-middle pointer" data-sort="name" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
		<th class="align-middle pointer" data-sort="shared" title="Click to sort"><i class="fa fa-sort"></i> Shared<br>Users</th>
		<th class="align-middle pointer" data-sort="rate" title="Click to sort"><i class="fa fa-sort"></i> Rate<br>Limit</th>
		<th class="align-middle pointer" data-sort="mode" title="Click to sort"><i class="fa fa-sort"></i> <?= $_expired_mode ?></th>
		<th class="align-middle pointer" data-sort="valid" title="Click to sort"><i class="fa fa-sort"></i> <?= $_validity ?></th>
		<th class="text-right align-middle pointer" data-sort="price" title="Click to sort"><i class="fa fa-sort"></i> <?= $_price." ".$currency; ?></th>
		<th class="text-right align-middle pointer" data-sort="sprice" title="Click to sort"><i class="fa fa-sort"></i> <?= $_selling_price." ".$currency; ?></th>
		<th class="align-middle pointer" data-sort="lock" title="Click to sort"><i class="fa fa-sort"></i> <?= $_lock_user ?></th>
    </tr>
  </thead>
  <tbody id="tbody"></tbody>
</table>
</div>
<?php
$rowsData = array();
$isIndo = ($currency == in_array($currency, $cekindo['indo']));
for ($i = 0; $i < $TotalReg; $i++) {
	$profiledetalis = $getprofile[$i];
	$pname = $profiledetalis['name'] ?? '';
	$ponlogin = $profiledetalis['on-login'] ?? '';
	$getmonexpired = $API->comm("/system/scheduler/print", array("?name" => "$pname"));
	$monexpired = $getmonexpired[0] ?? array();
	$pmon = $monexpired['name'] ?? '';
	$chkpmon = $monexpired['disabled'] ?? '';
	$moncolor = (empty($pmon) || $chkpmon == "true") ? "text-orange" : "text-green";
	$parts = explode(",", $ponlogin);
	$expmode = $parts[1] ?? '';
	$modeLabel = ($expmode == "rem") ? "Remove" : (($expmode == "ntf") ? "Notice" : (($expmode == "remc") ? "Remove & Record" : (($expmode == "ntfc") ? "Notice & Record" : "")));
	$pRaw = trim($parts[2] ?? '');
	$spRaw = trim($parts[4] ?? '');
	$pFmt = ($pRaw === '' || $pRaw === '0') ? '' : ($isIndo ? number_format((float)$pRaw, 0, ",", ".") : number_format((float)$pRaw, 2));
	$spFmt = ($spRaw === '' || $spRaw === '0') ? '' : ($isIndo ? number_format((float)$spRaw, 0, ",", ".") : number_format((float)$spRaw, 2));
	$rowsData[] = array(
		'id' => $profiledetalis['.id'] ?? '',
		'name' => $pname,
		'mon' => $moncolor,
		'shared' => $profiledetalis['shared-users'] ?? '',
		'rate' => $profiledetalis['rate-limit'] ?? '',
		'mode' => $modeLabel,
		'valid' => $parts[3] ?? '',
		'price' => $pFmt,
		'sprice' => $spFmt,
		'lock' => $parts[6] ?? '',
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
var HS_PROF_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var HS_SESSION = <?= json_encode($session); ?>;
initBulkUI('hs-profile', { virtual: true });
initVirtualTableView({
  data: HS_PROF_DATA,
  defaultPerPage: 100,
  searchCols: ['name', 'shared', 'rate', 'mode', 'valid', 'price', 'sprice', 'lock'],
  renderRow: function(p, checked) {
    var id = tvEsc(p.id), name = tvEsc(p.name);
    var sessEnc = encodeURIComponent(HS_SESSION);
    var idEnc = encodeURIComponent(p.id);
    var nameEnc = encodeURIComponent(p.name);
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + name + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><i class="fa fa-minus-square text-danger pointer" onclick="if(confirm(\'Are you sure to delete profile (' + name + ')?\')){loadpage(\'./?remove-user-profile=' + idEnc + '&pname=' + nameEnc + '&session=' + sessEnc + '\')}" title="Remove ' + name + '"></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a title="Open User by profile ' + name + '" href="./?hotspot=users&profile=' + nameEnc + '&session=' + sessEnc + '"><i class="fa fa-users"></i></a></td>' +
      '<td><a title="Open User Profile ' + name + '" href="./?user-profile=' + idEnc + '&session=' + sessEnc + '"><i class="fa fa-edit"></i> <i class="fa fa-ci fa-circle ' + tvEsc(p.mon) + '"></i> ' + name + '</a></td>' +
      '<td>' + tvEsc(p.shared) + '</td>' +
      '<td>' + tvEsc(p.rate) + '</td>' +
      '<td>' + tvEsc(p.mode) + '</td>' +
      '<td>' + tvEsc(p.valid) + '</td>' +
      '<td style="text-align:right;">' + tvEsc(p.price) + '</td>' +
      '<td style="text-align:right;">' + tvEsc(p.sprice) + '</td>' +
      '<td>' + tvEsc(p.lock) + '</td>' +
      '</tr>';
  }
});
</script>
