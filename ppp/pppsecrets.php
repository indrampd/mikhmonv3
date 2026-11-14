<?php
/*
 * PPPoE / PPP Secrets Management with Integrated Tagihan & Live SSE for Mikhmon v3
 * Styled 1:1 with Mikhmon Hotspot Users UI
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
    <h3><i class="fa fa-users"></i> <?= $_ppp_secrets ?? "PPPoE Secrets & Tagihan" ?>
      <span style="font-size: 14px">
        &nbsp; | &nbsp; <a href="./?ppp=addsecret&session=<?= $session; ?>" title="Tambah Secret"><i class="fa fa-user-plus"></i> <?= $_add ?? "Tambah" ?></a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Active Connections"><i class="fa fa-exchange"></i> <?= $_ppp_active ?? "PPP Active" ?> (<?= $TotalActive; ?>)</a>
        &nbsp; | &nbsp; <a href="./?ppp=profiles&session=<?= $session; ?>" title="Profiles"><i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?? "Profiles" ?> (<?= $TotalReg2; ?>)</a>
        &nbsp; | &nbsp; <a href="./?billing=overview&session=<?= $session; ?>" title="Bot Telegram & Billing"><i class="fa fa-telegram"></i> Bot Telegram</a>
      </span>
      <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?? "Processing..." ?> </i></small>
    </h3>
</div>

<div class="card-body">
  <!-- Toolbar Filters & Summary -->
  <div class="row">
    <div class="col-7 pd-t-5 pd-b-5">
      <div class="input-group">
        <div class="input-group-4 col-box-4">
          <input id="filterTable" type="text" style="padding:5.8px;" class="group-item group-item-l" placeholder="<?= $_search ?? "Cari nama, IP, comment..." ?>">
        </div>
        <div class="input-group-4 col-box-4">
          <select style="padding:5px;" class="group-item" onchange="location = this.value; loader()" title="Filter by Profile">
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
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=all&session=<?= $session; ?>" <?= $filter_tagihan === 'all' ? 'selected' : ''; ?>>Semua Tagihan (<?= $TotalRegAll; ?>)</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=lunas&session=<?= $session; ?>" <?= $filter_tagihan === 'lunas' ? 'selected' : ''; ?>>✅ Lunas/Aktif (<?= $counts_billing['lunas']; ?>)</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=tenggang&session=<?= $session; ?>" <?= $filter_tagihan === 'tenggang' ? 'selected' : ''; ?>>🟡 Tenggang (<?= $counts_billing['tenggang']; ?>)</option>
            <option value="./?ppp=secrets&profile=<?= urlencode($prof); ?>&tagihan=isolir&session=<?= $session; ?>" <?= $filter_tagihan === 'isolir' ? 'selected' : ''; ?>>🔴 Isolir (<?= $counts_billing['isolir']; ?>)</option>
          </select>
        </div>
      </div>
    </div>
    <div class="col-5 text-right pd-t-5 pd-b-5">
      <span class="badge bg-success pd-5 radius-3 pointer" onclick="location='./?ppp=secrets&tagihan=lunas&session=<?= $session; ?>'" title="Tagihan Lunas"><i class="fa fa-check"></i> Lunas: <?= $counts_billing['lunas']; ?></span>
      <span class="badge bg-warning pd-5 radius-3 mr-l-5 pointer" onclick="location='./?ppp=secrets&tagihan=tenggang&session=<?= $session; ?>'" title="Masa Tenggang"><i class="fa fa-clock-o"></i> Tenggang: <?= $counts_billing['tenggang']; ?></span>
      <span class="badge bg-danger pd-5 radius-3 mr-l-5 pointer" onclick="location='./?ppp=secrets&tagihan=isolir&session=<?= $session; ?>'" title="Terisolir"><i class="fa fa-ban"></i> Isolir: <?= $counts_billing['isolir']; ?></span>
      <span class="badge bg-secondary pd-5 radius-3 mr-l-5"><i class="fa fa-users"></i> Total: <?= $TotalRegAll; ?></span>
    </div>
  </div>

  <div id="liveAlertBox" style="display:none; margin-top:8px;" class="alert bg-info pd-10 radius-3">
    <i class="fa fa-bolt"></i> <span id="liveAlertText">Live event</span>
  </div>

  <div class="overflow mr-t-10 box-bordered" style="max-height: 75vh">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
    <tr>
      <th style="min-width:65px;" class="align-middle text-center" id="cuser"><?= $TotalReg; ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?? "Username" ?></th>
      <th>Password</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?? "Profile" ?></th>
      <th class="pointer text-center" title="Click to sort"><i class="fa fa-sort"></i> Tagihan</th>
      <th class="text-center" style="min-width: 140px;">Aksi Tagihan</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Service</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Remote Address</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Caller ID</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Koneksi</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?? "Comment" ?></th>
    </tr>
    </thead>
    <tbody id="tbody">
    <?php
    for ($i = 0; $i < $TotalReg; $i++) {
        $userdetails = $filtered_users[$i];
        $uid = $userdetails['.id'];
        $uname = $userdetails['name'];
        $upass = $userdetails['password'] ?? '';
        $uprofile = $userdetails['profile'] ?? 'default';
        $uservice = $userdetails['service'] ?? 'any';
        $uremote = $userdetails['remote-address'] ?? '-';
        $ucaller = $userdetails['caller-id'] ?? '-';
        $ucomment = $userdetails['comment'] ?? '';
        $udisabled = ($userdetails['disabled'] === 'true');
        $is_online = isset($active_map[$uname]);

        // Tagihan parsing
        $stat = MikhmonBilling::getStatus($uprofile, $ucomment);
        $parsed = MikhmonBilling::parseComment($ucomment);
        $is_isolir = ($stat['code'] === 'isolir' || $uprofile === 'pppoe-ISOLIR');

        echo "<tr id='row-user-" . htmlspecialchars($uname) . "' data-user='" . htmlspecialchars($uname) . "'>";
        echo "<td style='text-align:center;'>";
        echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Yakin ingin menghapus user (" . addslashes($uname) . ")?')){loadpage('./?remove-pppsecret=" . urlencode($uid) . "&session=" . $session . "');loader();}else{}\" title='Hapus " . htmlspecialchars($uname) . "'></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

        if ($udisabled) {
            $uriprocess = "'./?enable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="text-warning pointer" title="Enable User ' . htmlspecialchars($uname) . '" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-lock"></i></span>';
        } else {
            $uriprocess = "'./?disable-pppsecret=" . urlencode($uid) . "&session=" . $session . "'";
            echo '<span class="pointer text-green" title="Disable User ' . htmlspecialchars($uname) . '" onclick="loadpage(' . $uriprocess . ');loader();"><i class="fa fa-unlock"></i></span>';
        }
        echo "</td>";

        echo "<td><a title='Open User " . htmlspecialchars($uname) . "' href='./?secret=" . urlencode($uname) . "&session=" . $session . "'><i class='fa fa-edit'></i> <strong>" . htmlspecialchars($uname) . "</strong></a>";
        if (!empty($parsed['label']) && $parsed['label'] !== $uname) {
            echo " <small class='text-muted' style='display:block; font-size:11px;'>" . htmlspecialchars($parsed['label']) . "</small>";
        }
        echo "</td>";

        echo "<td><code>" . htmlspecialchars($upass) . "</code></td>";
        echo "<td><a id='prof-label-" . htmlspecialchars($uname) . "' href='./?ppp=secrets&profile=" . urlencode($uprofile) . "&session=" . $session . "' title='Filter by " . htmlspecialchars($uprofile) . "'>" . htmlspecialchars($uprofile) . "</a></td>";

        // Status Tagihan Badge
        echo "<td class='text-center' id='badge-tagihan-" . htmlspecialchars($uname) . "'>";
        echo "<span class='badge bg-" . $stat['badge'] . " pd-5 radius-3' title='" . htmlspecialchars($stat['hint']) . "'>";
        echo "<i class='fa " . $stat['icon'] . "'></i> " . htmlspecialchars($stat['label']);
        echo "</span>";
        if (!empty($parsed['lunas'])) {
            echo "<small style='display:block; font-size:10px; margin-top:2px; opacity:0.8;'>Bln: " . htmlspecialchars($parsed['lunas']) . "</small>";
        }
        echo "</td>";

        // Aksi Tagihan Buttons (AJAX enabled)
        echo "<td class='text-center'>";
        echo "<div class='btn-group' role='group'>";
        // Button Lunas
        echo "<button type='button' class='btn btn-sm btn-success pd-2 radius-2 mr-r-5 pointer' title='Tandai Lunas Bulan Ini' onclick=\"quickBillingAction('lunas', '" . addslashes($uname) . "')\"><i class='fa fa-check'></i> Lunas</button>";
        // Button Isolir / Pulihkan
        if ($is_isolir) {
            echo "<button type='button' class='btn btn-sm btn-warning pd-2 radius-2 pointer' title='Pulihkan Paket Asal' onclick=\"quickBillingAction('pulih', '" . addslashes($uname) . "')\"><i class='fa fa-refresh'></i> Pulih</button>";
        } else {
            echo "<button type='button' class='btn btn-sm btn-danger pd-2 radius-2 pointer' title='Isolir Pelanggan' onclick=\"quickBillingAction('isolir', '" . addslashes($uname) . "')\"><i class='fa fa-ban'></i> Isolir</button>";
        }
        echo "</div>";
        echo "</td>";

        echo "<td>" . htmlspecialchars($uservice) . "</td>";
        echo "<td>" . htmlspecialchars($uremote) . "</td>";
        echo "<td>" . htmlspecialchars($ucaller) . "</td>";

        echo "<td>";
        if ($is_online) {
            echo '<span class="text-green"><i class="fa fa-circle"></i> Online</span>';
        } elseif ($udisabled) {
            echo '<span class="text-danger"><i class="fa fa-ban"></i> Disabled</span>';
        } else {
            echo '<span class="text-muted" style="opacity:0.6;"><i class="fa fa-circle-o"></i> Offline</span>';
        }
        echo "</td>";

        echo "<td id='comm-user-" . htmlspecialchars($uname) . "'><small>" . htmlspecialchars($ucomment) . "</small></td>";
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
            // Refresh row or reload
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
