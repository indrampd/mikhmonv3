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

	$idhr = $_GET['idhr'];
	$idbl = $_GET['idbl'];
	$idbl2 = explode("/",$idhr)[0].explode("/",$idhr)[2];
	if ($idhr != ""){
		$_SESSION['report'] = "&idhr=".$idhr;
	} elseif ($idbl != ""){
		$_SESSION['report'] = "&idbl=".$idbl;
	} else {
		$_SESSION['report'] = "";
	}
	$_SESSION['idbl'] = $idbl;
	$remdata = ($_POST['remdata']);
	$prefix = $_GET['prefix'];
	

	$gettimezone = $API->comm("/system/clock/print");
	$timezone = $gettimezone[0]['time-zone-name'];
	date_default_timezone_set($timezone);

	if (isset($remdata)) {
		if (strlen($idhr) > "0") {
			if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
				$API->write('/system/script/print', false);
				$API->write('?source=' . $idhr . '', false);
				$API->write('=.proplist=.id');
				$ARREMD = $API->read();
				for ($i = 0; $i < count($ARREMD); $i++) {
					$API->write('/system/script/remove', false);
					$API->write('=.id=' . $ARREMD[$i]['.id']);
					$READ = $API->read();

				}
			}
		} elseif (strlen($idbl) > "0") {
			if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
				mikhmon_remove_scripts_by_month($API, $idbl);
			}

		}
		echo "<script>window.location='./?report=selling&session=" . $session . "'</script>";
	}

	if ($prefix != "") {
		$fprefix = "-prefix-[" . $prefix . "]";
	} else {
		$fprefix = "";
	}
	if (strlen($idhr) > "0") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$getData = $API->comm("/system/script/print", array(
				"?source" => "$idhr",
			));
			$TotalReg = count($getData);
		}
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif (strlen($idbl) > "0") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$getData = mikhmon_scripts_by_month($API, $idbl);
			$TotalReg = count($getData);
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif ($idhr == "" || $idbl == "") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$getData = $API->comm("/system/script/print", array(
				"?comment" => "mikhmon",
			));
			$TotalReg = count($getData);
		}
		$filedownload = "all";
		$shf = "text";
		$shd = "none";
	}
	
}
?>
		<script>
			function downloadCSV(csv, filename) {
			  var csvFile;
			  var downloadLink;
			  // CSV file
			  csvFile = new Blob([csv], {type: "text/csv"});
			  // Download link
			  downloadLink = document.createElement("a");
			  // File name
			  downloadLink.download = filename;
			  // Create a link to the file
			  downloadLink.href = window.URL.createObjectURL(csvFile);
			  // Hide download link
			  downloadLink.style.display = "none";
			  // Add the link to DOM
			  document.body.appendChild(downloadLink);
			  // Click download link
			  downloadLink.click();
			  }
			  
		function getSellingSelectedRows() {
			var all = window.SELLING_DATA || [];
			var sel = window._bulkSelected;
			if (!sel || !sel.size) return [];
			var out = [];
			for (var i = 0; i < all.length; i++) {
				if (sel.has(all[i].id)) out.push(all[i]);
			}
			return out;
		}
		function exportTableToCSV(filename, selectedOnly) {
			var rows = selectedOnly ? getSellingSelectedRows() : (window.SELLING_DATA || []);
			if (!rows.length) return;
			var csv = ['No,Date,Time,Username,Profile,Comment,Price'];
			for (var i = 0; i < rows.length; i++) {
				var r = rows[i];
				csv.push([i + 1, r.tgl, r.time, r.user, r.prof, r.comm, r.price].join(','));
			}
			downloadCSV(csv.join("\n"), filename);
		}
		function printSellingReport(queryStr, selectedOnly) {
			var url = './report/print.php?' + queryStr;
			var sel = window._bulkSelected;
			if (!selectedOnly || !sel || !sel.size) {
				window.open(url, '_blank');
				return;
			}
			var form = document.createElement('form');
			form.method = 'POST';
			form.action = url;
			form.target = '_blank';
			var inp = document.createElement('input');
			inp.type = 'hidden';
			inp.name = 'selected_ids';
			inp.value = JSON.stringify(Array.from(sel.keys()));
			form.appendChild(inp);
			document.body.appendChild(form);
			form.submit();
			document.body.removeChild(form);
		}

