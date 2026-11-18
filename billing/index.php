<?php
/*
 * Billing & Telegram Bot Management Dashboard for Mikhmon v3
 * Setup data router, penanganan aksi POST, dan redirect PRG ada di process.php.
 */
include_once __DIR__ . '/process.php';

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

// Rekap voucher hotspot untuk KPI (modul Telegram /hs*).
$hs_counts = null;
if (!empty($API->connected) && !empty($hs_cfg['enabled'])) {
    $hs_sum = MikhmonHotspotBilling::summarize($API);
    $hs_counts = $hs_sum['counts'];
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

// Status webhook dibaca dari cache yang ditulis watchdog tiap 5 menit.
// Memanggil Telegram di setiap page load membuat badge jatuh ke "Nonaktif"
// setiap kali panggilan itu lambat/gagal, padahal webhook-nya sehat.
// File ada di app/data: open_basedir PHP hanya mengizinkan /var/www.
$webhook_status_file = dirname(__DIR__) . '/data/webhook-status.json';
$webhook_cache = array();
if (is_readable($webhook_status_file)) {
    $decoded = json_decode((string) @file_get_contents($webhook_status_file), true);
    if (is_array($decoded)) {
        $webhook_cache = $decoded;
    }
}
$webhook_cache_age = isset($webhook_cache['checked_at']) ? (time() - (int) $webhook_cache['checked_at']) : null;
$webhook_cache_fresh = $webhook_cache_age !== null && $webhook_cache_age <= 900;

$active_webhook_url = '';
$webhook_pending = 0;
$bot_username = '';
$webhook_checked_at = '';
$webhook_note = '';

if ($webhook_cache_fresh) {
    $active_webhook_url = (string) ($webhook_cache['url'] ?? '');
    $webhook_pending = (int) ($webhook_cache['pending_update_count'] ?? 0);
    $bot_username = (string) ($webhook_cache['username'] ?? '');
    $webhook_checked_at = date('d-m-Y H:i', (int) $webhook_cache['checked_at']);
    $webhook_note = (string) ($webhook_cache['note'] ?? '');
    $webhook_state = !empty($webhook_cache['reachable'])
        ? (!empty($active_webhook_url) ? 'aktif' : 'nonaktif')
        : 'unknown';
} else {
    $webhook_info = $bot->getWebhookInfo();
    $webhook_ok = $webhook_info['ok'] ?? false;
    $active_webhook_url = $webhook_ok ? ($webhook_info['result']['url'] ?? '') : '';
    $webhook_pending = $webhook_ok ? ($webhook_info['result']['pending_update_count'] ?? 0) : 0;
    $webhook_state = $webhook_ok ? (!empty($active_webhook_url) ? 'aktif' : 'nonaktif') : 'unknown';
    $webhook_note = $webhook_ok ? '' : (string) ($webhook_info['description'] ?? 'Telegram tidak terjangkau');

    $me = $bot->apiCall('getMe');
    if (($me['ok'] ?? false) && !empty($me['result']['username'])) {
        $bot_username = $me['result']['username'];
    }
}

$suggested_webhook = !empty($active_webhook_url)
    ? $active_webhook_url
    : ((trim((string)($config['webhook_url'] ?? '')) !== ''
        ? trim((string)$config['webhook_url'])
        : 'https://<domain-publik-anda>/bot.php'));

$webhook_state_label = array('aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'unknown' => 'Tidak diketahui');
$webhook_state_badge = array('aktif' => 'bg-success', 'nonaktif' => 'bg-danger', 'unknown' => 'bg-warning');
$webhook_state_icon = array('aktif' => 'fa-circle', 'nonaktif' => 'fa-circle', 'unknown' => 'fa-question-circle');

// Mesin isolir: status run terakhir dari cache JSON (ditulis cron tiap 15 menit).
$iso_cfg = $config['portal'] ?? array();
$iso_pool = (string)($iso_cfg['pool'] ?? 'pppoe_pool_isolir');
$iso_network = (string)($iso_cfg['network'] ?? '192.168.6.0/24');
$iso_domain = trim((string)($iso_cfg['domain'] ?? ''));

$isolir_cache = array();
$isolir_status_file = dirname(__DIR__) . '/data/isolir-status.json';
if (is_readable($isolir_status_file)) {
    $decoded = json_decode((string) @file_get_contents($isolir_status_file), true);
    if (is_array($decoded)) {
        $isolir_cache = $decoded;
    }
}
$iso_age = isset($isolir_cache['last_run']) ? (time() - (int)$isolir_cache['last_run']) : null;
if ($iso_age === null) $iso_state = 'never';
elseif ($iso_age <= 1800) $iso_state = 'fresh';
elseif ($iso_age <= 7200) $iso_state = 'stale';
else $iso_state = 'old';
if ($iso_state !== 'never' && empty($isolir_cache['ok'])) $iso_state = 'error';

// Prasyarat router portal isolir (dipemeriksa langsung, API halaman ini sudah tersambung).
$iso_pool_ok = false;
$iso_profile_ok = false;
$iso_nat_ok = false;
$iso_https_ok = false;
$iso_dnsnat_ok = false;
$iso_dnsstatic_ok = false;
if (!empty($API->connected)) {
    $pools = $API->comm('/ip/pool/print');
    if (is_array($pools)) {
        foreach ($pools as $p) {
            if (($p['name'] ?? '') === $iso_pool) { $iso_pool_ok = true; break; }
        }
    }
    $isoProfiles = $API->comm('/ppp/profile/print', array('?name' => $config['isolir_profile'] ?? 'pppoe-ISOLIR'));
    if (is_array($isoProfiles) && !empty($isoProfiles[0])) {
        $iso_profile_ok = (($isoProfiles[0]['remote-address'] ?? '') === $iso_pool);
    }
    $isoNat = $API->comm('/ip/firewall/nat/print', array('?comment' => 'isolir-portal'));
    $iso_nat_ok = is_array($isoNat) && !empty($isoNat[0]);
    $isoNatDns = $API->comm('/ip/firewall/nat/print', array('?comment' => 'isolir-dns', '?protocol' => 'udp'));
    $iso_dnsnat_ok = is_array($isoNatDns) && !empty($isoNatDns[0]);
    if ($iso_domain !== '') {
        $isoNatHttps = $API->comm('/ip/firewall/nat/print', array('?comment' => 'isolir-https'));
        $iso_https_ok = is_array($isoNatHttps) && !empty($isoNatHttps[0]);
        $isoDnsStatic = $API->comm('/ip/dns/static/print', array('?name' => $iso_domain));
        $iso_dnsstatic_ok = is_array($isoDnsStatic) && !empty($isoDnsStatic[0]);
    }
}
?>


<div>
<div>
<div class="card">
<div class="card-header align-middle">
    <h3><i class="fa fa-telegram"></i> Billing &amp; Bot Telegram
      <span style="font-size: 13px;">
        &nbsp; | &nbsp; <a href="./?ppp=secrets&session=<?= $session; ?>" title="Kembali ke Secrets"><i class="fa fa-users"></i> User Secrets</a>
        &nbsp; | &nbsp; <a href="./?ppp=active&session=<?= $session; ?>" title="Active Connections"><i class="fa fa-exchange"></i> PPP Active</a>
      </span>
    </h3>
</div>

<div class="card-body" style="overflow:visible;">
  <?php if (!empty($msg_alert)): ?>
    <div class="alert bg-<?= $alert_type; ?> pd-10 radius-3 mr-b-10">
      <pre style="margin:0; background:transparent; border:none; color:inherit; font-size:12px; white-space:pre-wrap;"><?= htmlspecialchars($msg_alert); ?></pre>
    </div>
  <?php endif; ?>

  <!-- KPI bar (sama dengan halaman Secrets) -->
  <div class="pd-t-5 pd-b-5">
    <a class="btn bg-secondary radius-3" href="./?ppp=secrets&session=<?= $session; ?>">
      <i class="fa fa-users"></i> Total: <strong><?= $total_cust; ?></strong>
    </a>
    <a class="btn bg-secondary radius-3" href="./?ppp=secrets&tagihan=lunas&session=<?= $session; ?>">
      <i class="fa fa-check-circle text-green"></i> Lunas: <strong><?= $counts_billing['lunas']; ?></strong>
    </a>
    <a class="btn bg-secondary radius-3" href="./?ppp=secrets&tagihan=tenggang&session=<?= $session; ?>">
      <i class="fa fa-clock-o text-yellow"></i> Tenggang: <strong><?= $counts_billing['tenggang']; ?></strong>
    </a>
    <a class="btn bg-secondary radius-3" href="./?ppp=secrets&tagihan=isolir&session=<?= $session; ?>">
      <i class="fa fa-ban text-red"></i> Isolir: <strong><?= $counts_billing['isolir']; ?></strong>
    </a>
    <?php if ($hs_counts !== null): ?>
    <a class="btn bg-secondary radius-3" href="./?hotspot=users&profile=all&session=<?= $session; ?>">
      <i class="fa fa-ticket text-orange"></i> Voucher: <strong><?= array_sum($hs_counts); ?></strong>
    </a>
    <a class="btn bg-secondary radius-3" href="./?hotspot=active&session=<?= $session; ?>">
      <i class="fa fa-exchange text-green"></i> HS Online: <strong><?= $hs_counts['active']; ?></strong>
    </a>
    <?php endif; ?>
    <span class="btn bg-secondary radius-3" title="<?= htmlspecialchars($webhook_note !== '' ? $webhook_note : ('Diperiksa ' . ($webhook_checked_at !== '' ? $webhook_checked_at : 'saat halaman dibuka'))); ?>">
      <i class="fa <?= $webhook_state_icon[$webhook_state]; ?> <?= $webhook_state === 'aktif' ? 'text-green' : ($webhook_state === 'nonaktif' ? 'text-red' : 'text-yellow'); ?>"></i>
      Webhook: <strong><?= $webhook_state_label[$webhook_state]; ?></strong>
    </span>
  </div>

  <style>
    /* Grid (bukan float) supaya tinggi kedua kartu benar-benar sama dan tidak
       ada ruang kosong menggantung di bawah kolom kanan. */
    .bot-grid { display: grid; grid-template-columns: 1fr 1fr; align-items: stretch; }
    /* Float tidak dipakai untuk baris field: float yang lebih pendek (Tgl Batas Bayar)
       membuat field berikutnya naik ke sampingnya, sehingga Owner IDs dan Group IDs
       tidak sebaris. Grid mengunci tiap pasangan pada satu baris. */
    .field-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 10px; }
    .field-grid .fg-12 { grid-column: 1 / -1; }
    /* Pengaturan Bot: grup field yang bisa dilipat (details) supaya form panjang
       tidak membludak; pola sama dengan "Perintah Bot" di kartu Webhook.
       Warna abu transparan agar aman di tema light maupun dark. */
    .bot-group { border: 1px solid rgba(128,128,128,.25); border-radius: 6px;
                 background: rgba(128,128,128,.04); padding: 0 12px 10px; }
    .bot-group > summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px;
                           font-size: 13px; font-weight: 600; letter-spacing: .3px;
                           padding: 9px 0; user-select: none; }
    .bot-group > summary::-webkit-details-marker { display: none; }
    .bot-group .bot-caret { transition: transform .15s ease; font-size: 11px; opacity: .6; }
    .bot-group[open] > summary .bot-caret { transform: rotate(90deg); }
    .bot-group > .field-grid { margin-top: 2px; }
    /* Webhook: daftar perintah bot dua kolom agar tidak terlalu panjang. */
    .wh-cmds { columns: 2; column-gap: 18px; }
    .wh-cmds li { break-inside: avoid; }
    .wh-cmds li > code { display: inline-block; min-width: 148px; }
    @media (max-width: 750px) { .wh-cmds { columns: 1; } }
    @media (max-width: 750px) {
      .bot-grid { grid-template-columns: 1fr; }
      .field-grid { grid-template-columns: 1fr; }
    }
  </style>
  <div class="bot-grid">
    <!-- KIRI: Pengaturan Bot -->
    <div>
      <div class="card">
        <div class="card-header">
          <h3><i class="fa fa-cogs"></i> Pengaturan Bot
          <?php if ($bot_username !== ''): ?>
            <span class="badge bg-secondary radius-3" style="font-size:12px; margin-left:10px;">@<?= htmlspecialchars($bot_username); ?></span>
          <?php endif; ?>
          </h3>
        </div>
        <div class="card-body">
          <form method="POST" action="./?billing=overview&session=<?= $session; ?>">
            <input type="hidden" name="action_type" value="save_config">
            <input type="hidden" name="save_bot_config" value="1">

            <div class="field-grid">

              <details class="fg-12 bot-group" open>
                <summary><i class="fa fa-caret-right bot-caret"></i><i class="fa fa-key text-info"></i> Kredensial Bot</summary>
                <div class="field-grid">
                  <div class="fg-12 pd-b-5">
                    <label class="label-block">Telegram Bot Token</label>
                    <input class="form-control" type="password" name="bot_token" value="<?= htmlspecialchars($config['token'] ?? ''); ?>" placeholder="123456789:ABCdefGhIjkLmNoPqRsTuVwXyZ" autocomplete="off">
                  </div>

                  <div class="fg-12 pd-b-5">
                    <label class="label-block">Webhook Secret Token</label>
                    <input class="form-control" type="text" name="webhook_secret" value="<?= htmlspecialchars($config['webhook_secret'] ?? ''); ?>" placeholder="Random secret string untuk validasi request Telegram" autocomplete="off">
                    <small class="hint-text">Dipakai Telegram pada header <code>X-Telegram-Bot-Api-Secret-Token</code>.</small>
                  </div>
                </div>
              </details>

              <details class="fg-12 bot-group" open>
                <summary><i class="fa fa-caret-right bot-caret"></i><i class="fa fa-money text-green"></i> Tagihan &amp; Isolir</summary>
                <div class="field-grid">
                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_isolir_profile">Profil Isolir</label>
                    <?php $cur_isolir = $config['isolir_profile'] ?? 'pppoe-ISOLIR'; ?>
                    <?php if (!empty($ppp_profiles)): ?>
                      <select class="form-control" id="_isolir_profile" name="isolir_profile">
                        <?php if (!in_array($cur_isolir, $ppp_profiles, true)): ?>
                          <option value="<?= htmlspecialchars($cur_isolir); ?>" selected><?= htmlspecialchars($cur_isolir); ?> &mdash; tidak ada di router</option>
                        <?php endif; ?>
                        <?php foreach ($ppp_profiles as $profile_name): ?>
                          <?php $profile_rate = $ppp_profile_rates[$profile_name] ?? ''; ?>
                          <?php $profile_rate_label = $profile_rate !== '' ? strtok(trim($profile_rate), " \t") : ''; ?>
                          <option value="<?= htmlspecialchars($profile_name); ?>"<?= $profile_name === $cur_isolir ? ' selected' : ''; ?>><?= htmlspecialchars($profile_name); ?><?= $profile_rate_label !== '' ? ' — ' . htmlspecialchars($profile_rate_label) : ''; ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="hint-text">Diambil langsung dari <code>/ppp/profile</code> router &mdash; <?= count($ppp_profiles); ?> profil.</small>
                    <?php else: ?>
                      <input class="form-control" id="_isolir_profile" type="text" name="isolir_profile" value="<?= htmlspecialchars($cur_isolir); ?>" placeholder="pppoe-ISOLIR">
                      <small class="hint-text">Router tidak terjangkau &mdash; daftar profil tidak bisa dimuat.</small>
                    <?php endif; ?>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_cutoff_day">Tgl Batas Bayar</label>
                    <input class="form-control" id="_cutoff_day" type="number" min="1" max="28" name="cutoff_day" value="<?= (int)($config['cutoff_day'] ?? 10); ?>">
                  </div>
                </div>
              </details>

              <details class="fg-12 bot-group" open>
                <summary><i class="fa fa-caret-right bot-caret"></i><i class="fa fa-users text-orange"></i> Akses &amp; Izin</summary>
                <div class="field-grid">
                  <div class="fg-6 pd-b-5">
                    <label class="label-block">Owner IDs</label>
                    <input class="form-control" type="text" name="owner_ids" value="<?= htmlspecialchars(implode(', ', $config['owner_ids'] ?? array())); ?>" placeholder="562035561">
                    <small class="hint-text">Telegram User ID yang punya akses penuh, pisahkan dengan koma.</small>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block">Group IDs</label>
                    <input class="form-control" type="text" name="group_ids" value="<?= htmlspecialchars(implode(', ', $config['group_ids'] ?? array())); ?>" placeholder="-367197368">
                    <small class="hint-text">Chat ID grup Telegram yang boleh mengakses bot.</small>
                  </div>

                  <div class="fg-12 pd-b-5">
                    <label class="label-block">Cakupan Izin Grup</label>
                    <select class="form-control" name="group_scope">
                      <option value="tagih" <?= ($config['group_scope'] ?? '') === 'tagih' ? 'selected' : ''; ?>>Tagih &mdash; /tagihan, /cek, /lunas</option>
                      <option value="lihat" <?= ($config['group_scope'] ?? '') === 'lihat' ? 'selected' : ''; ?>>Lihat Saja &mdash; /tagihan, /cek</option>
                      <option value="penuh" <?= ($config['group_scope'] ?? '') === 'penuh' ? 'selected' : ''; ?>>Penuh &mdash; semua perintah termasuk /isolir</option>
                    </select>
                  </div>

                  <div class="fg-12 pd-b-5">
                    <label class="label-block">Notify IDs</label>
                    <input class="form-control" type="text" name="notify_ids" value="<?= htmlspecialchars(implode(', ', $config['notify_chat_ids'] ?? array())); ?>" placeholder="">
                    <small class="hint-text">Chat ID tambahan yang menerima /broadcast (pisahkan dengan koma).</small>
                  </div>
                </div>
              </details>

              <details class="fg-12 bot-group" open>
                <summary><i class="fa fa-caret-right bot-caret"></i><i class="fa fa-ticket text-yellow"></i> Modul Voucher Hotspot</summary>
                <div class="field-grid">
                  <div class="fg-12 pd-b-5">
                    <label class="label-block">Aktifkan Modul</label>
                    <label style="font-weight:400; cursor:pointer;">
                      <input type="checkbox" name="hs_enabled" value="1"<?= $hs_cfg['enabled'] ? ' checked' : ''; ?>>
                      Aktifkan perintah <code>/hs*</code> (cek, list, kick, reset, generate, hapus)
                    </label>
                  </div>

                  <div class="fg-12 pd-b-5">
                    <label class="label-block" for="_hs_default_profile">Profil Default Voucher</label>
                    <?php $cur_hs_prof = $hs_cfg['default_profile'] ?? ''; ?>
                    <?php if (!empty($hs_profiles)): ?>
                      <select class="form-control" id="_hs_default_profile" name="hs_default_profile">
                        <option value=""<?= $cur_hs_prof === '' ? ' selected' : ''; ?>>&mdash; tanya profil saat /hsgen &mdash;</option>
                        <?php foreach ($hs_profiles as $hp): ?>
                          <option value="<?= htmlspecialchars($hp); ?>"<?= $hp === $cur_hs_prof ? ' selected' : ''; ?>><?= htmlspecialchars($hp); ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <input class="form-control" id="_hs_default_profile" type="text" name="hs_default_profile" value="<?= htmlspecialchars($cur_hs_prof); ?>" placeholder="">
                    <?php endif; ?>
                    <small class="hint-text">Profil yang dipakai bila /hsgen dijalankan tanpa argumen profil.</small>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_hs_batch_max">Batas Batch /hsgen</label>
                    <input class="form-control" id="_hs_batch_max" type="number" min="1" max="500" name="hs_batch_max" value="<?= (int)($hs_cfg['batch_max'] ?? 50); ?>">
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_hs_gen_prefix">Prefix Nama Voucher</label>
                    <input class="form-control" id="_hs_gen_prefix" type="text" maxlength="6" name="hs_gen_prefix" value="<?= htmlspecialchars($hs_cfg['gen_prefix'] ?? ''); ?>" placeholder="">
                  </div>
                </div>
              </details>

              <details class="fg-12 bot-group" open>
                <summary><i class="fa fa-caret-right bot-caret"></i><i class="fa fa-ban text-red"></i> Portal Isolir</summary>
                <small class="hint-text" style="display:block; margin:-2px 0 8px;">Tampilan halaman yang dilihat pelanggan terisolir saat membuka browser (http://).</small>
                <div class="field-grid">
                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_contact">Nama Kontak</label>
                    <input class="form-control" id="_portal_contact" type="text" name="portal_contact" value="<?= htmlspecialchars($iso_cfg['contact'] ?? 'Admin'); ?>" placeholder="Admin">
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_phone">No. WhatsApp</label>
                    <input class="form-control" id="_portal_phone" type="text" name="portal_phone" value="<?= htmlspecialchars($iso_cfg['phone'] ?? ''); ?>" placeholder="08xxxxxxxxxx">
                    <small class="hint-text">Kosong = tombol WhatsApp tidak tampil di portal.</small>
                  </div>

                  <div class="fg-12 pd-b-5">
                    <label class="label-block" for="_portal_message">Pesan Kustom Portal</label>
                    <textarea class="form-control" id="_portal_message" name="portal_message" rows="2" placeholder="Kosong = pesan bawaan"><?= htmlspecialchars($iso_cfg['message'] ?? ''); ?></textarea>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_network">Subnet Isolir</label>
                    <input class="form-control" id="_portal_network" type="text" name="portal_network" value="<?= htmlspecialchars($iso_cfg['network'] ?? '192.168.6.0/24'); ?>">
                    <small class="hint-text">Harus sama dengan range pool di router.</small>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_pool">Nama Pool Isolir</label>
                    <input class="form-control" id="_portal_pool" type="text" name="portal_pool" value="<?= htmlspecialchars($iso_cfg['pool'] ?? 'pppoe_pool_isolir'); ?>">
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_server_ip">IP Server Portal</label>
                    <input class="form-control" id="_portal_server_ip" type="text" name="portal_server_ip" value="<?= htmlspecialchars($iso_cfg['server_ip'] ?? ''); ?>" placeholder="192.168.1.10">
                    <small class="hint-text">IP LAN server Mikhmon — tujuan dst-nat portal di router.</small>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_server_mac">MAC Server (lease DHCP)</label>
                    <input class="form-control" id="_portal_server_mac" type="text" name="portal_server_mac" value="<?= htmlspecialchars($iso_cfg['server_mac'] ?? ''); ?>" placeholder="02:00:00:00:00:01">
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_server_port">Port Web Server</label>
                    <input class="form-control" id="_portal_server_port" type="text" name="portal_server_port" value="<?= htmlspecialchars($iso_cfg['server_port'] ?? '8081'); ?>">
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_domain">Domain Portal (opsional)</label>
                    <input class="form-control" id="_portal_domain" type="text" name="portal_domain" value="<?= htmlspecialchars($iso_cfg['domain'] ?? ''); ?>" placeholder="isolir.example.com">
                    <small class="hint-text">Kosong = portal hanya via HTTP. Diisi = NAT 443 + DNS statis aktif.</small>
                  </div>

                  <div class="fg-6 pd-b-5">
                    <label class="label-block" for="_portal_tagline">Tagline Portal</label>
                    <input class="form-control" id="_portal_tagline" type="text" name="portal_tagline" value="<?= htmlspecialchars($iso_cfg['tagline'] ?? 'Layanan wifi termurah'); ?>">
                  </div>
                </div>
              </details>

            </div>

            <div class="pd-t-10">
              <button type="submit" class="btn bg-primary radius-3 pd-5"><i class="fa fa-save"></i> Simpan Pengaturan</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- KANAN: Webhook + Mesin Isolir (flex kolom: kartu Mesin melar mengisi sisa tinggi) -->
    <div style="display:flex; flex-direction:column;">
      <div class="card" style="display:flex; flex-direction:column;">
        <div class="card-header">
          <h3><i class="fa fa-link"></i> Webhook
            <span class="badge <?= $webhook_state_badge[$webhook_state]; ?> radius-3" style="font-size:12px; margin-left:10px;">
              <i class="fa <?= $webhook_state === 'aktif' ? 'fa-check' : ($webhook_state === 'nonaktif' ? 'fa-ban' : 'fa-question'); ?>"></i>
              <?= $webhook_state === 'aktif' ? 'Aktif' : ($webhook_state === 'nonaktif' ? 'Belum Terpasang' : 'Tidak diketahui'); ?>
            </span>
          </h3>
        </div>
        <div class="card-body" style="flex:1 1 auto; display:flex; flex-direction:column;">
          <?php
          $wh_label = $webhook_state === 'aktif' ? 'Webhook aktif' : ($webhook_state === 'nonaktif' ? 'Webhook belum terpasang' : 'Status webhook tidak diketahui');
          $wh_col   = $webhook_state === 'aktif' ? 'text-green' : ($webhook_state === 'nonaktif' ? 'text-red' : 'text-yellow');
          $wh_icon  = $webhook_state === 'aktif' ? 'fa-check-circle' : ($webhook_state === 'nonaktif' ? 'fa-times-circle' : 'fa-question-circle');
          ?>
          <!-- Strip status webhook; memakai gaya .iso-tile dari kartu Mesin Isolir -->
          <div class="iso-tile" style="margin-bottom:10px;">
            <i class="fa <?= $wh_icon; ?> <?= $wh_col; ?>"></i>
            <div style="min-width:0;">
              <div class="iso-tile-num" style="font-size:14px;"><?= $wh_label; ?></div>
              <div class="iso-tile-label">
                Pending update: <strong><?= (int) $webhook_pending; ?></strong>
                <?php if ($webhook_checked_at !== ''): ?>
                  &nbsp;&bull;&nbsp; diperiksa <?= htmlspecialchars($webhook_checked_at); ?> (watchdog tiap 5 menit)
                <?php endif; ?>
              </div>
            </div>
          </div>

          <form method="POST" action="./?billing=overview&session=<?= $session; ?>">
            <div class="pd-b-5">
              <label class="label-block" for="_webhook_url">URL Webhook</label>
              <input class="form-control" id="_webhook_url" type="text" name="webhook_url" value="<?= htmlspecialchars($suggested_webhook); ?>">
              <small class="hint-text">Harus domain publik ber-HTTPS (port-forward, reverse proxy, atau tunnel seperti Cloudflare). Telegram tidak bisa menjangkau domain lokal.</small>
            </div>

            <?php if ($webhook_note !== ''): ?>
              <div class="hint-text" style="margin-top:4px; color:#f86c6b;">
                <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($webhook_note); ?>
              </div>
            <?php endif; ?>

            <div style="margin-top:7px; display:flex; flex-wrap:wrap; gap:6px;">
              <button type="submit" name="set_webhook" class="btn bg-primary radius-3 pd-5"><i class="fa fa-plug"></i> Pasang</button>
              <button type="submit" name="check_webhook" class="btn bg-secondary radius-3 pd-5"><i class="fa fa-search"></i> Cek Status</button>
              <button type="submit" name="sync_commands" class="btn bg-info radius-3 pd-5"><i class="fa fa-list"></i> Sinkron Perintah</button>
              <button type="submit" name="delete_webhook" class="btn bg-danger radius-3 pd-5" onclick="return confirm('Yakin ingin mencabut Webhook?')"><i class="fa fa-trash"></i> Cabut</button>
            </div>
          </form>

          <details class="bot-group" style="margin-top:auto; padding-top:0;">
            <summary style="margin-top:2px;"><i class="fa fa-caret-right bot-caret"></i><i class="fa fa-terminal text-info"></i> Perintah Bot</summary>
            <ul class="wh-cmds pd-0" style="list-style:none; margin:4px 0 8px; padding:0; font-size:13px; line-height:2;">
              <li><code>/help</code> <span>Panduan lengkap bot</span></li>
              <li><code>/pptagihan</code> <span>Ringkasan tagihan &amp; tombol aksi inline</span></li>
              <li><code>/pplunas &lt;user&gt;</code> <span>Tandai lunas &amp; pulihkan profil</span></li>
              <li><code>/ppisolir &lt;user&gt;</code> <span>Isolir pelanggan menunggak</span></li>
              <li><code>/ppcek &lt;user&gt;</code> <span>Cek koneksi &amp; status tagihan</span></li>
              <li><code>/reminder [tenggang|isolir]</code> <span>Susun pesan reminder per pelanggan</span></li>
              <li><code>/hscek &lt;kode&gt;</code> <span>Detail voucher &amp; tombol Kick/Reset/Hapus</span></li>
              <li><code>/hslist [profil]</code> <span>Rekap voucher per profil</span></li>
              <li><code>/hsonline</code> <span>Daftar sesi hotspot aktif + tombol Kick</span></li>
              <li><code>/hsgen [profil] [jml]</code> <span>Generate voucher (owner)</span></li>
            </ul>
            <small class="hint-text">
              <i class="fa fa-bolt"></i> Aksi dari Telegram langsung ter-push <em>live</em> ke tabel PPPoE tanpa refresh.
            </small>
          </details>
        </div>
      </div>

      <!-- Mesin Isolir & Portal (row 2 kolom kanan): mengisi ruang kosong di bawah Webhook.
           flex:1 membuat kartu melar mengikuti tinggi kolom kiri (Pengaturan Bot). -->
      <?php
      $iso_badge = array(
        'fresh'  => array('bg-success', 'Berjalan', 'fa-play-circle'),
        'stale'  => array('bg-warning', 'Tidak Fresh', 'fa-hourglass-half'),
        'old'    => array('bg-danger', 'Tidak Berjalan', 'fa-exclamation-circle'),
        'never'  => array('bg-secondary', 'Belum Pernah', 'fa-moon-o'),
        'error'  => array('bg-danger', 'Error', 'fa-exclamation-triangle'),
      );
      $iso_applied = $isolir_cache['isolir_applied'] ?? array();
      $iso_restored = $isolir_cache['restored'] ?? array();
      $iso_errors = $isolir_cache['errors'] ?? array();
      // Prasyarat HTTPS hanya relevan bila portal.domain diisi (NAT 443 + DNS statis).
      $iso_pre_items = array(
        array($iso_pool_ok, 'Pool <code>' . htmlspecialchars($iso_pool) . '</code>', 'ada', 'belum ada di router', 'red'),
        array($iso_profile_ok, 'Profil isolir menunjuk pool', 'benar', 'belum (masih pool lama)', 'yellow'),
        array($iso_nat_ok, 'NAT redirect <code>isolir-portal</code>', 'terpasang', 'belum ada', 'red'),
        array($iso_dnsnat_ok, 'Redirect DNS <code>isolir-dns</code>', 'terpasang', 'belum ada', 'yellow'),
      );
      if ($iso_domain !== '') {
        $iso_pre_items[] = array($iso_https_ok, 'NAT HTTPS <code>isolir-https</code>', 'terpasang', 'belum ada (portal https)', 'yellow');
        $iso_pre_items[] = array($iso_dnsstatic_ok, 'DNS statis <code>' . htmlspecialchars($iso_domain) . '</code>', 'ada', 'belum ada', 'yellow');
      }
      $iso_pre_count = 0;
      $iso_pre_all = true;
      foreach ($iso_pre_items as $row) { $iso_pre_count += (int)$row[0]; if (!$row[0]) $iso_pre_all = false; }
      ?>
      <div class="card" style="margin-top:12px; flex:1 1 auto; display:flex; flex-direction:column;">
        <div class="card-header">
          <h3><i class="fa fa-ban"></i> Mesin Isolir &amp; Portal
            <span class="badge <?= $iso_badge[$iso_state][0]; ?> radius-3" style="font-size:12px; margin-left:10px;">
              <i class="fa <?= $iso_badge[$iso_state][2]; ?>"></i> <?= $iso_badge[$iso_state][1]; ?>
              <?php if ($iso_state !== 'never'): ?>
                &nbsp;<?= htmlspecialchars($isolir_cache['last_run_h'] ?? ''); ?>
                (<?= $iso_age < 60 ? $iso_age . 's' : round($iso_age / 60) . 'm'; ?> lalu)
              <?php endif; ?>
            </span>
          </h3>
        </div>
        <div class="card-body" style="flex:1 1 auto;">
          <style>
            /* Mesin Isolir: tile ringkas 2x2 (kolom kanan hanya setengah lebar halaman)
               + daftar prasyarat dua kolom. Warna abu transparan supaya aman di
               tema light maupun dark. */
            .iso-tiles { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .iso-tile { display: flex; align-items: center; gap: 10px; padding: 10px 12px;
                        border: 1px solid rgba(128,128,128,.25); border-radius: 6px;
                        background: rgba(128,128,128,.06); min-width: 0; }
            .iso-tile > i { font-size: 20px; width: 26px; text-align: center; flex: none; }
            .iso-tile-num { font-size: 18px; font-weight: 700; line-height: 1.15;
                            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .iso-tile-label { font-size: 12px; opacity: .75; line-height: 1.5;
                              white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .iso-checks { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0 18px; font-size: 14px; line-height: 2; }
            @media (max-width: 750px) {
              .iso-checks { grid-template-columns: 1fr; }
            }
          </style>

          <?php if ($iso_state === 'never'): ?>
            <div style="display:flex; align-items:flex-start; gap:12px; padding:10px 12px; border:1px dashed rgba(128,128,128,.35); border-radius:6px;">
              <i class="fa fa-power-off text-grey" style="font-size:22px; margin-top:2px;"></i>
              <div style="font-size:13px; line-height:1.7;">
                <strong>Mesin belum pernah berjalan.</strong> Pasang crontab di server (interval 15 menit):<br>
                <code>*/15 * * * * docker exec mikhmon php /var/www/cron/isolir-cron.php</code>
              </div>
            </div>
          <?php else: ?>
            <div class="iso-tiles">
              <div class="iso-tile" title="Pelanggan PPP dipindai pada run terakhir">
                <i class="fa fa-users text-info"></i>
                <div style="min-width:0;">
                  <div class="iso-tile-num"><?= (int)($isolir_cache['scanned'] ?? 0); ?></div>
                  <div class="iso-tile-label">Dipindai</div>
                </div>
              </div>
              <div class="iso-tile" title="<?= htmlspecialchars(implode(', ', $iso_applied)); ?>">
                <i class="fa fa-ban <?= $iso_applied ? 'text-red' : 'text-grey'; ?>"></i>
                <div style="min-width:0;">
                  <div class="iso-tile-num"><?= count($iso_applied); ?></div>
                  <div class="iso-tile-label"><?= $iso_applied
                    ? 'Isolir: ' . htmlspecialchars(implode(', ', array_slice($iso_applied, 0, 3))) . (count($iso_applied) > 3 ? ' +' . (count($iso_applied) - 3) : '')
                    : 'Tidak ada isolir baru'; ?></div>
                </div>
              </div>
              <div class="iso-tile" title="<?= htmlspecialchars(implode(', ', $iso_restored)); ?>">
                <i class="fa fa-check-circle <?= $iso_restored ? 'text-green' : 'text-grey'; ?>"></i>
                <div style="min-width:0;">
                  <div class="iso-tile-num"><?= count($iso_restored); ?></div>
                  <div class="iso-tile-label"><?= $iso_restored
                    ? 'Pulih: ' . htmlspecialchars(implode(', ', array_slice($iso_restored, 0, 3))) . (count($iso_restored) > 3 ? ' +' . (count($iso_restored) - 3) : '')
                    : 'Tidak ada pemulihan'; ?></div>
                </div>
              </div>
              <div class="iso-tile" title="Prasyarat router portal isolir">
                <i class="fa <?= $iso_pre_all ? 'fa-cogs text-green' : 'fa-cogs text-yellow'; ?>"></i>
                <div style="min-width:0;">
                  <div class="iso-tile-num"><?= $iso_pre_count; ?>/<?= count($iso_pre_items); ?></div>
                  <div class="iso-tile-label"><?= $iso_pre_all ? 'Prasyarat lengkap' : 'Prasyarat router belum lengkap'; ?></div>
                </div>
              </div>
            </div>
            <?php if (!empty($iso_errors)): ?>
            <div class="text-red" style="margin-top:8px; font-size:12px; line-height:1.6;">
              <i class="fa fa-exclamation-triangle"></i>
              <?= htmlspecialchars(implode('; ', array_slice($iso_errors, 0, 3))); ?><?= count($iso_errors) > 3 ? ' …' : ''; ?>
            </div>
            <?php endif; ?>
          <?php endif; ?>

          <div style="margin-top:10px; padding-top:8px; border-top:1px dashed rgba(128,128,128,.35);">
            <div style="font-size:12px; letter-spacing:.6px; opacity:.65; margin-bottom:2px;">PRASYARAT ROUTER</div>
            <div class="iso-checks">
              <?php foreach ($iso_pre_items as $row): list($ok, $label, $okTxt, $badTxt, $badCol) = $row; ?>
              <div>
                <i class="fa <?= $ok ? 'fa-check-circle text-green' : 'fa-times-circle text-' . $badCol; ?>"></i>
                <?= $label ?> <?= $ok ? $okTxt : $badTxt; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <small class="hint-text"><i class="fa fa-wrench"></i> Prasyarat dipasang oleh <code>app/cron/isolir-migrate.php</code> (--check lalu --apply).</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Riwayat Aksi -->
  <div class="card" style="margin-top:12px;">
    <div class="card-header">
      <h3><i class="fa fa-history"></i> Riwayat Aksi Tagihan
        <span class="badge bg-secondary radius-3" style="font-size:12px; margin-left:10px;"><?= count($events); ?> entri terakhir</span>
      </h3>
    </div>
    <div class="card-body" style="padding:0;">
      <!-- Chip filter sisi klien: hanya menyembunyikan baris, tidak memuat ulang halaman. -->
      <div id="histFilters" style="display:flex; flex-wrap:wrap; gap:6px; padding:10px 10px 0;">
        <button type="button" class="btn bg-primary radius-3 pd-5" style="font-size:12px;" onclick="histFilter('all', this)"><i class="fa fa-list"></i> Semua</button>
        <button type="button" class="btn bg-secondary radius-3 pd-5" style="font-size:12px;" onclick="histFilter('lunas', this)"><i class="fa fa-check-circle text-green"></i> Lunas</button>
        <button type="button" class="btn bg-secondary radius-3 pd-5" style="font-size:12px;" onclick="histFilter('isolir', this)"><i class="fa fa-ban text-red"></i> Isolir</button>
        <button type="button" class="btn bg-secondary radius-3 pd-5" style="font-size:12px;" onclick="histFilter('other', this)"><i class="fa fa-bolt text-yellow"></i> Lainnya</button>
      </div>
      <div class="overflow" style="max-height: 42vh; overflow: auto; margin-top:8px;">
        <table id="histTable" class="table table-bordered table-hover" style="margin:0; width:100%;">
          <style>
            /* table-layout:fixed supaya persentase kolom benar-benar dipakai;
               tanpa ini browser memberi hampir semua sisa lebar ke kolom Pelanggan. */
            #histTable { table-layout: fixed; }
            /* Di layar sempit 5 kolom tidak bisa muat: beri lebar minimum dan
               biarkan kontainer tabel yang scroll horizontal, bukan halaman. */
            @media (max-width: 750px) { #histTable { min-width: 640px; } }
            #histTable th { padding: 9px 10px; text-align: left; }
            #histTable td { padding: 8px 10px; vertical-align: middle; word-break: break-word; }
          </style>
          <thead>
            <tr>
              <th style="width:16%;">Waktu</th>
              <th style="width:14%;">Aktor</th>
              <th style="width:10%;" class="text-center">Aksi</th>
              <th style="width:28%;">Pelanggan</th>
              <th style="width:32%;">Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $hist_badge = array('lunas' => array('success', 'fa-check'), 'isolir' => array('danger', 'fa-ban'));
            if (empty($events)) {
                echo "<tr><td colspan='5' class='text-center text-grey pd-10'>"
                   . "<i class='fa fa-inbox' style='font-size:20px; display:block; margin-bottom:4px; opacity:.5;'></i>"
                   . "Belum ada riwayat aktivitas.</td></tr>";
            } else {
                foreach ($events as $ev) {
                    $b = $hist_badge[$ev['action']] ?? array('info', 'fa-bolt');
                    echo "<tr data-action='" . htmlspecialchars($ev['action']) . "'>";
                    echo "<td><span class='text-grey'>" . htmlspecialchars($ev['ts']) . "</span></td>";
                    echo "<td><span>" . htmlspecialchars($ev['tg_id']) . "</span></td>";
                    echo "<td class='text-center'><span class='badge bg-{$b[0]} pd-2 radius-3' style='font-size:12px;'>"
                       . "<i class='fa {$b[1]}'></i> " . strtoupper(htmlspecialchars($ev['action'])) . "</span></td>";
                    echo "<td>" . htmlspecialchars($ev['target']) . "</td>";
                    echo "<td><span class='text-grey' title='" . htmlspecialchars($ev['detail']) . "'>" . htmlspecialchars($ev['detail']) . "</span></td>";
                    echo "</tr>";
                }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
      function histFilter(act, btn) {
        document.querySelectorAll('#histTable tbody tr[data-action]').forEach(function (tr) {
          var a = tr.getAttribute('data-action');
          var show = (act === 'all') || (a === act) || (act === 'other' && a !== 'lunas' && a !== 'isolir');
          tr.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('#histFilters .btn').forEach(function (b) {
          b.classList.remove('bg-primary');
          b.classList.add('bg-secondary');
        });
        btn.classList.remove('bg-secondary');
        btn.classList.add('bg-primary');
      }
    </script>
  </div>

</div>
</div>
</div>
</div>
