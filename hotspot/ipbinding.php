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

	$getbinding = $API->comm("/ip/hotspot/ip-binding/print");
	$TotalReg = count($getbinding);

	$countbinding = $API->comm("/ip/hotspot/ip-binding/print", array(
		"count-only" => "",
	));
}

?>
<div class="row">
<div id="reloadbinding">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-address-book"></i> <?= $_ip_bindings ?> 
<?php
if ($countbinding < 2) {
	echo "$countbinding item";
} elseif ($countbinding > 1) {
	echo "$countbinding items";
};
?>
    </h3>
</div>
<div class="card-body">	   
<div class="w-6">
    <input id="tableSearch" type="text" class="form-control" placeholder="Search..">
  </div>
<!-- Bulk bar: muncul saat ada checkbox terpilih -->
<div id="bulkBar" style="display:none; margin:8px 0 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
  <strong id="bulkCount">0</strong>&nbsp;selected:
  <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="bulkAct('enable')"><i class="fa fa-unlock"></i> Enable</button>
  <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="bulkAct('disable')"><i class="fa fa-lock"></i> Disable</button>
  <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Remove</button>
</div>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh">  	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap"> 
 <thead>
  <tr>
    <th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
    <th></th>
    <th></th>
    <th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th class="pointer" data-sort="mac" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" data-sort="addr" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" data-sort="toaddr" title="Click to sort"><i class="fa fa-sort"></i> To Address</th>
    <th class="pointer" data-sort="srv" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
  </tr>
  </thead>
  <tbody id="tbody"></tbody>
</table>
</div>
<?php
$rowsData = array();
for ($i = 0; $i < $TotalReg; $i++) {
	$binding = $getbinding[$i];
	$rowsData[] = array(
		'id' => $binding['.id'] ?? '',
		'mac' => $binding['mac-address'] ?? '',
		'addr' => $binding['address'] ?? '',
		'toaddr' => $binding['to-address'] ?? '',
		'srv' => $binding['server'] ?? '',
		'comm' => $binding['comment'] ?? '',
		'dis' => (($binding['disabled'] ?? '') == 'true') ? 1 : 0,
		'byp' => (($binding['bypassed'] ?? '') == 'true') ? 1 : 0,
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
<script src="./js/tableview.js?v=<?= @filemtime(dirname(__DIR__) . '/js/tableview.js'); ?>"></script>
<script>
var IP_BINDING_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var IP_SESSION = <?= json_encode($session); ?>;
initBulkUI('ip-binding', { virtual: true });
initVirtualTableView({
  data: IP_BINDING_DATA,
  defaultPerPage: 100,
  searchCols: ['comm', 'mac', 'addr', 'toaddr', 'srv'],
  renderRow: function(b, checked) {
    var id = tvEsc(b.id), mac = tvEsc(b.mac), addr = tvEsc(b.addr);
    var sessEnc = encodeURIComponent(IP_SESSION);
    var idEnc = encodeURIComponent(b.id);
    var toggleBtn = b.dis
      ? '<span class="text-warning btnsmall pointer" title="Enable Binding ' + addr + '" onclick="loadpage(\'./?enable-ip-binding=' + idEnc + '&session=' + sessEnc + '\')"><i class="fa fa-lock"></i></span>'
      : '<span class="btnsmall pointer" title="Disable Binding ' + addr + '" onclick="loadpage(\'./?disable-ip-binding=' + idEnc + '&session=' + sessEnc + '\')"><i class="fa fa-unlock"></i></span>';
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + mac + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><i class="fa fa-minus-square text-danger pointer" onclick="if(confirm(\'Are you sure to delete (' + mac + ')?\')){loadpage(\'./?remove-ip-binding=' + idEnc + '&mac=' + encodeURIComponent(b.mac) + '&addr=' + encodeURIComponent(b.addr) + '&session=' + sessEnc + '\')}" title="Remove ' + mac + '"></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + toggleBtn + '</td>' +
      '<td style="text-align:center;">' + (b.byp ? '<b style="color:#0091EA;">P</b>' : '') + '</td>' +
      '<td>' + tvEsc(b.comm) + '</td>' +
      '<td>' + mac + '</td>' +
      '<td>' + addr + '</td>' +
      '<td>' + tvEsc(b.toaddr) + '</td>' +
      '<td>' + tvEsc(b.srv) + '</td>' +
      '</tr>';
  }
});
</script>
</div>
</div>
</div>
<div class="modal-window" id="help" aria-hidden="true">
  <div>
  	<header><h1>Help</h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p> 
		    <?php if ($currency == in_array($currency, $cekindo['indo'])) { ?>
		      <ul>
		        <li>Masuk ke menu Hosts.</li>
		        <li>Klik IP Address yang ingin di binding.</li>
		      </ul>
		    <?php 
				} else { ?>
		      <ul>
		        <li>Go to Hosts menu.</li>
		        <li>Click the IP Address that you want to binding.</li>
		      </ul>
		    <?php 
				} ?>
	</p>
  </div>
</div>
</div>
</div>
