<?php
/*
 * Setup data & penanganan aksi POST halaman Billing & Bot Telegram.
 *
 * File ini di-include dari dua tempat (include_once, jadi hanya jalan sekali):
 *   1. app/index.php — SEBELUM headhtml.php/menu.php, khusus saat POST.
 *      Redirect PRG (Post/Redirect/Get) hanya bisa mengirim header Location
 *      sebelum ada output; kalau dicoba setelah menu/sidebar ter-render,
 *      header() gagal ("headers already sent") dan browser hanya menerima
 *      halaman terpotong (tampilan blank).
 *   2. billing/index.php — untuk load halaman biasa (GET).
 */

session_start(); // buka lagi setelah session_write_close() di app/index.php

require_once dirname(__DIR__) . '/lib/billing.class.php';
require_once dirname(__DIR__) . '/lib/telegram_bot.class.php';
require_once dirname(__DIR__) . '/lib/hotspot_billing.class.php';

$bot = new MikhmonTelegramBot();
$config = MikhmonBilling::getConfig();
$msg_alert = '';
$alert_type = 'info';

// Daftar profil PPPoE diambil langsung dari router untuk dropdown "Profil Isolir".
// Kalau router tidak terjangkau daftarnya kosong dan form kembali ke input teks,
// supaya halaman tetap bisa dibuka saat router sedang down.
$ppp_profiles = array();
$ppp_profile_rates = array();
if (!empty($API->connected)) {
    $profile_res = $API->comm("/ppp/profile/print", array(".proplist" => "name,rate-limit"));
    if (is_array($profile_res)) {
        foreach ($profile_res as $profile_row) {
            $profile_name = $profile_row['name'] ?? '';
            if ($profile_name !== '') {
                $ppp_profiles[] = $profile_name;
                $ppp_profile_rates[$profile_name] = $profile_row['rate-limit'] ?? '';
            }
        }
    }
}
sort($ppp_profiles, SORT_NATURAL | SORT_FLAG_CASE);

// Profil voucher hotspot (modul Telegram /hs*). Sama seperti profil PPPoE:
// router tidak terjangkau -> daftar kosong, form kembali ke input teks.
$hs_cfg = MikhmonHotspotBilling::getHotspotConfig($config);
$hs_profiles = array();
if (!empty($API->connected)) {
    $hs_profiles = MikhmonHotspotBilling::listProfiles($API);
}
sort($hs_profiles, SORT_NATURAL | SORT_FLAG_CASE);

