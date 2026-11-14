<?php
/*
 * Billing & Telegram Bot Management Dashboard for Mikhmon v3
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

require_once dirname(__DIR__) . '/lib/billing.class.php';
require_once dirname(__DIR__) . '/lib/telegram_bot.class.php';

$session = $_GET['session'] ?? '';
$bot = new MikhmonTelegramBot();
$config = MikhmonBilling::getConfig();
$msg_alert = '';
$alert_type = 'info';

// Process Actions
if (isset($_POST['save_bot_config']) || ($_POST['action_type'] ?? '') === 'save_config') {
    $token = trim($_POST['bot_token'] ?? '');
    $secret = trim($_POST['webhook_secret'] ?? '');
    $isolir_prof = trim($_POST['isolir_profile'] ?? 'pppoe-ISOLIR');
    $cutoff = (int)($_POST['cutoff_day'] ?? 10);
    $scope = $_POST['group_scope'] ?? 'tagih';

    $owners_raw = explode(',', $_POST['owner_ids'] ?? '');
    $owner_ids = array_values(array_filter(array_map('trim', $owners_raw)));

    $groups_raw = explode(',', $_POST['group_ids'] ?? '');
    $group_ids = array_values(array_filter(array_map('trim', $groups_raw)));

    $config['token'] = $token;
    $config['webhook_secret'] = $secret;
    $config['isolir_profile'] = $isolir_prof;
    $config['cutoff_day'] = $cutoff;
    $config['group_scope'] = $scope;
    $config['owner_ids'] = $owner_ids;
    $config['group_ids'] = $group_ids;

    $saved = MikhmonBilling::saveConfig($config);
    if ($saved !== false) {
        $config = MikhmonBilling::getConfig();
        $msg_alert = "Konfigurasi Bot Telegram berhasil disimpan!";
        $alert_type = "success";
    } else {
        $msg_alert = "Gagal menyimpan file konfigurasi. Periksa hak akses direktori data/.";
        $alert_type = "danger";
    }
} elseif (isset($_POST['set_webhook'])) {
    $webhook_url = trim($_POST['webhook_url'] ?? '');
    $secret = $config['webhook_secret'] ?? '';
    if (empty($webhook_url)) {
        $msg_alert = "URL Webhook tidak boleh kosong.";
        $alert_type = "danger";
    } else {
        @$bot->deleteWebhook();
        $res = $bot->setWebhook($webhook_url, $secret);
        if ($res['ok']) {
            $msg_alert = "Webhook Telegram berhasil dipasang ke: " . htmlspecialchars($webhook_url);
            $alert_type = "success";
        } else {
            $msg_alert = "Gagal memasang webhook: " . htmlspecialchars($res['description'] ?? 'Error');
            $alert_type = "danger";
        }
    }
} elseif (isset($_POST['check_webhook'])) {
    $info = $bot->getWebhookInfo();
    $msg_alert = "Status Webhook: " . json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $alert_type = ($info['ok'] ?? false) ? "info" : "danger";
} elseif (isset($_POST['delete_webhook'])) {
    $res = $bot->deleteWebhook();
    if ($res['ok']) {
        $msg_alert = "Webhook Telegram berhasil dicabut.";
        $alert_type = "warning";
    } else {
        $msg_alert = "Gagal mencabut webhook: " . htmlspecialchars($res['description'] ?? 'Error');
        $alert_type = "danger";
    }
}

// Compute PPPoE Stats
$secrets = $API->comm("/ppp/secret/print");
$counts_billing = array('lunas' => 0, 'tenggang' => 0, 'isolir' => 0, 'none' => 0);
if (is_array($secrets)) {
    foreach ($secrets as $s) {
        $st = MikhmonBilling::getStatus($s['profile'] ?? '', $s['comment'] ?? '', $config['cutoff_day'] ?? 10);
        if (isset($counts_billing[$st['code']])) {
            $counts_billing[$st['code']]++;
        }
    }
}
$total_cust = count($secrets);

// Recent Events from SQLite
$events = MikhmonBilling::getEvents(25);

// Detect current webhook or recommend public tunnel endpoint
$webhook_info = $bot->getWebhookInfo();
$active_webhook_url = ($webhook_info['ok'] ?? false) ? ($webhook_info['result']['url'] ?? '') : '';
$webhook_pending = ($webhook_info['ok'] ?? false) ? ($webhook_info['result']['pending_update_count'] ?? 0) : 0;
$suggested_webhook = !empty($active_webhook_url) ? $active_webhook_url : "https://mikhmon.indrampd.work/bot.php";

// Bot identity (best-effort, cached per request)
$bot_username = '';
$me = $bot->apiCall('getMe');
if (($me['ok'] ?? false) && !empty($me['result']['username'])) {
    $bot_username = $me['result']['username'];
}
?>

<link rel="stylesheet" href="./css/ppp-billing.css">

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-telegram"></i> Billing &amp; Bot Telegram
      <span style="font-size: 13px;">
        &nbsp; | &nbsp; <a href="./?ppp=secrets&session=<?= $session; ?>" title="Kembali ke Secrets"><i class="fa fa-users"></i> User Secrets</a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Active Connections"><i class="fa fa-exchange"></i> PPP Active</a>
      </span>
    </h3>
</div>

<div class="card-body">
  <?php if (!empty($msg_alert)): ?>
    <div class="alert bg-<?= $alert_type; ?> pd-10 radius-3 mr-b-10">
      <pre style="margin:0; background:transparent; border:none; color:inherit; font-size:12px; white-space:pre-wrap;"><?= htmlspecialchars($msg_alert); ?></pre>
    </div>
  <?php endif; ?>

  <!-- KPI bar (sama dengan halaman Secrets) -->
  <div class="kpi-bar">
    <a class="kpi-pill" href="./?ppp=secrets&session=<?= $session; ?>">
      <i class="fa fa-users"></i> Total: <strong><?= $total_cust; ?></strong>
    </a>
    <a class="kpi-pill" href="./?ppp=secrets&tagihan=lunas&session=<?= $session; ?>">
      <i class="fa fa-check-circle text-green"></i> Lunas: <strong><?= $counts_billing['lunas']; ?></strong>
    </a>
    <a class="kpi-pill" href="./?ppp=secrets&tagihan=tenggang&session=<?= $session; ?>">
      <i class="fa fa-clock-o text-yellow"></i> Tenggang: <strong><?= $counts_billing['tenggang']; ?></strong>
    </a>
    <a class="kpi-pill" href="./?ppp=secrets&tagihan=isolir&session=<?= $session; ?>">
      <i class="fa fa-ban text-red"></i> Isolir: <strong><?= $counts_billing['isolir']; ?></strong>
    </a>
    <span class="kpi-pill is-static">
      <span class="status-dot <?= !empty($active_webhook_url) ? 'bg-success' : 'bg-danger'; ?>"></span>
      Webhook: <strong><?= !empty($active_webhook_url) ? 'Aktif' : 'Nonaktif'; ?></strong>
    </span>
  </div>

  <div class="row">
    <!-- KIRI: Pengaturan Bot -->
    <div class="col-6 col-box-6">
      <div class="ui-section">
        <div class="ui-section-head">
          <h4><i class="fa fa-cogs"></i> Pengaturan Bot</h4>
          <?php if ($bot_username !== ''): ?>
            <span class="kpi-pill is-static" style="font-size:11px;">@<?= htmlspecialchars($bot_username); ?></span>
          <?php endif; ?>
        </div>
        <div class="ui-section-body">
          <form method="POST" action="./?billing=overview&session=<?= $session; ?>">
            <input type="hidden" name="action_type" value="save_config">
            <input type="hidden" name="save_bot_config" value="1">

            <div class="ui-field-grid">
              <div class="ui-field span-2">
                <label>Telegram Bot Token</label>
                <input type="password" name="bot_token" value="<?= htmlspecialchars($config['token'] ?? ''); ?>" placeholder="123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ" autocomplete="off">
              </div>

              <div class="ui-field span-2">
                <label>Webhook Secret Token</label>
                <input type="text" name="webhook_secret" value="<?= htmlspecialchars($config['webhook_secret'] ?? ''); ?>" placeholder="Random secret string untuk validasi request Telegram" autocomplete="off">
                <small class="ui-hint">Dipakai Telegram pada header <code>X-Telegram-Bot-Api-Secret-Token</code>.</small>
              </div>

              <div class="ui-field">
                <label>Profil Isolir</label>
                <input type="text" name="isolir_profile" value="<?= htmlspecialchars($config['isolir_profile'] ?? 'pppoe-ISOLIR'); ?>" placeholder="pppoe-ISOLIR">
              </div>

              <div class="ui-field">
                <label>Tgl Batas Bayar</label>
                <input type="number" min="1" max="28" name="cutoff_day" value="<?= (int)($config['cutoff_day'] ?? 10); ?>">
              </div>

              <div class="ui-field span-2">
                <label>Owner IDs</label>
                <input type="text" name="owner_ids" value="<?= htmlspecialchars(implode(', ', $config['owner_ids'] ?? array())); ?>" placeholder="562035561">
                <small class="ui-hint">Telegram User ID yang punya akses penuh, pisahkan dengan koma.</small>
              </div>

              <div class="ui-field span-2">
                <label>Group IDs</label>
                <input type="text" name="group_ids" value="<?= htmlspecialchars(implode(', ', $config['group_ids'] ?? array())); ?>" placeholder="-367197368">
                <small class="ui-hint">Chat ID grup Telegram yang boleh mengakses bot.</small>
              </div>

              <div class="ui-field span-2">
                <label>Cakupan Izin Grup</label>
                <select name="group_scope">
                  <option value="tagih" <?= ($config['group_scope'] ?? '') === 'tagih' ? 'selected' : ''; ?>>Tagih &mdash; /tagihan, /cek, /lunas</option>
                  <option value="lihat" <?= ($config['group_scope'] ?? '') === 'lihat' ? 'selected' : ''; ?>>Lihat Saja &mdash; /tagihan, /cek</option>
                  <option value="penuh" <?= ($config['group_scope'] ?? '') === 'penuh' ? 'selected' : ''; ?>>Penuh &mdash; semua perintah termasuk /isolir</option>
                </select>
              </div>
            </div>

            <button type="submit" class="btn btn-primary pd-5 radius-3 mr-t-15"><i class="fa fa-save"></i> Simpan Pengaturan</button>
          </form>
        </div>
      </div>
    </div>

    <!-- KANAN: Webhook + Perintah -->
    <div class="col-6 col-box-6">
      <div class="ui-section">
        <div class="ui-section-head">
          <h4><i class="fa fa-link"></i> Webhook</h4>
          <?php if (!empty($active_webhook_url)): ?>
            <span class="kpi-pill is-static bg-success" style="font-size:11px;"><i class="fa fa-check"></i> Aktif</span>
          <?php else: ?>
            <span class="kpi-pill is-static bg-danger" style="font-size:11px;"><i class="fa fa-ban"></i> Belum Terpasang</span>
          <?php endif; ?>
        </div>
        <div class="ui-section-body">
          <form method="POST" action="./?billing=overview&session=<?= $session; ?>">
            <div class="ui-field">
              <label>URL Webhook</label>
              <input type="text" name="webhook_url" value="<?= htmlspecialchars($suggested_webhook); ?>">
              <small class="ui-hint">Harus domain publik ber-HTTPS. Domain lokal (AdGuard rewrite) tidak bisa dijangkau server Telegram.</small>
            </div>

            <?php if (!empty($active_webhook_url)): ?>
              <div style="margin-top:10px; font-size:12px; opacity:0.75;">
                <i class="fa fa-clock-o"></i> Pending update: <strong><?= (int)$webhook_pending; ?></strong>
              </div>
            <?php endif; ?>

            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:12px;">
              <button type="submit" name="set_webhook" class="btn btn-success pd-5 radius-3"><i class="fa fa-plug"></i> Pasang</button>
              <button type="submit" name="check_webhook" class="btn btn-info pd-5 radius-3"><i class="fa fa-search"></i> Cek Status</button>
              <button type="submit" name="delete_webhook" class="btn btn-danger pd-5 radius-3" onclick="return confirm('Yakin ingin mencabut Webhook?')"><i class="fa fa-trash"></i> Cabut</button>
            </div>
          </form>
        </div>
      </div>

      <div class="ui-section">
        <div class="ui-section-head">
          <h4><i class="fa fa-terminal"></i> Perintah Bot</h4>
        </div>
        <div class="ui-section-body">
          <ul class="cmd-list">
            <li><code>/tagihan</code> <span>Ringkasan tagihan &amp; tombol aksi inline</span></li>
            <li><code>/lunas &lt;user&gt;</code> <span>Tandai lunas &amp; pulihkan profil</span></li>
            <li><code>/isolir &lt;user&gt;</code> <span>Isolir pelanggan menunggak</span></li>
            <li><code>/cek &lt;user&gt;</code> <span>Cek koneksi &amp; status tagihan</span></li>
          </ul>
          <small class="ui-hint" style="display:block; margin-top:8px;">
            <i class="fa fa-bolt"></i> Aksi dari Telegram langsung ter-push <em>live</em> ke tabel PPPoE tanpa refresh.
          </small>
        </div>
      </div>
    </div>
  </div>

  <!-- Riwayat Aksi -->
  <div class="ui-section">
    <div class="ui-section-head">
      <h4><i class="fa fa-history"></i> Riwayat Aksi Tagihan</h4>
      <span style="font-size:12px; opacity:0.65;"><?= count($events); ?> entri terakhir</span>
    </div>
    <div class="ui-section-body" style="padding:0;">
      <div class="overflow" style="max-height: 42vh;">
        <table class="table table-bordered table-hover text-nowrap secret-table" style="margin:0;">
          <thead>
            <tr>
              <th style="width:150px;">Waktu</th>
              <th style="width:150px;">Aktor</th>
              <th style="width:100px;">Aksi</th>
              <th>Pelanggan</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (empty($events)) {
                echo "<tr><td colspan='5' class='text-center text-muted pd-15'>Belum ada riwayat aktivitas.</td></tr>";
            } else {
                foreach ($events as $ev) {
                    $badge = ($ev['action'] === 'lunas') ? 'success' : (($ev['action'] === 'isolir') ? 'danger' : 'info');
                    echo "<tr>";
                    echo "<td><small style='opacity:0.75;'>" . htmlspecialchars($ev['ts']) . "</small></td>";
                    echo "<td><small>" . htmlspecialchars($ev['tg_id']) . "</small></td>";
                    echo "<td><span class='badge bg-{$badge} pd-2 radius-2' style='font-size:11px;'>" . strtoupper(htmlspecialchars($ev['action'])) . "</span></td>";
                    echo "<td><strong>" . htmlspecialchars($ev['target']) . "</strong></td>";
                    echo "<td><small style='opacity:0.8;'>" . htmlspecialchars($ev['detail']) . "</small></td>";
                    echo "</tr>";
                }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
</div>
</div>
</div>
