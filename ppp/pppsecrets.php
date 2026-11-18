<?php
/*
 * PPPoE / PPP Secrets Management with Integrated Tagihan & Live SSE for Mikhmon v3
 * Harmonious, spacious, and responsive Mikhmon design
 */
session_start();
error_reporting(0);
ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

require_once dirname(__DIR__) . '/lib/billing.class.php';

$session = $_GET['session'] ?? '';
$prof = $_GET['profile'] ?? 'all';
$filter_tagihan = $_GET['tagihan'] ?? 'all';

// Load profiles
$getprofile = $API->comm("/ppp/profile/print");
$TotalReg2 = count($getprofile);

// Load all secrets count
$countall = $API->comm("/ppp/secret/print", array("count-only" => ""));
$TotalRegAll = is_numeric($countall) ? (int)$countall : 0;

// Load secrets with filter
if ($prof == "all" || empty($prof)) {
    $getuser = $API->comm("/ppp/secret/print");
} else {
    $getuser = $API->comm("/ppp/secret/print", array("?profile" => "$prof"));
}

// Active connections for online status mapping
$getactive = $API->comm("/ppp/active/print");
$TotalActive = count($getactive);
$active_map = array();
if (is_array($getactive)) {
    foreach ($getactive as $act) {
        if (!empty($act['name'])) {
            $active_map[$act['name']] = $act;
        }
    }
}

// Compute billing counts
$counts_billing = array('lunas' => 0, 'tenggang' => 0, 'isolir' => 0, 'none' => 0);
$filtered_users = array();

if (is_array($getuser)) {
    foreach ($getuser as $u) {
        $st = MikhmonBilling::getStatus($u['profile'] ?? '', $u['comment'] ?? '');
        $code = $st['code'];
        if (isset($counts_billing[$code])) {
            $counts_billing[$code]++;
        }
        if ($filter_tagihan === 'all' || $filter_tagihan === $code) {
            $filtered_users[] = $u;
        }
    }
}
$TotalReg = count($filtered_users);
?>


<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-users"></i> PPPoE Secrets
      <span style="font-size: 13px;">
        &nbsp; | &nbsp; <a href="./?ppp=addsecret&session=<?= $session; ?>" title="Tambah Secret"><i class="fa fa-user-plus"></i> <?= $_add ?? "Tambah" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Active Connections"><i class="fa fa-exchange"></i> Active (<?= $TotalActive; ?>)</a>
        &nbsp; | &nbsp; <a href="./?ppp=profiles&session=<?= $session; ?>" title="Profiles"><i class="fa fa-pie-chart"></i> Profiles (<?= $TotalReg2; ?>)</a>
        &nbsp; | &nbsp; <a href="./?billing=overview&session=<?= $session; ?>" title="Bot Telegram & Tagihan"><i class="fa fa-telegram"></i> Bot Telegram</a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?> </i></small>
    </h3>
</div>