// Process Actions
if (isset($_POST['save_bot_config']) || ($_POST['action_type'] ?? '') === 'save_config') {
    $token = trim($_POST['bot_token'] ?? '');
    $secret = trim($_POST['webhook_secret'] ?? '');
    $isolir_prof = trim($_POST['isolir_profile'] ?? 'pppoe-ISOLIR');
    // Profil yang tidak ada di router akan ditolak RouterOS saat isolir dijalankan,
    // jadi simpan nilai lama dan beri peringatan alih-alih menyimpan nama yang salah.
    $isolir_prof_rejected = !empty($ppp_profiles) && !in_array($isolir_prof, $ppp_profiles, true);
    if ($isolir_prof_rejected) {
        $rejected_prof_name = $isolir_prof;
        $isolir_prof = $config['isolir_profile'] ?? 'pppoe-ISOLIR';
    }
    $cutoff = (int)($_POST['cutoff_day'] ?? 10);
    $scope = $_POST['group_scope'] ?? 'tagih';

    $owners_raw = explode(',', $_POST['owner_ids'] ?? '');
    $owner_ids = array_values(array_filter(array_map('trim', $owners_raw)));

    $groups_raw = explode(',', $_POST['group_ids'] ?? '');
    $group_ids = array_values(array_filter(array_map('trim', $groups_raw)));

    $notify_raw = explode(',', $_POST['notify_ids'] ?? '');
    $notify_ids = array_values(array_filter(array_map('trim', $notify_raw)));

    $config['token'] = $token;
    $config['webhook_secret'] = $secret;
    $config['isolir_profile'] = $isolir_prof;
    $config['cutoff_day'] = $cutoff;
    $config['group_scope'] = $scope;
    $config['owner_ids'] = $owner_ids;
    $config['group_ids'] = $group_ids;
    $config['notify_chat_ids'] = $notify_ids;

    // Seksi hotspot: merge agar kunci lain (mis. notify_chat_ids) tidak hilang.
    $hs_cfg = $config['hotspot'] ?? array();
    $hs_cfg['enabled'] = isset($_POST['hs_enabled']);
    $hs_default_prof = trim($_POST['hs_default_profile'] ?? '');
    // Profil yang tidak ada di router akan ditolak RouterOS saat /hsgen dijalankan.
    $hs_default_rejected = $hs_default_prof !== '' && !empty($hs_profiles) && !in_array($hs_default_prof, $hs_profiles, true);
    if ($hs_default_rejected) {
        $hs_default_prof = $hs_cfg['default_profile'] ?? '';
    }
    $hs_cfg['default_profile'] = $hs_default_prof;
    $hs_cfg['gen_prefix'] = trim($_POST['hs_gen_prefix'] ?? '');
    $hs_cfg['batch_max'] = max(1, (int)($_POST['hs_batch_max'] ?? 50));
    $config['hotspot'] = $hs_cfg;

    // Seksi portal isolir: merge agar kunci lain tidak hilang.
    $portal_cfg = $config['portal'] ?? array();
    $portal_cfg['contact'] = trim($_POST['portal_contact'] ?? '');
    $portal_cfg['phone'] = trim($_POST['portal_phone'] ?? '');
    $portal_cfg['message'] = trim($_POST['portal_message'] ?? '');
    $portal_cfg['network'] = trim($_POST['portal_network'] ?? '') !== '' ? trim($_POST['portal_network'] ?? '') : '192.168.6.0/24';
    $portal_cfg['pool'] = trim($_POST['portal_pool'] ?? '') !== '' ? trim($_POST['portal_pool'] ?? '') : 'pppoe_pool_isolir';
    $portal_cfg['server_ip'] = trim($_POST['portal_server_ip'] ?? '');
    $portal_cfg['server_mac'] = trim($_POST['portal_server_mac'] ?? '');
    $portal_cfg['server_port'] = trim($_POST['portal_server_port'] ?? '') !== '' ? trim($_POST['portal_server_port'] ?? '') : '8081';
    $portal_cfg['domain'] = trim($_POST['portal_domain'] ?? '');
    $portal_cfg['tagline'] = trim($_POST['portal_tagline'] ?? '') !== '' ? trim($_POST['portal_tagline'] ?? '') : 'Layanan wifi termurah';
    $config['portal'] = $portal_cfg;
    // $portal_cfg ini juga dipakai panel prasyarat di bawah, jangan timpa lagi.

    $saved = MikhmonBilling::saveConfig($config);
    if ($saved !== false) {
        $config = MikhmonBilling::getConfig();
        $msg_alert = "Konfigurasi Bot Telegram berhasil disimpan!";
        $alert_type = "success";
        if (!empty($isolir_prof_rejected)) {
            $msg_alert .= "\n\nProfil Isolir '" . $rejected_prof_name . "' tidak ada di router, jadi nilai lama '"
                . $config['isolir_profile'] . "' yang dipakai. Pilih salah satu profil dari daftar.";
            $alert_type = "warning";
        }
        if (!empty($hs_default_rejected)) {
            $msg_alert .= "\n\nProfil default hotspot '" . ($_POST['hs_default_profile'] ?? '') . "' tidak ada di router, jadi nilai lama '"
                . $config['hotspot']['default_profile'] . "' yang dipakai.";
            $alert_type = "warning";
        }
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
            // Simpan juga ke config agar watchdog (webhook-watch.py) tahu URL yang diharapkan.
            $config['webhook_url'] = $webhook_url;
            MikhmonBilling::saveConfig($config);
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
} elseif (isset($_POST['sync_commands'])) {
    $res = $bot->syncCommands();
    if ($res['ok'] ?? false) {
        $msg_alert = "Daftar perintah berhasil didaftarkan ke Telegram. Buka chat bot dan ketik / untuk melihat palet perintah terbaru.";
        $alert_type = "success";
    } else {
        $msg_alert = "Gagal mendaftarkan perintah: " . htmlspecialchars($res['description'] ?? 'Error');
        $alert_type = "danger";
    }
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

// PRG (Post/Redirect/Get): halaman tidak boleh di-render sebagai respons POST,
// kalau tidak browser memunculkan dialog "Confirm Form Resubmission" tiap reload.
// Posisi blok ini harus sebelum output apa pun (lihat komentar di atas file).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['billing_flash'] = array('msg' => $msg_alert, 'type' => $alert_type);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_SESSION['billing_flash'])) {
    $msg_alert = $_SESSION['billing_flash']['msg'];
    $alert_type = $_SESSION['billing_flash']['type'];
    unset($_SESSION['billing_flash']);
}
// Lepas lock session lagi (pola app/index.php) supaya render halaman yang lambat
// tidak memblokir tab lain dari login yang sama.
session_write_close();