// https://stackoverflow.com/questions/33218607/use-inline-css-to-apply-usd-currency-format-within-html-table
function number_format(number, decimals, dec_point, thousands_sep) {

  number = (number + '')
    .replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function(n, prec) {
      var k = Math.pow(10, prec);
      return '' + (Math.round(n * k) / k)
        .toFixed(prec);
    };
  // Fix for IE parseFloat(0.55).toFixed(0) = 0;
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
    .split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '')
    .length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1)
      .join('0');
  }
  return s.join(dec);
}
        
		function formatSellingTotal(sum) {
			var th = document.getElementById('total');
			if (!th) return;
			<?php if ($currency == in_array($currency, $cekindo['indo'])) {
				echo 'th.innerHTML = "' . $currency . ' " + number_format(sum, "", "", ".");';
			} else {
				echo 'th.innerHTML = "' . $currency . ' " + number_format(sum, 2, ".", ",");';
			} ?>
		}
		</script>

<script>
$(document).ready(function(){
  $("#openResume").click(function(){
    notify("Calculating data");
    window.location = "./?report=resume-report&idbl=<?= $idbl;?>&session=<?= $session;?>";
  });
});
</script>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-money"></i> <?= $_selling_report ?> <?= ucfirst($idhr) . ucfirst(substr($idbl,0,3).' '.substr($idbl,3,5));	if ($prefix != "") {echo " prefix [" . $prefix . "]";} ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3>
</div>
<div class="card-body">
<div class="row">
	<div class="row">
	<div class="col-12">
		<div style="padding-bottom: 5px; padding-top: 5px;">
		  <input id="tableSearch" type="text" class="form-control" style="float:left; margin-top: 6px; max-width: 150px;" placeholder="<?= $_search ?>">&nbsp;
		  <button name="help" class="btn bg-primary" onclick="location.href='#help';" title="Help"><i class="fa fa-question"></i> <?= $_help ?></button>
		  <button class="btn bg-primary" onclick="exportTableToCSV('report-mikhmon-<?= $filedownload . $fprefix; ?>.csv', false)" title="Download selling report"><i class="fa fa-download"></i> CSV</button>
			<button class="btn bg-primary" onclick="location.href='./?report=selling&session=<?= $session; ?>';" title="Reload all data"><i class="fa fa-search"></i> <?= $_all ?></button>
			<?php if(!empty($idbl)){echo '<button name="resume" id="openResume" class="btn bg-primary"title="Resume Report"><i class="fa fa-area-chart"></i> '.$_resume.'</button>';}else{
				echo '<a class="btn bg-primary" href="./?report=selling&idbl='.$idbl2.'&session='.$session.'" title="Show '.ucfirst(substr($idbl2,0,3).' '.substr($idbl2,3,5)).'"><i class="fa fa-search"></i> '.ucfirst(substr($idbl2,0,3).' '.substr($idbl2,3,5)).'</a>';}?>
		  <button name="print" class="btn bg-primary" onclick="printSellingReport(<?= htmlspecialchars(json_encode(explode("?report=selling&", $url)[1] ?? ''), ENT_QUOTES); ?>, false);" title="Print"><i class="fa fa-print"></i> <?= $_print ?></button>
		  <button style="display: <?= $shd; ?>;" name="remdata" class="btn bg-danger" onclick="location.href='#remdata';" title="Delete Data <?= $filedownload; ?>"><i class="fa fa-trash"></i> <?= $_delete_data.' '. $filedownload; ?></button>
		</div>
	</div>
	</div>
		<div class="input-group mr-b-10">
			<div class="input-group-1 col-box-2">
			<select style="padding:5px;" class="group-item group-item-l" title="<?= $_days ?>" id="D">
        			<?php
										$day = explode("/", $idhr)[1];
										if ($day != "") {
											echo "<option value='" . $day . "'>" . $day . "</option>";
										}
										echo "<option value=''>Day</option>";

										for ($x = 1; $x <= 31; $x++) {
											if (strlen($x) == 1) {
												$x = "0" . $x;
											} else {
												$x = $x;
											}
											echo "<option value='" . $x . "'>" . $x . "</option>";
										}
										?>
    		</select>
			</div>
			<div class="input-group-2 col-box-4">
			<select style="padding:5px;" class="group-item group-item-md" title="Month" id="M">
        			<?php
										$idbls = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
										$idblf = array(1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
										$month = explode("/", $idhr)[0];
										$month1 = substr($idbl, 0, 3);

										if ($month != "") {
											$fm = array_search($month, $idbls);
											echo "<option value='" . $month . "'>" . $idblf[$fm] . "</option>";
										} elseif ($month1 != "") {
											$fm = array_search($month1, $idbls);
											echo "<option value=" . $month1 . ">" . $idblf[$fm] . "</option>";
										} else {
											echo "<option value=" . $idbls[date("n")] . ">" . $idblf[date("n")] . "</option>";
										}
										for ($x = 1; $x <= 12; $x++) {
											echo "<option value='" . $idbls[$x] . "''>" . $idblf[$x] . "</option>";
										}
										?>
    		</select>
			</div>
			<div class="input-group-2 col-box-3">
			<select style="padding:5px;" class="group-item group-item-md" title="Year" id="Y">
        			<?php
										$year = explode("/", $idhr)[2];
										$year1 = substr($idbl, 3, 4);

										if ($year != "") {
											echo "<option>" . $year . "</option>";
										} elseif ($year1 != "") {
											echo "<option>" . $year1 . "</option>";
										}
											echo "<option>" . date("Y") . "</option>";
										
										for ($Y = 2018; $Y <= date("Y"); $Y++) {
											if ($Y == date("Y")) {
											} else {
												echo "<option value='" . $Y . "''>" . $Y . "</option>";
											}
										}
										?>
    		</select>
			</div>
            <div class="input-group-2 col-box-3">
				<div style="padding:3.5px;"  class="group-item group-item-r text-center pointer" onclick="filterR(); loader();"><i class="fa fa-search"></i> Filter</div>
			</div>
			<script type="text/javascript">
				
				function filterR(){
					var D = document.getElementById('D').value;
					var M = document.getElementById('M').value;
					var Y = document.getElementById('Y').value;
					var X = (document.getElementById('tableSearch') || {}).value || '';

					if(D !== ""){
						window.location='./?report=selling&idhr='+M+'/'+D+'/'+Y+'&prefix='+X+'&session=<?= $session; ?>';
					}else if(D === ""){
						window.location='./?report=selling&idbl='+M+Y+'&prefix='+X+'&session=<?= $session; ?>';
					}
					
				}
			</script>
		</div>
		  <!-- Bulk bar: muncul saat ada checkbox terpilih -->
		  <div id="bulkBar" style="display:none; margin:8px 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
		    <strong id="bulkCount">0</strong>&nbsp;selected:
		    <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="exportTableToCSV('report-mikhmon-selected-<?= $filedownload . $fprefix; ?>.csv', true)"><i class="fa fa-download"></i> CSV</button>
		    <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="printSellingReport(<?= htmlspecialchars(json_encode(explode("?report=selling&", $url)[1] ?? ''), ENT_QUOTES); ?>, true)"><i class="fa fa-print"></i> Cetak</button>
		    <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Remove</button>
		  </div>
		  <div class="overflow box-bordered" style="max-height: 70vh">
			<table id="dataTable" class="table table-bordered table-hover text-nowrap">
				<thead class="thead-light">
				<tr>
				  <th colspan=6 ><?= $_selling_report ?> <?= $filedownload . $fprefix; ?><b style="font-size:0;">,,,,</b></th>
				  <th style="text-align:right;"><?= $_total ?></th>
				  <th style="text-align:right;" id="total"></th>
				</tr>
				<tr>
				  <th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
				  <th >&#8470;</th>
					<th class="pointer" data-sort="tgl" title="Click to sort"><i class="fa fa-sort"></i> <?= $_date ?></th>
					<th class="pointer" data-sort="time" title="Click to sort"><i class="fa fa-sort"></i> <?= $_time ?></th>
					<th class="pointer" data-sort="user" title="Click to sort"><i class="fa fa-sort"></i> <?= $_user_name ?></th>
					<th class="pointer" data-sort="prof" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?></th>
					<th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
					<th class="pointer" data-sort="price" style="text-align:right;" title="Click to sort"><i class="fa fa-sort"></i> <?= $_price ?></th>
				</tr>
				</thead>
				<tbody id="tbody"></tbody>
			</table>
		</div>
		<?php
		$rowsData = array();
		$dataresume = '';
		$totalresume = 0;
		$no = 0;
		for ($i = 0; $i < $TotalReg; $i++) {
			$getname = explode("-|-", $getData[$i]['name'] ?? '');
			$uname = $getname[2] ?? '';
			if ($prefix != "" && substr($uname, 0, strlen($prefix)) != $prefix) {
				continue;
			}
			$no++;
			$tgl = $getname[0] ?? '';
			$ltime = $getname[1] ?? '';
			$price = $getname[3] ?? '0';
			$profile = $getname[7] ?? '';
			$comment = $getname[8] ?? '';
			$rowsData[] = array(
				'id' => $getData[$i]['.id'] ?? '',
				'no' => $no,
				'tgl' => $tgl,
				'time' => $ltime,
				'user' => $uname,
				'prof' => $profile,
				'comm' => $comment,
				'price' => $price,
			);
			if ($prefix == "") {
				$dataresume .= $tgl . $price;
				$totalresume += (float)$price;
			}
		}
		if ($prefix == "") {
			$_SESSION['dataresume'] = $dataresume;
			$_SESSION['totalresume'] = $TotalReg . '/' . $totalresume;
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
		window.SELLING_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		(function() {
			var sum = 0;
			for (var i = 0; i < window.SELLING_DATA.length; i++) {
				var p = parseFloat(window.SELLING_DATA[i].price);
				if (!isNaN(p)) sum += p;
			}
			formatSellingTotal(sum);
		})();
		initBulkUI('selling', { virtual: true });
		initVirtualTableView({
			data: window.SELLING_DATA,
			defaultPerPage: 100,
			searchCols: ['tgl', 'time', 'user', 'prof', 'comm', 'price'],
			renderRow: function(r, checked) {
				var id = tvEsc(r.id), user = tvEsc(r.user);
				return '<tr class="row-data">' +
					'<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + user + '"' + (checked ? ' checked' : '') + '></td>' +
					'<td>' + r.no + '</td>' +
					'<td>' + tvEsc(r.tgl) + '</td>' +
					'<td>' + tvEsc(r.time) + '</td>' +
					'<td>' + user + '</td>' +
					'<td>' + tvEsc(r.prof) + '</td>' +
					'<td>' + tvEsc(r.comm) + '</td>' +
					'<td style="text-align:right;">' + tvEsc(r.price) + '</td>' +
					'</tr>';
			}
		});
		</script>
</div>
</div>
</div>

<!-- Modal -->
<div class="modal-window" id="remdata" aria-hidden="true">
  <div>
  	<header><h1><?= $_confirm ?></h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p>
			<?= $_delete_report ?>
	</p>
	<form autocomplete="off" method="post" action="">
	<center>
	<button type="submit" name="remdata" title="Yes" class="btn bg-primary">Yes</button>&nbsp;
	<a class="btn bg-secondary" href="#" title="Close" class="modal-close">No</a>
	</center>
	</form>
  </div>
</div>
<div class="modal-window" id="help" aria-hidden="true">
  <div>
  	<header><h1><?= $_help ?></h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p>
			<?= $_help_report ?>
	</p>
  </div>
</div>
</div>
