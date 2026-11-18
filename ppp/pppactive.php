<?php
/*
 * Active PPPoE / PPP Connections for Mikhmon v3
 * Styled 1:1 with Mikhmon Hotspot Active UI
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$getactive = $API->comm("/ppp/active/print");
$TotalReg = count($getactive);
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-plug"></i> <?= $_ppp_active ?? "PPPoE Active" ?>
      <span style="font-size: 14px">
        <?php if ($TotalReg < 2) {
            echo "{$TotalReg} item";
        } else {
            echo "{$TotalReg} items";
        } ?>
        &nbsp; | &nbsp; <a href="./?ppp=secrets&session=<?= $session; ?>" title="Secret List"><i class="fa fa-users"></i> <?= $_ppp_secrets ?? "User Secrets" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Reload"><i class="fa fa-refresh"></i> Refresh</a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?></i></small>
    </h3>
</div>

<div class="card-body">
  <div class="row">
    <div class="col-6 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-8 col-box-8">
          <input id="tableSearch" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari user aktif..." ?>">
        </div>
        <div class="input-group-4 col-box-4">
          <button class="group-item group-item-r" onclick="location.reload(); loader();" title="Refresh"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
      </div>
    </div>
    <div class="col-6 text-right pd-t-5 pd-b-5">
      <span class="badge bg-success pd-5 radius-3"><i class="fa fa-circle"></i> Online: <?= $TotalReg; ?></span>
    </div>
  </div>

<!-- Bulk bar: muncul saat ada checkbox terpilih -->
  <div id="bulkBar" style="display:none; margin:8px 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
    <strong id="bulkCount">0</strong>&nbsp;terpilih:
    <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('kick')"><i class="fa fa-plug"></i> Kick / Putuskan</button>
  </div>

  <div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
      <tr>
        <th style="width:30px; text-align:center;" title="Select all on this page"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
        <th style="width:50px; text-align:center;" id="cuser"><?= $TotalReg; ?></th>
        <th class="pointer" data-sort="srv" title="Click to sort"><i class="fa fa-sort"></i> Service</th>
        <th class="pointer" data-sort="user" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?? "User" ?></th>
        <th class="pointer" data-sort="addr" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
        <th class="pointer" data-sort="caller" title="Click to sort"><i class="fa fa-sort"></i> Caller ID (MAC)</th>
        <th class="text-right pointer" data-sort="up" title="Click to sort"><i class="fa fa-sort"></i> Uptime</th>
        <th class="pointer" data-sort="enc" title="Click to sort"><i class="fa fa-sort"></i> Encoding</th>
        <th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?? "Comment" ?></th>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
  </div>
  <?php
  $rowsData = array();
  for ($i = 0; $i < $TotalReg; $i++) {
      $act = $getactive[$i];
      $rowsData[] = array(
          'id' => $act['.id'] ?? '',
          'user' => $act['name'] ?? '',
          'srv' => $act['service'] ?? 'pppoe',
          'caller' => $act['caller-id'] ?? '-',
          'addr' => $act['address'] ?? '-',
          'up' => formatDTM($act['uptime'] ?? ''),
          'enc' => $act['encoding'] ?? '-',
          'comm' => $act['comment'] ?? '',
      );
  }
  ?>

  <!-- Footer pagination -->
  <div class="pd-t-5" style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; justify-content:space-between;">
    <div style="display:flex; align-items:center; gap:8px; font-size:12px;">
      <span id="pageInfo">&nbsp;</span>
      <select id="perPageSel" class="form-control" style="width:auto; padding:2px 6px; font-size:12px;" title="Baris per halaman">
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
var PPP_ACTIVE_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var PPP_SESSION = <?= json_encode($session); ?>;
initBulkUI('ppp-active', { virtual: true });
initVirtualTableView({
  data: PPP_ACTIVE_DATA,
  defaultPerPage: 100,
  searchCols: ['srv', 'user', 'addr', 'caller', 'enc', 'comm'],
  renderRow: function(a, checked) {
    var id = tvEsc(a.id), user = tvEsc(a.user);
    var sessEnc = encodeURIComponent(PPP_SESSION);
    return '<tr class="row-data">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + user + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><span class="pointer" title="Putuskan Sesi ' + user + '" onclick="if(confirm(\'Putuskan koneksi (' + user + ')?\')){loadpage(\'./?remove-pactive=' + encodeURIComponent(a.id) + '&session=' + sessEnc + '\');loader();}"><i class="fa fa-minus-square text-danger"></i></span></td>' +
      '<td><span class="badge bg-info">' + tvEsc(a.srv) + '</span></td>' +
      '<td><a title="Open Secret ' + user + '" href="./?secret=' + encodeURIComponent(a.user) + '&session=' + sessEnc + '"><i class="fa fa-edit"></i> ' + user + '</a></td>' +
      '<td>' + tvEsc(a.addr) + '</td>' +
      '<td><code>' + tvEsc(a.caller) + '</code></td>' +
      '<td class="text-right">' + tvEsc(a.up) + '</td>' +
      '<td>' + tvEsc(a.enc) + '</td>' +
      '<td>' + tvEsc(a.comm) + '</td>' +
      '</tr>';
  }
});
</script>
