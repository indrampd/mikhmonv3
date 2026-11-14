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
$suggested_webhook = !empty($active_webhook_url) ? $active_webhook_url : "https://mikhmon.indrampd.work/bot.php";
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-telegram"></i> Billing & Bot Telegram
      <span style="font-size: 14px">
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

  <!-- Summary Cards -->
  <div class="row">
    <div class="col-3 col-m-6 col-box-3">
      <div class="box bg-green text-center pd-10 radius-3 pointer" onclick="location='./?ppp=secrets&tagihan=lunas&session=<?= $session; ?>'">
        <h4 style="margin:0;"><i class="fa fa-check-circle"></i> <?= $counts_billing['lunas']; ?></h4>
        <small>Tagihan Lunas (Aktif)</small>
      </div>
    </div>
    <div class="col-3 col-m-6 col-box-3">
      <div class="box bg-yellow text-center pd-10 radius-3 pointer" onclick="location='./?ppp=secrets&tagihan=tenggang&session=<?= $session; ?>'">
        <h4 style="margin:0;"><i class="fa fa-clock-o"></i> <?= $counts_billing['tenggang']; ?></h4>
        <small>Masa Tenggang</small>
      </div>
    </div>
    <div class="col-3 col-m-6 col-box-3">
      <div class="box bg-red text-center pd-10 radius-3 pointer" onclick="location='./?ppp=secrets&tagihan=isolir&session=<?= $session; ?>'">
        <h4 style="margin:0;"><i class="fa fa-ban"></i> <?= $counts_billing['isolir']; ?></h4>
        <small>Terisolir</small>
      </div>
    </div>
    <div class="col-3 col-m-6 col-box-3">
      <div class="box bg-secondary text-center pd-10 radius-3 pointer" onclick="location='./?ppp=secrets&session=<?= $session; ?>'">
        <h4 style="margin:0;"><i class="fa fa-users"></i> <?= $total_cust; ?></h4>
        <small>Total Pelanggan PPPoE</small>
      </div>
    </div>
  </div>

  <div class="row mr-t-20">
    <!-- Bot Settings Form -->
    <div class="col-6 col-box-6">
      <div class="card" style="box-shadow:none; border:1px solid #ddd;">
        <div class="card-header">
          <h4><i class="fa fa-cogs"></i> Pengaturan Bot Telegram</h4>
        </div>
        <div class="card-body">
          <form method="POST" action="./?billing=overview&session=<?= $session; ?>">
            <input type="hidden" name="action_type" value="save_config">
            <input type="hidden" name="save_bot_config" value="1">
            <div class="form-group mr-b-10">
              <label>Telegram Bot Token:</label>
              <input type="password" name="bot_token" class="form-control" style="width:100%; padding:6px;" value="<?= htmlspecialchars($config['token'] ?? ''); ?>" placeholder="123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ">
            </div>

            <div class="form-group mr-b-10">
              <label>Webhook Secret Token:</label>
              <input type="text" name="webhook_secret" class="form-control" style="width:100%; padding:6px;" value="<?= htmlspecialchars($config['webhook_secret'] ?? ''); ?>" placeholder="Random secret string untuk validasi request Telegram">
            </div>

            <div class="row">
              <div class="col-6 mr-b-10">
                <label>Profil Isolir (PPPoE):</label>
                <input type="text" name="isolir_profile" class="form-control" style="width:100%; padding:6px;" value="<?= htmlspecialchars($config['isolir_profile'] ?? 'pppoe-ISOLIR'); ?>" placeholder="pppoe-ISOLIR">
              </div>
              <div class="col-6 mr-b-10">
                <label>Tgl Batas Bayar (Default):</label>
                <input type="number" min="1" max="28" name="cutoff_day" class="form-control" style="width:100%; padding:6px;" value="<?= (int)($config['cutoff_day'] ?? 10); ?>">
              </div>
            </div>

            <div class="form-group mr-b-10">
              <label>Owner IDs (Telegram User ID, pisahkan koma):</label>
              <input type="text" name="owner_ids" class="form-control" style="width:100%; padding:6px;" value="<?= htmlspecialchars(implode(', ', $config['owner_ids'] ?? array())); ?>" placeholder="562035561">
            </div>

            <div class="form-group mr-b-10">
              <label>Group IDs (Chat ID Grup Telegram, pisahkan koma):</label>
              <input type="text" name="group_ids" class="form-control" style="width:100%; padding:6px;" value="<?= htmlspecialchars(implode(', ', $config['group_ids'] ?? array())); ?>" placeholder="-367197368">
            </div>

            <div class="form-group mr-b-15">
              <label>Cakupan Izin Grup (Group Scope):</label>
              <select name="group_scope" class="form-control" style="width:100%; padding:6px;">
                <option value="tagih" <?= ($config['group_scope'] ?? '') === 'tagih' ? 'selected' : ''; ?>>Tagih (Boleh /tagihan, /cek, /lunas)</option>
                <option value="lihat" <?= ($config['group_scope'] ?? '') === 'lihat' ? 'selected' : ''; ?>>Lihat Saja (Hanya /tagihan dan /cek)</option>
                <option value="penuh" <?= ($config['group_scope'] ?? '') === 'penuh' ? 'selected' : ''; ?>>Penuh (Semua perintah termasuk /isolir)</option>
              </select>
            </div>

            <button type="submit" name="save_bot_config" class="btn btn-primary pd-5 radius-3"><i class="fa fa-save"></i> Simpan Pengaturan</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Webhook Management -->
    <div class="col-6 col-box-6">
      <div class="card" style="box-shadow:none; border:1px solid #ddd;">
        <div class="card-header">
          <h4><i class="fa fa-link"></i> Webhook Controller
            <?php if (!empty($active_webhook_url)): ?>
              <span class="badge bg-success pd-5 radius-3" style="font-size:11px; float:right;"><i class="fa fa-check"></i> Aktif</span>
            <?php else: ?>
              <span class="badge bg-danger pd-5 radius-3" style="font-size:11px; float:right;"><i class="fa fa-ban"></i> Belum Terpasang</span>
            <?php endif; ?>
          </h4>
        </div>
        <div class="card-body">
          <form method="POST" action="./?billing=overview&session=<?= $session; ?>">
            <div class="form-group mr-b-10">
              <label>URL Webhook Telegram:</label>
              <input type="text" name="webhook_url" class="form-control" style="width:100%; padding:6px;" value="<?= htmlspecialchars($suggested_webhook); ?>">
              <small class="text-muted">Gunakan domain dengan DNS publik (misal <code>https://billing.indrampd.work/bot.php</code>). Domain lokal/internal seperti AdGuard rewrite tidak dapat dijangkau oleh server Telegram.</small>
            </div>

            <div class="btn-group mr-t-10">
              <button type="submit" name="set_webhook" class="btn btn-success pd-5 radius-3 mr-r-5"><i class="fa fa-plug"></i> Pasang Webhook</button>
              <button type="submit" name="check_webhook" class="btn btn-info pd-5 radius-3 mr-r-5"><i class="fa fa-search"></i> Cek Status</button>
              <button type="submit" name="delete_webhook" class="btn btn-danger pd-5 radius-3" onclick="return confirm('Yakin ingin mencabut Webhook?')"><i class="fa fa-trash"></i> Cabut Webhook</button>
            </div>
          </form>

          <hr class="mr-t-15 mr-b-15">

          <h5><i class="fa fa-info-circle"></i> Petunjuk Perintah Telegram</h5>
          <p style="font-size:12.5px; line-height:1.6;" class="text-muted">
            Setelah webhook terpasang, kamu bisa mengoperasikan bot langsung dari akun Telegram atau grup terdaftar:<br>
            • <code>/tagihan</code> — Melihat ringkasan dan daftar tombol inline.<br>
            • <code>/lunas nama_user</code> — Menandai lunas & memulihkan profil.<br>
            • <code>/isolir nama_user</code> — Mengisolir user yang menunggak.<br>
            • <code>/cek nama_user</code> — Memeriksa koneksi & status tagihan.<br>
            • Perubahan dari Telegram akan langsung <b>ter-update live</b> di tabel PPPoE Mikhmon tanpa refresh.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Activity Log -->
  <div class="row mr-t-20">
    <div class="col-12">
      <div class="card" style="box-shadow:none; border:1px solid #ddd;">
        <div class="card-header">
          <h4><i class="fa fa-history"></i> Riwayat Aksi Tagihan Terakhir</h4>
        </div>
        <div class="card-body">
          <div class="overflow box-bordered" style="max-height: 40vh;">
            <table class="table table-bordered table-hover text-nowrap">
              <thead>
                <tr>
                  <th style="width:150px;">Waktu</th>
                  <th>Aktor / Telegram ID</th>
                  <th>Aksi</th>
                  <th>Pelanggan</th>
                  <th>Detail</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (empty($events)) {
                    echo "<tr><td colspan='5' class='text-center text-muted'>Belum ada riwayat aktivitas.</td></tr>";
                } else {
                    foreach ($events as $ev) {
                        $badge = ($ev['action'] === 'lunas') ? 'success' : (($ev['action'] === 'isolir') ? 'danger' : 'info');
                        echo "<tr>";
                        echo "<td><code>" . htmlspecialchars($ev['ts']) . "</code></td>";
                        echo "<td>" . htmlspecialchars($ev['tg_id']) . "</td>";
                        echo "<td><span class='badge bg-{$badge} pd-2 radius-2'>" . strtoupper(htmlspecialchars($ev['action'])) . "</span></td>";
                        echo "<td><strong>" . htmlspecialchars($ev['target']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($ev['detail']) . "</td>";
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
</div>
</div>
