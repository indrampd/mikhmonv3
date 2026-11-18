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
ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  $userProplist = '.id,server,name,password,profile,mac-address,uptime,bytes-in,bytes-out,comment,disabled,limit-uptime,limit-bytes-total';
  if ($prof == "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      ".proplist" => $userProplist,
    ));
  } elseif ($prof != "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?profile" => "$prof",
      ".proplist" => $userProplist,
    ));
  }
  if ($comm != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?comment" => "$comm",
      ".proplist" => $userProplist,
    ));
  }
  $exp = $_GET['exp'];
  if ($exp != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?limit-uptime" => "1s",
      ".proplist" => $userProplist,
    ));
  }
  $TotalReg = is_array($getuser) ? count($getuser) : 0;
  $counttuser = $TotalReg;
  $getprofile = $API->comm("/ip/hotspot/user/profile/print");
  $TotalReg2 = count($getprofile);
}
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> <?= $_users ?>
      <span style="font-size: 14px">
        <?php
        if ($counttuser == 0) {
          echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "</script>";
        } ?>
         &nbsp; | &nbsp; <a href="./?hotspot-user=add&session=<?= $session; ?>" title="Add User"><i class="fa fa-user-plus"></i> <?= $_add ?></a>
        &nbsp; | &nbsp; <a href="./?hotspot-user=generate&session=<?= $session; ?>" title="Generate User"><i class="fa fa-users"></i> <?= $_generate ?></a>
         &nbsp; | &nbsp; <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=script" title="Download User List as Mikrotik Script"><i class="fa fa-download"></i> Script</a>&nbsp; | &nbsp; <a href="<?= str_replace("=users", "=export-users", $url); ?>&export=csv" title="Download User List as CSV"><i class="fa fa-download"></i> CSV</a>
        </span>  &nbsp;
        <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
    </h3>
    
</div>
<div class="card-body">
  <div class="row">
   <div class="col-6 pd-t-5 pd-b-5">
  <div class="input-group">
    <div class="input-group-4 col-box-4">
      <input id="tableSearch" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?>">
    </div>
    <div class="input-group-4 col-box-4">
      <select style="padding:5px;" class="group-item group-item-m" onchange="location = this.value; loader()" title="Filter by Profile">
        <option><?= $_profile ?> </option>
        <option value="./?hotspot=users&profile=all&session=<?= $session; ?>"><?= $_show_all ?></option>
      <?php
      for ($i = 0; $i < $TotalReg2; $i++) {
        $profile = $getprofile[$i];
        echo "<option value='./?hotspot=users&profile=" . $profile['name'] . "&session=" . $session . "'>" . $profile['name'] . "</option>";
      }
      ?>
    </select>
  </div>
  <div class="input-group-4 col-box-4">
    <select style="padding:5px;" class="group-item group-item-r" id="comment" name="comment" onchange="location = './?hotspot=users&comment='+ this.value +'&session=<?= $session;?>';">
    <?php
    if ($comm != "") {
    } else {
      echo "<option value=''>".$_comment."</option>";
    }
    $TotalReg = count($getuser);
    for ($i = 0; $i < $TotalReg; $i++) {
      $ucomment = $getuser[$i]['comment'];
      $uprofile = $getuser[$i]['profile'];
      $acomment .= ",".$ucomment."#". $uprofile;
    }

    $ocomment=  explode(",",$acomment);
    
    $comments=array_count_values($ocomment) ;
    foreach ($comments as $tcomment=>$value) {

      if (is_numeric(substr($tcomment, 3, 3))) {
       
        echo "<option value='" . explode("#",$tcomment)[0] . "' >". explode("#",$tcomment)[0]." ".explode("#",$tcomment)[1]. " [".$value. "]</option>";
       }
 
    }

    ?>
    </select>
  </div>
  </div>
  </div>
 
  <div class="col-6">
    <?php if ($comm != "") { ?>
  <button class="btn bg-red" onclick="if(confirm('Are you sure to delete username by comment (<?= $comm; ?>)?')){loadpage('./?remove-hotspot-user-by-comment=<?= $comm; ?>&session=<?= $session; ?>');loader();}else{}" title="Remove user by comment <?= $comm; ?>">  <i class="fa fa-trash"></i> <?= $_by_comment ?></button>
    <?php ; }else if ($exp == "1"){ ?>
  <button class="btn bg-red" onclick="if(confirm('Are you sure to delete users?')){loadpage('./?remove-hotspot-user-expired=1&session=<?= $session; ?>');loader();}else{}" title="Remove user expired">  <i class="fa fa-trash"></i> Expired Users</button>
      <?php } ?>
  <script>
    function printV(a,b){
    var comm = document.getElementById('comment').value;
    var url = "./voucher/print.php?id="+comm+"&"+a+"="+b+"&session=<?= $session; ?>";
    if (comm === "" ){
      <?php if ($currency == in_array($currency, $cekindo['indo'])) { ?>
      alert('Silakan pilih salah satu Comment terlebih dulu!');
      <?php
    } else { ?>
      alert('Please choose one of the Comments first!');
      <?php
    } ?>
    }else{
      var win = window.open(url, '_blank');
      win.focus();
    }}
  </script>
  <button class="btn bg-primary" title='Print' onclick="printV('qr','no');"><i class="fa fa-print"></i> <?= $_print_default ?></button>
  <button class="btn bg-primary" title='Print QR' onclick="printV('qr','yes');"><i class="fa fa-print"></i> <?= $_print_qr ?></button>
  <button class="btn bg-primary" title='Print Small'onclick="printV('small','yes');"><i class="fa fa-print"></i> <?= $_print_small ?></button>
  </div>
