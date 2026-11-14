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
          <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari user, IP, komentar..." ?>">
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

  <div id="liveAlertBox" style="display:none; margin:0 0 12px; padding:9px 12px; border:1px solid #1EA4E1; border-left:3px solid #20a8d8; border-radius:4px; background:#3a4149; color:#e4e7ea; box-sizing:border-box; max-width:100%; overflow-wrap:anywhere;">
    <i class="fa fa-bolt"></i> <span id="liveAlertText">Live event</span>
  </div>

  <!-- Breathable Table Structure -->
  <div class="overflow box-bordered" style="max-height: 72vh; border-radius: 4px;">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
    <tr>
      <th style="width:50px; text-align:center;" id="cuser"><?= $TotalReg; ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Username</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Profile</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Remote IP</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Comment</th>
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
        
        // Col 1: Action (Lock / Delete) - Same style as Hotspot Users
        echo "<td style='text-align:center;'>";
        echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Yakin ingin menghapus user (' + " . htmlspecialchars(json_encode($uname), ENT_QUOTES, 'UTF-8') . " + ')?')){loadpage('./?remove-pppsecret=" . urlencode($uid) . "&session=" . $session . "');loader();}\" title='Hapus " . htmlspecialchars($uname) . "'></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        if ($udisabled) {
            $uriprocess = "'./?enable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="text-warning pointer" title="Enable User ' . htmlspecialchars($uname) . '" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-lock"></i></span></td>';
        } else {
            $uriprocess = "'./?disable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="pointer" title="Disable User ' . htmlspecialchars($uname) . '" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-unlock"></i></span></td>';
        }

        // Status dot color for connection: green if online, muted if offline
        $dot_color = $is_online ? "text-green" : "text-muted";
        $dot_title = $is_online ? "Online" : "Offline";

        // Col 2: Username (Hotspot Style: <i class='fa fa-edit'></i> <i class='fa fa-circle ...'></i> Username)
        echo "<td>";
        echo "<a title='Open User " . htmlspecialchars($uname) . " (" . $dot_title . ")' href='./?secret=" . urlencode($uname) . "&session=" . $session . "'>";
        echo "<i class='fa fa-edit'></i> <i class='fa fa-circle " . $dot_color . "' title='" . $dot_title . "'></i> " . htmlspecialchars($uname);
        echo "</a>";
        echo "</td>";

        // Col 3: Profile (with origin package note if currently isolated)
        echo "<td>";
        echo "<a id='prof-label-" . htmlspecialchars($uname) . "' href='./?ppp=secrets&profile=" . urlencode($uprofile) . "&session=" . $session . "'>";
        echo htmlspecialchars($uprofile);
        echo "</a>";
        if ($is_isolir && !empty($parsed['pkg']) && $parsed['pkg'] !== $uprofile) {
            echo " <small class='text-muted' title='Paket Asli'>(" . htmlspecialchars($parsed['pkg']) . ")</small>";
        }
        echo "</td>";

        // Col 4: Remote IP / Service
        echo "<td>";
        if (!empty($uremote) && $uremote !== '-') {
            echo htmlspecialchars($uremote);
        } else {
            echo "<span class='text-muted'>&mdash;</span>";
        }
        echo "</td>";

        // Col 5: Comment / Pelanggan (Same as Hotspot Comment)
        echo "<td>";
        if (!empty($parsed['label'])) {
            echo htmlspecialchars($parsed['label']);
        } else {
            echo "<span class='text-muted'>&mdash;</span>";
        }
        echo "</td>";

        // Col 6: Status Tagihan (1 row inline: dot status + label + dot + Tahun-Bulan)
        $tagihan_dot = ($stat['code'] === 'lunas') ? 'text-green' : (($stat['code'] === 'tenggang') ? 'text-yellow' : 'text-danger');
        echo "<td class='text-center' id='badge-tagihan-" . htmlspecialchars($uname) . "'>";
        echo "<i class='fa fa-circle " . $tagihan_dot . "' style='font-size:10px;' title='" . htmlspecialchars($stat['hint']) . "'></i> ";
        echo "<strong>" . htmlspecialchars($stat['label']) . "</strong>";
        if (!empty($parsed['lunas'])) {
            echo " <span class='text-muted' style='font-size:11.5px;'>• " . htmlspecialchars($parsed['lunas']) . "</span>";
        }
        echo "</td>";

        // Col 7: Aksi Cepat (2 tombol aksi saja: Lunas + Isolir/Pulih)
        echo "<td class='text-center'>";
        echo "<div style='display:inline-flex; gap:4px; align-items:center;'>";
        // Button Lunas
        echo "<button type='button' class='btn bg-success radius-3 pd-2 pointer' title='Tandai Lunas Bulan Ini' onclick=\"quickBillingAction('lunas', " . htmlspecialchars(json_encode($uname), ENT_QUOTES, 'UTF-8') . ")\"><i class='fa fa-check'></i> Lunas</button>";
        // Button Isolir / Pulih
        if ($is_isolir) {
            echo "<button type='button' class='btn bg-warning radius-3 pd-2 pointer' title='Pulihkan Paket Asal' onclick=\"quickBillingAction('pulih', " . htmlspecialchars(json_encode($uname), ENT_QUOTES, 'UTF-8') . ")\"><i class='fa fa-refresh'></i> Pulih</button>";
        } else {
            echo "<button type='button' class='btn bg-danger radius-3 pd-2 pointer' title='Isolir Pelanggan' onclick=\"quickBillingAction('isolir', " . htmlspecialchars(json_encode($uname), ENT_QUOTES, 'UTF-8') . ")\"><i class='fa fa-ban'></i> Isolir</button>";
        }
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
