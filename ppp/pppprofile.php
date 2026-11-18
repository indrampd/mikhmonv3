<?php
/*
 * PPP Profiles Management for Mikhmon v3
 * Styled 1:1 with Mikhmon Hotspot User Profiles UI
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

$session = $_GET['session'] ?? '';
$getprofile = $API->comm("/ppp/profile/print");
$TotalReg = count($getprofile);
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?? "PPP Profiles" ?>
      <span style="font-size: 14px">
        <?php if ($TotalReg < 2) {
            echo "{$TotalReg} item";
        } else {
            echo "{$TotalReg} items";
        } ?>
        &nbsp; | &nbsp; <a href="./?ppp=add-profile&session=<?= $session; ?>" title="Tambah Profile"><i class="fa fa-plus-square"></i> <?= $_add ?? "Tambah" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=secrets&session=<?= $session; ?>" title="Secret List"><i class="fa fa-users"></i> <?= $_ppp_secrets ?? "User Secrets" ?></a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?></i></small>
    </h3>
</div>

<div class="card-body">
  <div class="row">
    <div class="col-6 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-8 col-box-8">
          <input id="tableSearch" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari profile..." ?>">
        </div>
      </div>
    </div>
    <div class="col-6 text-right pd-t-5 pd-b-5">
      <span class="badge bg-secondary pd-5 radius-3"><i class="fa fa-pie-chart"></i> Total: <?= $TotalReg; ?></span>
    </div>
  </div>

  <!-- Bulk bar: muncul saat ada checkbox terpilih -->
  <div id="bulkBar" style="display:none; margin:8px 0 0; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
    <strong id="bulkCount">0</strong>&nbsp;terpilih:
    <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Hapus</button>
  </div>

  <div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
      <tr>
        <th style="width:30px; text-align:center;" title="Pilih semua di halaman ini"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
        <th style="min-width: 50px;" class="text-center align-middle" id="cuser"><?= $TotalReg; ?></th>
        <th class="pointer" data-sort="name" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?? "Profile Name" ?></th>
        <th class="pointer" data-sort="laddr" title="Click to sort"><i class="fa fa-sort"></i> Local Address</th>
        <th class="pointer" data-sort="raddr" title="Click to sort"><i class="fa fa-sort"></i> Remote Address</th>
        <th class="pointer" data-sort="rate" title="Click to sort"><i class="fa fa-sort"></i> Rate Limit (Rx/Tx)</th>
        <th class="pointer" data-sort="only" title="Click to sort"><i class="fa fa-sort"></i> Only One</th>
        <th class="pointer" data-sort="dns" title="Click to sort"><i class="fa fa-sort"></i> DNS Server</th>
        <th class="pointer" data-sort="comm" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?? "Comment" ?></th>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
  </div>
  <?php
  $rowsData = array();
  for ($i = 0; $i < $TotalReg; $i++) {
      $prof = $getprofile[$i];
      $pname = $prof['name'] ?? '';
      $rowsData[] = array(
          'id' => $prof['.id'] ?? '',
          'name' => $pname,
          'laddr' => $prof['local-address'] ?? '-',
          'raddr' => $prof['remote-address'] ?? '-',
          'rate' => $prof['rate-limit'] ?? '-',
          'only' => $prof['only-one'] ?? 'default',
          'dns' => $prof['dns-server'] ?? '-',
          'comm' => $prof['comment'] ?? '',
          'def' => ($pname === 'default' || $pname === 'default-encryption') ? 1 : 0,
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
var PPP_PROF_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var PPP_SESSION = <?= json_encode($session); ?>;
initBulkUI('ppp-profile', { virtual: true });
initVirtualTableView({
  data: PPP_PROF_DATA,
  defaultPerPage: 100,
  searchCols: ['name', 'laddr', 'raddr', 'rate', 'only', 'dns', 'comm'],
  renderRow: function(p, checked) {
    var id = tvEsc(p.id), name = tvEsc(p.name);
    var sessEnc = encodeURIComponent(PPP_SESSION);
    var idEnc = encodeURIComponent(p.id);
    var nameEnc = encodeURIComponent(p.name);
    var chk = p.def ? '' : '<input type="checkbox" class="row-check" data-id="' + id + '" data-name="' + name + '"' + (checked ? ' checked' : '') + '>';
    var delBtn = p.def
      ? '<i class="fa fa-minus-square text-muted" style="opacity:0.3;" title="Default Profile"></i>'
      : '<i class="fa fa-minus-square text-danger pointer" onclick="if(confirm(\'Hapus profile (' + name + ')?\')){loadpage(\'./?remove-pprofile=' + idEnc + '&session=' + sessEnc + '\');loader();}" title="Hapus ' + name + '"></i>';
    return '<tr class="row-data">' +
      '<td style="text-align:center;">' + chk + '</td>' +
      '<td style="text-align:center;">' + delBtn + '</td>' +
      '<td><a title="Open Profile ' + name + '" href="./?ppp=edit-profile&name=' + nameEnc + '&session=' + sessEnc + '"><i class="fa fa-edit"></i> ' + name + '</a></td>' +
      '<td>' + tvEsc(p.laddr) + '</td>' +
      '<td>' + tvEsc(p.raddr) + '</td>' +
      '<td>' + tvEsc(p.rate) + '</td>' +
      '<td>' + tvEsc(p.only) + '</td>' +
      '<td>' + tvEsc(p.dns) + '</td>' +
      '<td>' + tvEsc(p.comm) + '</td>' +
      '</tr>';
  }
});
</script>