</div>

<!-- Bulk bar: muncul saat ada checkbox terpilih -->
<div id="bulkBar" style="display:none; margin:8px 0 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
  <strong id="bulkCount">0</strong>&nbsp;selected:
  <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="bulkAct('enable')"><i class="fa fa-unlock"></i> Enable</button>
  <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="bulkAct('disable')"><i class="fa fa-lock"></i> Disable</button>
  <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Remove</button>
</div>

<div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
    <th style="min-width:50px;" class="align-middle text-center" id="cuser"><?= $counttuser; ?></th>
    <th style="min-width:50px;" class="pointer" data-sort="srv" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" data-sort="name" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th>Print</th>
    <th class="pointer" data-sort="prof" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?></th>
	  <th class="pointer" data-sort="mac" title="Click to sort"><i class="fa fa-sort"></i> Mac Address</th>
    <th class="text-right align-middle pointer" data-sort="up" title="Click to sort"><i class="fa fa-sort"></i> <?= $_uptime_user ?></th>
    <th class="text-right align-middle pointer" data-sort="bi" title="Click to sort"><i class="fa fa-sort"></i> Bytes In</th>
    <th class="text-right align-middle pointer" data-sort="bo" title="Click to sort"><i class="fa fa-sort"></i> Bytes Out</th>
    <th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
    </tr>
  </thead>
  <tbody id="tbody"></tbody>
