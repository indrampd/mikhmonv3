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

<link rel="stylesheet" href="./css/ppp-billing.css?v=3">

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
  <div class="kpi-bar">
    <a class="kpi-pill <?= $filter_tagihan === 'all' ? 'bg-primary' : ''; ?>" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=all&session=<?= $session; ?>">
      <i class="fa fa-users"></i> Total: <strong><?= $TotalRegAll; ?></strong>
    </a>
    <a class="kpi-pill <?= $filter_tagihan === 'lunas' ? 'bg-success' : ''; ?>" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=lunas&session=<?= $session; ?>">
      <i class="fa fa-check-circle text-green"></i> Lunas: <strong><?= $counts_billing['lunas']; ?></strong>
    </a>
    <a class="kpi-pill <?= $filter_tagihan === 'tenggang' ? 'bg-warning' : ''; ?>" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=tenggang&session=<?= $session; ?>">
      <i class="fa fa-clock-o text-yellow"></i> Tenggang: <strong><?= $counts_billing['tenggang']; ?></strong>
    </a>
    <a class="kpi-pill <?= $filter_tagihan === 'isolir' ? 'bg-danger' : ''; ?>" href="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=isolir&session=<?= $session; ?>">
      <i class="fa fa-ban text-red"></i> Isolir: <strong><?= $counts_billing['isolir']; ?></strong>
    </a>
    <span class="kpi-pill is-static">
      <span class="status-dot bg-success"></span> Online: <strong><?= $TotalActive; ?></strong>
    </span>
  </div>

  <!-- Search & Filter Controls -->
  <div class="row mr-b-15">
    <div class="col-12">
      <div class="input-group">
        <div class="input-group-4 col-box-4">
          <input id="filterTable" type="text" style="padding:6px 10px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari nama, IP, komentar..." ?>">
        </div>
        <div class="input-group-4 col-box-4">
          <select style="padding:6px 8px;" class="group-item" onchange="location = this.value; loader()" title="Filter by Profile">
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
          <select style="padding:6px 8px;" class="group-item group-item-r" onchange="location = this.value; loader()" title="Filter by Status Tagihan">
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=all&session=<?= $session; ?>" <?= $filter_tagihan === 'all' ? 'selected' : ''; ?>>Semua Status Tagihan</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=lunas&session=<?= $session; ?>" <?= $filter_tagihan === 'lunas' ? 'selected' : ''; ?>>Lunas / Aktif</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=tenggang&session=<?= $session; ?>" <?= $filter_tagihan === 'tenggang' ? 'selected' : ''; ?>>Masa Tenggang</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=isolir&session=<?= $session; ?>" <?= $filter_tagihan === 'isolir' ? 'selected' : ''; ?>>Terisolir</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div id="liveAlertBox" style="display:none; margin-bottom:12px;" class="alert bg-info pd-10 radius-3">
    <i class="fa fa-bolt"></i> <span id="liveAlertText">Live event</span>
  </div>

  <!-- Breathable Table Structure -->
  <div class="overflow box-bordered" style="max-height: 72vh; border-radius: 4px;">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap secret-table">
    <thead>
    <tr>
      <th style="width:50px; text-align:center;" id="cuser"><?= $TotalReg; ?></th>
      <th class="pointer" title="Click to sort" style="min-width:190px;"><i class="fa fa-sort"></i> Akun</th>
      <th class="pointer" title="Click to sort" style="min-width:150px;"><i class="fa fa-sort"></i> Pelanggan</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Paket / Profile</th>
      <th class="pointer text-center" title="Click to sort" style="width:105px;"><i class="fa fa-sort"></i> Koneksi</th>
      <th class="pointer text-center" title="Click to sort" style="width:125px;"><i class="fa fa-sort"></i> Tagihan</th>
      <th class="text-center" style="width:165px;">Aksi Cepat</th>
    </tr>
    </thead>
    <tbody id="tbody">
    <?php
    if ($TotalReg === 0) {
        echo "<tr><td colspan='7' class='text-center pd-15 text-muted'><i class='fa fa-info-circle'></i> Tidak ada data secret PPPoE ditemukan.</td></tr>";
    }

    for ($i = 0; $i < $TotalReg; $i++) {
        $userdetails = $filtered_users[$i];
        $uid = $userdetails['.id'];
        $uname = $userdetails['name'];
        $upass = $userdetails['password'] ?? '';
        $uprofile = $userdetails['profile'] ?? 'default';
        $uservice = $userdetails['service'] ?? 'pppoe';
        $uremote = $userdetails['remote-address'] ?? '-';
        $ucomment = $userdetails['comment'] ?? '';
        $udisabled = ($userdetails['disabled'] === 'true');
        $is_online = isset($active_map[$uname]);

        // Tagihan parsing
        $stat = MikhmonBilling::getStatus($uprofile, $ucomment);
        $parsed = MikhmonBilling::parseComment($ucomment);
        $is_isolir = ($stat['code'] === 'isolir' || $uprofile === 'pppoe-ISOLIR');

        echo "<tr id='row-user-" . htmlspecialchars($uname) . "' data-user='" . htmlspecialchars($uname) . "'>";
        
        // Col 1: Action (Lock / Delete)
        echo "<td style='text-align:center;'>";
        echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Yakin ingin menghapus user (" . addslashes($uname) . ")?')){loadpage('./?remove-pppsecret=" . urlencode($uid) . "&session=" . $session . "');loader();}else{}\" title='Hapus " . htmlspecialchars($uname) . "'></i>&nbsp;&nbsp;";
        if ($udisabled) {
            $uriprocess = "'./?enable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="text-warning pointer" title="Aktifkan Akun" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-lock"></i></span>';
        } else {
            $uriprocess = "'./?disable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="pointer text-green" title="Nonaktifkan Akun" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-unlock"></i></span>';
        }
        echo "</td>";

        // Col 2: Akun (Username, Password, Remote IP)
        echo "<td>";
        echo "<a title='Edit User " . htmlspecialchars($uname) . "' href='./?secret=" . urlencode($uname) . "&session=" . $session . "' style='font-size:13.5px; font-weight:600;'>";
        echo "<i class='fa fa-user'></i> " . htmlspecialchars($uname) . "</a>";
        
        echo "<span class='user-subinfo'>";
        if ($upass !== '') {
            echo "<code style='font-size:10.5px; padding:1px 4px;'>" . htmlspecialchars($upass) . "</code>";
            echo " &nbsp;•&nbsp; ";
        }
        if (!empty($uremote) && $uremote !== '-') {
            echo "<i class='fa fa-desktop'></i> " . htmlspecialchars($uremote);
        } else {
            echo "<i class='fa fa-server'></i> " . htmlspecialchars($uservice);
        }
        echo "</span>";
        echo "</td>";

        // Col 3: Pelanggan (Label / Comment)
        echo "<td>";
        if (!empty($parsed['label'])) {
            echo "<span style='font-size:13px;'><i class='fa fa-id-card text-muted'></i> " . htmlspecialchars($parsed['label']) . "</span>";
        } else {
            echo "<span class='text-muted' style='font-size:12px; opacity:0.55;'>&mdash;</span>";
        }
        echo "</td>";

        // Col 3: Paket / Profile & Tarif
        echo "<td>";
        echo "<a id='prof-label-" . htmlspecialchars($uname) . "' href='./?ppp=secrets&profile=" . urlencode($uprofile) . "&session=" . $session . "' style='font-weight:500;'>";
        echo htmlspecialchars($uprofile);
        echo "</a>";
        if (!empty($parsed['pkg'])) {
            echo "<span class='user-subinfo'><i class='fa fa-cube'></i> " . htmlspecialchars($parsed['pkg']) . "</span>";
        }
        echo "</td>";

        // Col 4: Status Koneksi
        echo "<td class='text-center'>";
        if ($is_online) {
            echo "<span class='badge bg-success pd-5 radius-3' style='font-size:11px;'><i class='fa fa-circle'></i> Online</span>";
        } else {
            echo "<span class='badge bg-secondary pd-5 radius-3' style='font-size:11px; opacity:0.65;'><i class='fa fa-circle-o'></i> Offline</span>";
        }
        echo "</td>";

        // Col 5: Status Tagihan Badge
        echo "<td class='text-center' id='badge-tagihan-" . htmlspecialchars($uname) . "'>";
        echo "<span class='badge bg-" . $stat['badge'] . " pd-5 radius-3' style='font-size:11px;' title='" . htmlspecialchars($stat['hint']) . "'>";
        echo "<i class='fa " . $stat['icon'] . "'></i> " . htmlspecialchars($stat['label']);
        echo "</span>";
        if (!empty($parsed['lunas'])) {
            echo "<small style='display:block; font-size:10px; margin-top:2px; opacity:0.75;'>Bln: " . htmlspecialchars($parsed['lunas']) . "</small>";
        }
        echo "</td>";

        // Col 6: Aksi Cepat
        echo "<td class='text-center'>";
        echo "<div style='display:inline-flex; gap:4px; align-items:center;'>";
        // Button Lunas
        echo "<button type='button' class='btn-action-sm bg-success pointer' title='Tandai Lunas Bulan Ini' onclick=\"quickBillingAction('lunas', '" . addslashes($uname) . "')\"><i class='fa fa-check'></i> Lunas</button>";
        // Button Isolir / Pulih
        if ($is_isolir) {
            echo "<button type='button' class='btn-action-sm bg-warning pointer' title='Pulihkan Paket Asal' onclick=\"quickBillingAction('pulih', '" . addslashes($uname) . "')\"><i class='fa fa-refresh'></i> Pulih</button>";
        } else {
            echo "<button type='button' class='btn-action-sm bg-danger pointer' title='Isolir Pelanggan' onclick=\"quickBillingAction('isolir', '" . addslashes($uname) . "')\"><i class='fa fa-ban'></i> Isolir</button>";
        }
        // Button Edit
        echo "<a href='./?secret=" . urlencode($uname) . "&session=" . $session . "' class='btn-action-sm box-bordered text-muted pointer' title='Edit Secret'><i class='fa fa-pencil'></i></a>";
        echo "</div>";
        echo "</td>";

        echo "</tr>";
    }
    ?>
    </tbody>
  </table>
  </div>
</div>
</div>
</div>
</div>

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

// Server-Sent Events (SSE) Live Updates
(function initSSE() {
    if (!window.EventSource) return;
    try {
        var evtSource = new EventSource('./billing/events.php?session=<?= urlencode($session); ?>');
        evtSource.addEventListener('billing', function(e) {
            try {
                var d = JSON.parse(e.data);
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