<div class="card-body">
  <!-- Top KPI & Quick Filter Bar -->
  <div class="pd-t-5 pd-b-5">
    <a class="btn <?= $filter_tagihan === 'all' ? 'bg-primary' : 'bg-secondary'; ?> radius-3" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=all&session=<?= $session; ?>">
      <i class="fa fa-users"></i> Total: <strong><?= $TotalRegAll; ?></strong>
    </a>
    <a class="btn <?= $filter_tagihan === 'lunas' ? 'bg-success' : 'bg-secondary'; ?> radius-3" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=lunas&session=<?= $session; ?>">
      <i class="fa fa-check-circle text-green"></i> Lunas: <strong><?= $counts_billing['lunas']; ?></strong>
    </a>
    <a class="btn <?= $filter_tagihan === 'tenggang' ? 'bg-warning' : 'bg-secondary'; ?> radius-3" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=tenggang&session=<?= $session; ?>">
      <i class="fa fa-clock-o text-yellow"></i> Tenggang: <strong><?= $counts_billing['tenggang']; ?></strong>
    </a>
    <a class="btn <?= $filter_tagihan === 'isolir' ? 'bg-danger' : 'bg-secondary'; ?> radius-3" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=isolir&session=<?= $session; ?>">
      <i class="fa fa-ban text-red"></i> Isolir: <strong><?= $counts_billing['isolir']; ?></strong>
    </a>
    <span class="btn bg-secondary radius-3">
      <i class="fa fa-circle text-green"></i> Online: <strong><?= $TotalActive; ?></strong>
    </span>
  </div>

  <!-- Search & Filter Controls (Hotspot Mikhmon Style) -->
  <div class="row mr-b-10">
    <div class="col-6 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-4 col-box-4">
          <input id="tableSearch" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari user, IP, komentar..." ?>">
        </div>
        <div class="input-group-4 col-box-4">
          <select style="padding:5px;" class="group-item group-item-m" onchange="location = this.value; loader()" title="Filter by Profile">
            <option value="./?ppp=secrets&profile=all&tagihan=<?= $filter_tagihan; ?>&session=<?= $session; ?>"><?= $_show_all ?? "Semua Profile" ?> (<?= $TotalRegAll; ?>)</option>
            <?php
            for ($i = 0; $i < $TotalReg2; $i++) {
                $pname = $getprofile[$i]['name'];
                $selected = ($prof === $pname) ? 'selected' : '';
                echo "<option value='./?ppp=secrets&profile=" . urlencode($pname) . "&tagihan=" . $filter_tagihan . "&session=" . $session . "' {$selected}>" . htmlspecialchars($pname) . "</option>";
            }
            ?>
          </select>
        </div>
        <div class="input-group-4 col-box-4">
          <select style="padding:5px;" class="group-item group-item-r" onchange="location = this.value; loader()" title="Filter by Status Tagihan">
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=all&session=<?= $session; ?>" <?= $filter_tagihan === 'all' ? 'selected' : ''; ?>>Semua Status Tagihan</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=lunas&session=<?= $session; ?>" <?= $filter_tagihan === 'lunas' ? 'selected' : ''; ?>>Lunas / Aktif</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=tenggang&session=<?= $session; ?>" <?= $filter_tagihan === 'tenggang' ? 'selected' : ''; ?>>Masa Tenggang</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=isolir&session=<?= $session; ?>" <?= $filter_tagihan === 'isolir' ? 'selected' : ''; ?>>Terisolir</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Bulk bar: muncul saat ada checkbox terpilih -->
  <div id="bulkBar" style="display:none; margin:0 0 10px; padding:8px 10px; border:1px dashed rgba(128,128,128,.35); border-radius:4px; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px;">
    <strong id="bulkCount">0</strong>&nbsp;terpilih:
    <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="bulkAct('enable')"><i class="fa fa-unlock"></i> Enable</button>
    <button type="button" class="btn bg-secondary radius-3 pd-5" onclick="bulkAct('disable')"><i class="fa fa-lock"></i> Disable</button>
    <button type="button" class="btn bg-danger radius-3 pd-5" onclick="bulkAct('remove')"><i class="fa fa-trash"></i> Hapus</button>
    <button type="button" class="btn bg-success radius-3 pd-5" onclick="bulkAct('lunas')"><i class="fa fa-check"></i> Tandai Lunas</button>
  </div>

  <div id="liveAlertBox" style="display:none; margin:0 0 12px; padding:9px 12px; border:1px solid #1EA4E1; border-left:3px solid #20a8d8; border-radius:4px; background:#3a4149; color:#e4e7ea; box-sizing:border-box; max-width:100%; overflow-wrap:anywhere;">
    <i class="fa fa-bolt"></i> <span id="liveAlertText">Live event</span>
  </div>

  <!-- Breathable Table Structure -->
  <div class="overflow box-bordered" style="max-height: 72vh; border-radius: 4px;">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
    <tr>
      <th style="width:30px; text-align:center;" title="Pilih semua di halaman ini"><input type="checkbox" onclick="toggleAllChecks(this)"></th>
      <th style="width:70px; text-align:center;">Aksi</th>
      <th class="pointer" data-sort="name" title="Click to sort"><i class="fa fa-sort"></i> Nama</th>
      <th class="pointer" data-sort="srv" title="Click to sort"><i class="fa fa-sort"></i> Layanan</th>
      <th class="pointer" data-sort="caller" title="Click to sort"><i class="fa fa-sort"></i> Caller ID</th>
      <th class="pointer" data-sort="prof" title="Click to sort"><i class="fa fa-sort"></i> Profil</th>
      <th class="pointer" data-sort="ip" title="Click to sort"><i class="fa fa-sort"></i> IP</th>
      <th class="pointer" data-sort="lastout" title="Click to sort"><i class="fa fa-sort"></i> Terakhir Keluar</th>
      <th class="pointer" data-sort="label" title="Click to sort"><i class="fa fa-sort"></i> Comment</th>
      <th class="pointer text-center" data-sort="slabel" title="Click to sort" style="width:125px;"><i class="fa fa-sort"></i> Tagihan</th>
      <th class="text-center" style="width:165px;">Aksi Cepat</th>
    </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
  </div>
  <?php
  $rowsData = array();
  for ($i = 0; $i < $TotalReg; $i++) {
      $userdetails = $filtered_users[$i];
      $uname = $userdetails['name'] ?? '';
      $uprofile = $userdetails['profile'] ?? 'default';
      $ucomment = $userdetails['comment'] ?? '';
      $is_online = isset($active_map[$uname]);
      $uip = ($is_online && !empty($active_map[$uname]['address'])) ? $active_map[$uname]['address'] : ($userdetails['remote-address'] ?? '');
      $stat = MikhmonBilling::getStatus($uprofile, $ucomment);
      $parsed = MikhmonBilling::parseComment($ucomment);
      $is_isolir = ($stat['code'] === 'isolir' || $uprofile === 'pppoe-ISOLIR');
      $rowsData[] = array(
          'id' => $userdetails['.id'] ?? '',
          'name' => $uname,
          'srv' => $userdetails['service'] ?? 'pppoe',
          'caller' => $userdetails['caller-id'] ?? '',
          'prof' => $uprofile,
          'orig' => ($is_isolir && !empty($parsed['pkg']) && $parsed['pkg'] !== $uprofile) ? $parsed['pkg'] : '',
          'ip' => $uip,
          'lastout' => $userdetails['last-logged-out'] ?? '',
          'label' => $parsed['label'] ?? '',
          'dis' => (($userdetails['disabled'] ?? '') === 'true') ? 1 : 0,
          'online' => $is_online ? 1 : 0,
          'isolir' => $is_isolir ? 1 : 0,
          'scode' => $stat['code'],
          'slabel' => $stat['label'],
          'shint' => $stat['hint'],
          'lunas' => $parsed['lunas'] ?? '',
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
// AJAX Action for Lunas / Isolir / Pulih
function quickBillingAction(action, username) {
    var confirmMsg = (action === 'lunas') ? 'Tandai user (' + username + ') LUNAS bulan ini & pulihkan paket?' :
                     (action === 'isolir') ? 'Yakin ingin meng-ISOLIR user (' + username + ')?' :
                     'Pulihkan paket asal user (' + username + ')?';

    if (!confirm(confirmMsg)) return;

    loader();
    var formData = new FormData();
    formData.append('session', '<?= $session; ?>');
    formData.append('action', action);
    formData.append('target', username);

    fetch('./billing/action.php', {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('✅ ' + data.message);
            setTimeout(function() { location.reload(); }, 600);
        } else {
            alert('❌ ' + (data.message || 'Gagal memproses aksi'));
            if (document.getElementById('loader')) document.getElementById('loader').style.display = 'none';
        }
    })
    .catch(function(err) {
        alert('Terjadi kesalahan koneksi.');
        if (document.getElementById('loader')) document.getElementById('loader').style.display = 'none';
    });
}

function showToast(msg) {
    var box = document.getElementById('liveAlertBox');
    var txt = document.getElementById('liveAlertText');
    if (box && txt) {
        txt.innerHTML = msg;
        box.style.display = 'block';
        setTimeout(function() {
            box.style.display = 'none';
        }, 5000);
    }
}

// Bulk (checkbox) + Filter + pagination virtual (hanya render halaman aktif)
var PPP_SECRETS_DATA = <?= json_encode($rowsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var PPP_SESSION = <?= json_encode($session); ?>;
initBulkUI('ppp', { virtual: true });
initVirtualTableView({
  data: PPP_SECRETS_DATA,
  defaultPerPage: 100,
  searchCols: ['name', 'srv', 'caller', 'prof', 'orig', 'ip', 'label', 'slabel', 'lunas'],
  renderRow: function(u, checked) {
    var uid = tvEsc(u.id), uname = tvEsc(u.name), prof = tvEsc(u.prof);
    var sessEnc = encodeURIComponent(PPP_SESSION);
    var uEnc = encodeURIComponent(u.name);
    var idEnc = encodeURIComponent(u.id);
    var toggleBtn = u.dis
      ? '<span class="text-warning pointer" title="Enable User ' + uname + '" onclick="loadpage(\'./?enable-pppsecret=' + idEnc + '&session=' + sessEnc + '\');loader();"><i class="fa fa-lock"></i></span>'
      : '<span class="pointer" title="Disable User ' + uname + '" onclick="loadpage(\'./?disable-pppsecret=' + idEnc + '&session=' + sessEnc + '\');loader();"><i class="fa fa-unlock"></i></span>';
    var onlineBadge = u.online ? ' <span class="badge bg-success radius-3" style="font-size:11px; margin-left:6px;"><i class="fa fa-circle" style="font-size:9px;"></i> Online</span>' : '';
    var origHtml = u.orig ? ' <small class="text-muted" title="Paket Asli">(' + tvEsc(u.orig) + ')</small>' : '';
    var dash = '<span class="text-muted">&mdash;</span>';
    var dotCls = u.scode === 'lunas' ? 'text-green' : (u.scode === 'tenggang' ? 'text-yellow' : 'text-danger');
    var lunasHtml = u.lunas ? ' <span class="text-muted" style="font-size:11.5px;">&bull; ' + tvEsc(u.lunas) + '</span>' : '';
    var jsUser = tvEsc(JSON.stringify(u.name));
    var isoBtn = u.isolir
      ? '<button type="button" class="btn bg-warning radius-3 pd-2 pointer" title="Pulihkan Paket Asal" onclick="quickBillingAction(\'pulih\', ' + jsUser + ')"><i class="fa fa-refresh"></i> Pulih</button>'
      : '<button type="button" class="btn bg-danger radius-3 pd-2 pointer" title="Isolir Pelanggan" onclick="quickBillingAction(\'isolir\', ' + jsUser + ')"><i class="fa fa-ban"></i> Isolir</button>';
    return '<tr class="row-data" id="row-user-' + uname + '" data-user="' + uname + '">' +
      '<td style="text-align:center;"><input type="checkbox" class="row-check" data-id="' + uid + '" data-name="' + uname + '"' + (checked ? ' checked' : '') + '></td>' +
      '<td style="text-align:center;"><i class="fa fa-minus-square text-danger pointer" onclick="if(confirm(\'Yakin ingin menghapus user (' + uname + ')?\')){loadpage(\'./?remove-pppsecret=' + idEnc + '&session=' + sessEnc + '\');loader();}" title="Hapus ' + uname + '"></i>&nbsp;&nbsp;&nbsp;&nbsp;' + toggleBtn + '</td>' +
      '<td><a title="Open User ' + uname + '" href="./?secret=' + uEnc + '&session=' + sessEnc + '"><i class="fa fa-edit"></i> ' + uname + '</a>' + onlineBadge + '</td>' +
      '<td>' + tvEsc(u.srv) + '</td>' +
      '<td>' + (u.caller ? tvEsc(u.caller) : dash) + '</td>' +
      '<td><a id="prof-label-' + uname + '" href="./?ppp=secrets&profile=' + encodeURIComponent(u.prof) + '&session=' + sessEnc + '">' + prof + '</a>' + origHtml + '</td>' +
      '<td>' + (u.ip ? tvEsc(u.ip) : dash) + '</td>' +
      '<td>' + (u.lastout ? '<small class="text-muted">' + tvEsc(u.lastout) + '</small>' : dash) + '</td>' +
      '<td>' + (u.label ? tvEsc(u.label) : dash) + '</td>' +
      '<td class="text-center" id="badge-tagihan-' + uname + '"><i class="fa fa-circle ' + dotCls + '" style="font-size:10px;" title="' + tvEsc(u.shint) + '"></i> ' + tvEsc(u.slabel) + lunasHtml + '</td>' +
      '<td class="text-center"><div style="display:inline-flex; gap:4px; align-items:center;"><button type="button" class="btn bg-success radius-3 pd-2 pointer" title="Tandai Lunas Bulan Ini" onclick="quickBillingAction(\'lunas\', ' + jsUser + ')"><i class="fa fa-check"></i> Lunas</button>' + isoBtn + '</div></td>' +
      '</tr>';
  }
});

// Server-Sent Events (SSE) Live Updates
(function initSSE() {
    if (!window.EventSource) return;
    // Only events newer than this page load are "live". Without last_ts the
    // stream replays the most recent stored event on every connect, so the
    // banner fired on every visit with a stale update.
    var pageOpenedAt = Date.now() / 1000;
    var lastTs = pageOpenedAt;
    try {
        var evtSource = new EventSource('./billing/events.php?session=<?= urlencode($session); ?>&last_ts=' + lastTs.toFixed(3));
        evtSource.addEventListener('billing', function(e) {
            try {
                var d = JSON.parse(e.data);
                if (!d || !d.target) return;
                // Ignore anything that happened before this page was opened.
                if (d.ts && Number(d.ts) < lastTs) return;
                if (d.ts) lastTs = Number(d.ts);
                if (d && d.target) {
                    showToast('⚡ Live Update: <b>' + d.target + '</b> baru saja di-<b>' + d.action.toUpperCase() + '</b> (' + (d.detail || '') + ')');
                    var targetRow = document.getElementById('row-user-' + d.target);
                    if (targetRow) {
                        targetRow.style.backgroundColor = 'rgba(40, 167, 69, 0.25)';
                        setTimeout(function() { targetRow.style.backgroundColor = ''; }, 3000);
                    }
                }
            } catch(ex) {}
        });
        evtSource.onerror = function() {
            // Reconnects automatically
        };
    } catch (err) {}
})();
</script>