</table>
</div>
<?php
$rowsData = array();
for ($i = 0; $i < $TotalReg; $i++) {
  $userdetails = $getuser[$i];
  $uname = $userdetails['name'] ?? '';
  $upass = $userdetails['password'] ?? '';
  $ucomment = $userdetails['comment'] ?? '';
  $utimelimit = $userdetails['limit-uptime'] ?? '';
  $utimelimit = ($utimelimit == '1s') ? ' expired' : (' ' . $utimelimit);
  $udatalimit = $userdetails['limit-bytes-total'] ?? '';
  $udatalimit = ($udatalimit == '') ? '' : (' ' . formatBytes($udatalimit, 2));
  $rowsData[] = array(
    'id' => $userdetails['.id'] ?? '',
    'srv' => $userdetails['server'] ?? '',
    'name' => $uname,
    'mode' => ($uname == $upass) ? 'vc' : 'up',
    'prof' => $userdetails['profile'] ?? '',
    'mac' => $userdetails['mac-address'] ?? '',
    'up' => formatDTM($userdetails['uptime'] ?? ''),
    'bi' => formatBytes($userdetails['bytes-in'] ?? 0, 2),
    'bo' => formatBytes($userdetails['bytes-out'] ?? 0, 2),
    'comm' => $ucomment,
    'dis' => ($userdetails['disabled'] ?? '') === 'true' ? 1 : 0,
    'tlim' => $utimelimit,
    'dlim' => $udatalimit,
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
</div>

<script src="./js/tableview.js?v=<?= @filemtime(dirname(__DIR__) . '/js/tableview.js'); ?>"></script>
<script>
// Bulk (checkbox) + Filter + pagination virtual (hanya render halaman aktif)
var HS_USERS_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var HS_SESSION = <?= json_encode($session); ?>;
initBulkUI('hotspot', { virtual: true });
initVirtualTableView({
  data: HS_USERS_DATA,
  defaultPerPage: 100,
  searchCols: ['srv', 'name', 'prof', 'mac', 'comm', 'tlim', 'dlim'],
  renderRow: function(u, checked) {
    var uid = tvEsc(u.id);
    var uname = tvEsc(u.name);
    var uEnc = encodeURIComponent(u.name);
    var sessEnc = encodeURIComponent(HS_SESSION);
    var toggleBtn = u.dis
      ? '<span class="text-warning pointer" title="Enable User ' + uname + '" onclick="loadpage(\'./?enable-hotspot-user=' + uid + '&session=' + sessEnc + '\')"><i class="fa fa-lock"></i></span>'
      : '<span class="pointer" title="Disable User ' + uname + '" onclick="loadpage(\'./?disable-hotspot-user=' + uid + '&session=' + sessEnc + '\')"><i class="fa fa-unlock"></i></span>';
    var popup = "javascript:window.open('./voucher/print.php?user=" + u.mode + "-" + uEnc + "&qr=no&session=" + sessEnc + "','_blank','width=320,height=550').print();";
    var popupQR = "javascript:window.open('./voucher/print.php?user=" + u.mode + "-" + uEnc + "&qr=yes&session=" + sessEnc + "','_blank','width=320,height=550').print();";
    var cTitle = tvEsc(u.comm + u.dlim + u.tlim);
    var commCell = '';
    if (u.name !== 'default-trial') {
      var pfx = u.comm.substring(0, 3);
      if (pfx === 'vc-' || pfx === 'up-') {
        commCell = '<a href="./?hotspot=users&comment=' + encodeURIComponent(u.comm) + '&session=' + sessEnc + '" title="Filter by ' + tvEsc(u.comm) + '"><i class="fa fa-search"></i> ' + tvEsc(u.comm) + ' ' + tvEsc(u.dlim) + ' ' + tvEsc(u.tlim) + '</a>';
      } else if (u.tlim === ' expired') {
        commCell = '<a href="./?hotspot=users&profile=all&exp=1&session=' + sessEnc + '" title="Filter by expired"><i class="fa fa-search"></i> ' + tvEsc(u.comm) + ' ' + tvEsc(u.dlim) + ' ' + tvEsc(u.tlim) + '</a>';
      } else {
        commCell = tvEsc(u.comm) + ' ';
      }
    }
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + uid + '" data-name="' + uname + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><i class="fa fa-minus-square text-danger pointer" onclick="if(confirm(\'Are you sure to delete username (' + uname + ')?\')){loadpage(\'./?remove-hotspot-user=' + uid + '&session=' + sessEnc + '\')}" title="Remove ' + uname + '"></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + toggleBtn + '</td>' +
      '<td>' + tvEsc(u.srv) + '</td>' +
      '<td><a title="Open User ' + uname + '" href="./?hotspot-user=' + uid + '&session=' + sessEnc + '"><i class="fa fa-edit"></i> ' + uname + '</a></td>' +
      '<td class="text-center"><a title="Print ' + uname + '" href="' + popup + '"><i class="fa fa-print"></i></a> &nbsp; <a title="Print ' + uname + '" href="' + popupQR + '"><i class="fa fa-qrcode"></i></a></td>' +
      '<td>' + tvEsc(u.prof) + '</td>' +
      '<td style="text-align:left">' + tvEsc(u.mac) + '</td>' +
      '<td style="text-align:right">' + tvEsc(u.up) + '</td>' +
      '<td style="text-align:right">' + tvEsc(u.bi) + '</td>' +
      '<td style="text-align:right">' + tvEsc(u.bo) + '</td>' +
      '<td style="max-width:300px; overflow:hidden; text-overflow:ellipsis;" title="' + cTitle + '">' + commCell + '</td>' +
      '</tr>';
  }
});
</script>
	
