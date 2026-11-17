<?php
/*
 * Hotspot voucher commands for MikhmonTelegramBot (/hs*).
 * Implemented as a trait to keep telegram_bot.class.php small.
 * All methods run inside the bot class, so private members are accessible.
 */

require_once __DIR__ . '/hotspot_billing.class.php';

trait TelegramHotspotCommands
{
    /* ==========================================================
     * Command Handlers (/hs*)
     * ========================================================== */

    private function cmdHsHelp($chat_id, $tg_id)
    {
        if (!$this->hsEnabled()) {
            $this->sendMessage($chat_id, "⚠️ Modul hotspot sedang dinonaktifkan di konfigurasi bot.");
            return;
        }
        if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin.");
            return;
        }

        $html = "<b>🎫 Bot Voucher Hotspot</b>\n\n";
        $html .= "<i>Semua perintah tanpa argumen menampilkan menu tombol.</i>\n\n";
        $html .= "<b>Lihat:</b>\n";
        $html .= "• /hscek <code>[kode]</code> — Detail voucher\n";
        $html .= "• /hsonline — Sesi aktif + tombol putus sesi\n";
        $html .= "• /hslist <code>[profil]</code> — Rekap voucher per profil\n";
        $html .= "<b>Aksi:</b>\n";
        $html .= "• /hskick <code>[kode]</code> — Putus sesi aktif\n";
        $html .= "• /hsreset <code>[kode]</code> — Reset counter pemakaian\n";
        $html .= "<b>Owner:</b>\n";
        $html .= "• /hsgen — Generate voucher (pilih profil → jumlah)\n";
        $html .= "• /hsdel <code>[kode]</code> — Hapus voucher (konfirmasi)\n";

        $this->sendMessage($chat_id, $html);
    }

    private function cmdHsCek($chat_id, $tg_id, $kode, $msg_id = null)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin.");
            return;
        }
        if (empty($kode)) {
            // Menu interaktif: pilih profil lalu voucher — tanpa ketik manual.
            $this->cmdHsList($chat_id, $tg_id, '', $msg_id, 'hscek');
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        list($user, $error) = MikhmonHotspotBilling::resolveUser($api, $kode);
        if ($user === null) {
            $api->disconnect();
            $this->sendMessage($chat_id, htmlspecialchars($error));
            return;
        }

        $actives = $api->comm('/ip/hotspot/active/print', array('?user' => $user['name']));
        $active = (is_array($actives) && !empty($actives[0])) ? $actives[0] : null;
        $api->disconnect();

        $this->hsRenderDetail($chat_id, $msg_id, $tg_id, $user, $active);
    }

    private function cmdHsOnline($chat_id, $tg_id, $msg_id = null)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin.");
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $actives = $api->comm('/ip/hotspot/active/print');
        $api->disconnect();
        $actives = is_array($actives) ? $actives : array();

        $html = "🟢 <b>Sesi Hotspot Aktif (" . count($actives) . ")</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━\n";
        $kick_rows = array();
        if (empty($actives)) {
            $html .= "<i>Tidak ada sesi aktif.</i>";
        } else {
            $shown = 0;
            foreach ($actives as $a) {
                if ($shown >= 30) {
                    $html .= "<i>... dan " . (count($actives) - $shown) . " sesi lainnya</i>\n";
                    break;
                }
                $u = htmlspecialchars($a['user'] ?? '-');
                $ip = htmlspecialchars($a['address'] ?? '-');
                $up = htmlspecialchars($a['uptime'] ?? '-');
                $html .= "• <code>{$u}</code> — {$ip} ({$up})\n";
                if (($a['user'] ?? '') !== '') {
                    $kick_rows[] = array('text' => '🔌 ' . $u, 'callback_data' => 'hskick:' . $a['user']);
                }
                $shown++;
            }
        }

        $keyboard = array_chunk($kick_rows, 2);
        $keyboard[] = array(
            array('text' => '🔄 Refresh', 'callback_data' => 'hsonline')
        );

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $keyboard));
        } else {
            $this->sendMessage($chat_id, $html, array('inline_keyboard' => $keyboard));
        }
    }

    private function cmdHsList($chat_id, $tg_id, $profile, $msg_id = null, $mode = 'hscek')
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin.");
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $sum = MikhmonHotspotBilling::summarize($api, $profile);
        $profiles = $sum['profiles'];
        $api->disconnect();

        // No profile given: overview + per-profile buttons. Also reached via
        // callback (🔙 Semua profil / 🔙 Daftar voucher), so it must render
        // regardless of $msg_id and edit the message in place.
        if (empty($profile)) {
            $c = $sum['counts'];
            $html = "📊 <b>Rekap Voucher Hotspot</b>\n";
            $html .= "Total: <b>" . count($sum['users']) . "</b>\n";
            $html .= "⚪ Belum terpakai : <b>{$c['unused']}</b>\n";
            $html .= "🟢 Aktif online   : <b>{$c['active']}</b>\n";
            $html .= "🔵 Tidak online   : <b>{$c['idle']}</b>\n";
            $html .= "🔴 Habis          : <b>{$c['exhausted']}</b>\n";
            if ($c['expired'] > 0) $html .= "⌛ Kedaluwarsa    : <b>{$c['expired']}</b>\n";
            if ($c['disabled'] > 0) $html .= "⛔ Nonaktif       : <b>{$c['disabled']}</b>\n";

            $keyboard = array();
            $row = array();
            foreach (array_slice($profiles, 0, 12) as $p) {
                $row[] = array('text' => $p, 'callback_data' => 'hslist:' . $p);
                if (count($row) === 2) {
                    $keyboard[] = $row;
                    $row = array();
                }
            }
            if (!empty($row)) $keyboard[] = $row;
            $keyboard[] = array(array('text' => '🔄 Refresh', 'callback_data' => 'hslist:overview'));

            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $keyboard));
            } else {
                $this->sendMessage($chat_id, $html, array('inline_keyboard' => $keyboard));
            }
            return;
        }

        // Profile recap.
        $c = $sum['counts'];
        $label = empty($profile) ? 'Semua Profil' : htmlspecialchars($profile);
        $html = "📊 <b>Rekap Voucher — {$label}</b>\n";
        $html .= "Total: <b>" . count($sum['users']) . "</b>\n";
        $html .= "⚪ Belum terpakai : <b>{$c['unused']}</b>\n";
        $html .= "🟢 Aktif online   : <b>{$c['active']}</b>\n";
        $html .= "🔵 Tidak online   : <b>{$c['idle']}</b>\n";
        $html .= "🔴 Habis          : <b>{$c['exhausted']}</b>\n";
        if ($c['expired'] > 0) $html .= "⌛ Kedaluwarsa    : <b>{$c['expired']}</b>\n";
        if ($c['disabled'] > 0) $html .= "⛔ Nonaktif       : <b>{$c['disabled']}</b>\n";

        $hints = array(
            'hscek' => 'Pilih voucher untuk melihat detail:',
            'hskick' => 'Pilih voucher untuk diputus sesinya:',
            'hsreset' => 'Pilih voucher untuk reset counter:',
            'hsdel' => 'Pilih voucher untuk dihapus:'
        );
        $html .= "\n<i>" . ($hints[$mode] ?? 'Pilih voucher:') . "</i>";

        // Buttons open the requested action for each voucher (cek/kick/reset/del).
        // For hsdel everything is listed; for the other modes skip dead vouchers.
        $keyboard = array();
        $row = array();
        $shown = 0;
        foreach ($sum['users'] as $u) {
            if ($shown >= 20) break;
            if ($mode !== 'hsdel' && in_array($u['status']['code'], array('exhausted', 'expired', 'disabled'), true)) continue;
            $row[] = array('text' => $u['name'], 'callback_data' => $mode . ':' . $u['name']);
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = array();
            }
            $shown++;
        }
        if (!empty($row)) $keyboard[] = $row;
        $keyboard[] = array(array('text' => '🔙 Semua profil', 'callback_data' => 'hslist:overview'));

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $keyboard));
        } else {
            $this->sendMessage($chat_id, $html, array('inline_keyboard' => $keyboard));
        }
    }

    private function cmdHsKick($chat_id, $tg_id, $kode, $sender_name, $msg_id = null)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'aksi')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin untuk memutus sesi.");
            return;
        }
        if (empty($kode)) {
            // Menu interaktif: pilih dari sesi yang sedang online.
            $this->hsShowKickPicker($chat_id, $msg_id);
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        list($user, $error) = MikhmonHotspotBilling::resolveUser($api, $kode);
        if ($user === null) {
            $api->disconnect();
            $this->sendMessage($chat_id, htmlspecialchars($error));
            return;
        }

        $n = MikhmonHotspotBilling::killSession($api, $user['name']);
        MikhmonHotspotBilling::logEvent($tg_id, 'hs_kick', $user['name'], "by @{$sender_name}");
        MikhmonHotspotBilling::emitLiveEvent('hs_kick', $user['name'], "putus sesi oleh @{$sender_name}");
        $api->disconnect();

        if ($n > 0) {
            $html = "🔌 <b>{$user['name']}</b> — {$n} sesi diputus oleh @{$sender_name}.";
        } else {
            $html = "⚪ <b>{$user['name']}</b> — tidak ada sesi aktif.";
        }

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html);
        } else {
            $this->sendMessage($chat_id, $html);
        }
    }

    private function cmdHsReset($chat_id, $tg_id, $kode, $sender_name, $msg_id = null)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'aksi')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin untuk reset counter.");
            return;
        }
        if (empty($kode)) {
            // Menu interaktif: pilih voucher — tanpa ketik manual.
            $this->cmdHsList($chat_id, $tg_id, '', $msg_id, 'hsreset');
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        list($user, $error) = MikhmonHotspotBilling::resolveUser($api, $kode);
        if ($user === null) {
            $api->disconnect();
            $this->sendMessage($chat_id, htmlspecialchars($error));
            return;
        }

        MikhmonHotspotBilling::resetCounters($api, $user['.id']);
        MikhmonHotspotBilling::logEvent($tg_id, 'hs_reset', $user['name'], "by @{$sender_name}");
        $api->disconnect();

        $html = "♻️ <b>{$user['name']}</b> — counter pemakaian direset oleh @{$sender_name}.";
        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html);
        } else {
            $this->sendMessage($chat_id, $html);
        }
    }

    private function cmdHsGen($chat_id, $tg_id, $args, $sender_name, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /hsgen hanya untuk owner bot.");
            return;
        }

        $hs_cfg = MikhmonHotspotBilling::getHotspotConfig($this->config);
        $profile = trim((string)($args[0] ?? ''));
        $qty = (int)($args[1] ?? 10);

        if ($profile === '') {
            // Without a profile: menu interaktif pilih profil.
            $default = $hs_cfg['default_profile'];
            $api = $this->getRouterAPI();
            if (!$api) {
                $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
                return;
            }
            $profiles = MikhmonHotspotBilling::listProfiles($api);
            $api->disconnect();
            $keyboard = array();
            $row = array();
            if ($default !== '' && in_array($default, $profiles, true)) {
                $row[] = array('text' => '⭐ ' . $default . ' (default)', 'callback_data' => 'hsgenprof:' . $default);
                $profiles = array_values(array_diff($profiles, array($default)));
            }
            foreach (array_slice($profiles, 0, 11) as $p) {
                $row[] = array('text' => $p, 'callback_data' => 'hsgenprof:' . $p);
                if (count($row) === 2) {
                    $keyboard[] = $row;
                    $row = array();
                }
            }
            if (!empty($row)) $keyboard[] = $row;
            $keyboard[] = array(array('text' => '❌ Batal', 'callback_data' => 'batal'));
            $picker_html = "🎫 <b>Generate Voucher</b> — pilih profil:";
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $picker_html, array('inline_keyboard' => $keyboard));
            } else {
                $this->sendMessage($chat_id, $picker_html, array('inline_keyboard' => $keyboard));
            }
            return;
        }

        if ($qty < 1) $qty = 1;
        if ($qty > $hs_cfg['batch_max']) $qty = $hs_cfg['batch_max'];

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $res = MikhmonHotspotBilling::generateVouchers($api, $profile, $qty, array(
            'prefix' => $hs_cfg['gen_prefix']
        ));
        $api->disconnect();

        if (!$res['success']) {
            $this->sendMessage($chat_id, "❌ " . htmlspecialchars($res['message']));
            return;
        }

        $lines = array();
        foreach ($res['vouchers'] as $v) {
            $lines[] = $v['name'];
        }

        // Large batches go out as a file to avoid flooding the chat.
        if (count($lines) > 20) {
            $this->sendDocument(
                $chat_id,
                'voucher-' . date('Ymd-His') . '.txt',
                implode("\n", $lines),
                "🎫 " . htmlspecialchars($res['message']) . "\nUser = password. Diproses oleh @{$sender_name}"
            );
            return;
        }

        $html = "🎫 <b>Voucher Hotspot Dibuat</b>\n";
        $html .= htmlspecialchars($res['message']) . "\n";
        $html .= "User = password:\n";
        $html .= "<code>" . htmlspecialchars(implode("\n", $lines)) . "</code>\n";
        $html .= "Diproses oleh @{$sender_name}";
        $this->sendMessage($chat_id, $html);
    }

    private function cmdHsDel($chat_id, $tg_id, $kode, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /hsdel hanya untuk owner bot.");
            return;
        }
        if (empty($kode)) {
            // Menu interaktif: daftar voucher (semua status) untuk dihapus.
            $this->cmdHsList($chat_id, $tg_id, '', $msg_id, 'hsdel');
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        list($user, $error) = MikhmonHotspotBilling::resolveUser($api, $kode);
        if ($user === null) {
            $api->disconnect();
            $this->sendMessage($chat_id, htmlspecialchars($error));
            return;
        }

        $stat = MikhmonHotspotBilling::getStatus($user);
        $name = htmlspecialchars($user['name']);
        $html = "⚠️ <b>Hapus voucher?</b>\n";
        $html .= "Kode    : <code>{$name}</code>\n";
        $html .= "Profil  : <code>" . htmlspecialchars($user['profile'] ?? '-') . "</code>\n";
        $html .= "Status  : {$stat['icon']} {$stat['label']}\n";
        $html .= "Tindakan ini <b>tidak dapat dibatalkan</b>.";

        // RouterOS .id (*A…) fits the 64-byte callback_data limit even for
        // long voucher names; the route resolves it back on the server.
        $btns = array(
            array(
                array('text' => '✅ Ya, hapus', 'callback_data' => 'hsdelok:' . $user['.id']),
                array('text' => '❌ Batal', 'callback_data' => 'batal')
            ),
            array(
                array('text' => '🔙 Daftar voucher', 'callback_data' => 'hslist:overview')
            )
        );

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $btns));
        } else {
            $this->sendMessage($chat_id, $html, array('inline_keyboard' => $btns));
        }
        $api->disconnect();
    }

    /* ==========================================================
     * Hotspot Callback Routes (hs…:)
     * ========================================================== */

    /**
     * Handle hs* callback data. Returns true when the data belonged here.
     */
    private function handleHotspotCallback($cb_id, $tg_id, $sender_name, $chat_id, $msg_id, $data)
    {
        if (!str_starts_with($data, 'hs')) {
            return false;
        }

        // Guard: module switched off while old buttons still float around.
        if (!$this->hsEnabled()) {
            $this->answerCallback($cb_id, '⚠️ Modul hotspot dinonaktifkan', true);
            return true;
        }

        if ($data === 'hsonline') {
            if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $this->answerCallback($cb_id, 'Memperbarui sesi...');
            $this->cmdHsOnline($chat_id, $tg_id, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'hslist:')) {
            if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $profile = substr($data, 7);
            $this->answerCallback($cb_id, 'Memuat rekap...');
            if ($profile === 'overview') {
                $this->cmdHsList($chat_id, $tg_id, '', $msg_id);
            } else {
                $this->cmdHsList($chat_id, $tg_id, $profile, $msg_id);
            }
            return true;
        }

        if (str_starts_with($data, 'hscek:')) {
            if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $kode = substr($data, 6);
            $this->answerCallback($cb_id, "Memuat {$kode}...");
            $this->cmdHsCek($chat_id, $tg_id, $kode, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'hskick:')) {
            if (!$this->isAuthorized($tg_id, $chat_id, 'aksi')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $kode = substr($data, 7);
            $this->answerCallback($cb_id, "Memutus sesi {$kode}...");
            $this->cmdHsKick($chat_id, $tg_id, $kode, $sender_name, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'hsreset:')) {
            if (!$this->isAuthorized($tg_id, $chat_id, 'aksi')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $kode = substr($data, 8);
            $this->answerCallback($cb_id, "Reset counter {$kode}...");
            $this->cmdHsReset($chat_id, $tg_id, $kode, $sender_name, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'hsdel:')) {
            $kode = substr($data, 6);
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat menghapus!', true);
                return true;
            }
            $this->answerCallback($cb_id, "Memuat {$kode}...");
            $this->cmdHsDel($chat_id, $tg_id, $kode, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'hsdelok:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat menghapus!', true);
                return true;
            }
            $uid = substr($data, 8);
            $api = $this->getRouterAPI();
            if (!$api) {
                $this->answerCallback($cb_id, '⚠️ Gagal terhubung router', true);
                return true;
            }
            // .id resolution: find the row so the reply can name the voucher.
            $rows = $api->comm('/ip/hotspot/user/print');
            $name = '';
            foreach (is_array($rows) ? $rows : array() as $r) {
                if (($r['.id'] ?? '') === $uid) {
                    $name = $r['name'] ?? '';
                    break;
                }
            }
            if ($name === '') {
                $api->disconnect();
                $this->answerCallback($cb_id, 'Voucher sudah tidak ada.', true);
                $this->editMessageText($chat_id, $msg_id, "<i>Voucher sudah tidak ada.</i>");
                return true;
            }
            MikhmonHotspotBilling::deleteUser($api, $uid);
            MikhmonHotspotBilling::logEvent($tg_id, 'hs_delete', $name, "by @{$sender_name}");
            MikhmonHotspotBilling::emitLiveEvent('hs_delete', $name, "hapus voucher oleh @{$sender_name}");
            $api->disconnect();

            $this->answerCallback($cb_id, "🗑 {$name} dihapus!");
            $this->editMessageText($chat_id, $msg_id, "🗑 Voucher <b>" . htmlspecialchars($name) . "</b> telah dihapus oleh @{$sender_name}.");
            return true;
        }

        if (str_starts_with($data, 'hsgenprof:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat generate!', true);
                return true;
            }
            $profile = substr($data, 10);
            $this->answerCallback($cb_id, "Profil {$profile} — pilih jumlah...");
            $this->hsShowQtyPicker($chat_id, $msg_id, $profile);
            return true;
        }

        if (str_starts_with($data, 'hsgenqty:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat generate!', true);
                return true;
            }
            $rest = substr($data, 9);
            $sep = strrpos($rest, ':');
            if ($sep === false) {
                $this->answerCallback($cb_id);
                return true;
            }
            $profile = substr($rest, 0, $sep);
            $qty = (int)substr($rest, $sep + 1);
            if ($qty < 1) $qty = 1;
            $this->answerCallback($cb_id, "Generate {$qty} voucher {$profile}...");
            $this->cmdHsGen($chat_id, $tg_id, array($profile, $qty), $sender_name);
            return true;
        }

        if ($data === 'hsgen') {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat generate!', true);
                return true;
            }
            $this->answerCallback($cb_id, 'Memuat daftar profil...');
            $this->cmdHsGen($chat_id, $tg_id, array(), $sender_name, $msg_id);
            return true;
        }

        if ($data === 'hskickpick') {
            if (!$this->isAuthorized($tg_id, $chat_id, 'aksi')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $this->answerCallback($cb_id, 'Memperbarui sesi...');
            $this->hsShowKickPicker($chat_id, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'hsrefresh:')) {
            if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $uid = substr($data, 10);
            $this->answerCallback($cb_id, 'Memperbarui...');
            $api = $this->getRouterAPI();
            if (!$api) {
                $this->answerCallback($cb_id, '⚠️ Gagal terhubung router', true);
                return true;
            }
            $user = $this->hsFindById($api, $uid);
            if ($user === null) {
                $api->disconnect();
                $this->editMessageText($chat_id, $msg_id, "<i>Voucher sudah tidak ada.</i>");
                return true;
            }
            $actives = $api->comm('/ip/hotspot/active/print', array('?user' => $user['name']));
            $active = (is_array($actives) && !empty($actives[0])) ? $actives[0] : null;
            $api->disconnect();
            $this->hsRenderDetail($chat_id, $msg_id, $tg_id, $user, $active);
            return true;
        }

        return false;
    }

    /* ==========================================================
     * Helpers
     * ========================================================== */

    private function hsShowKickPicker($chat_id, $msg_id = null)
    {
        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $actives = $api->comm('/ip/hotspot/active/print');
        $api->disconnect();
        $actives = is_array($actives) ? $actives : array();

        $html = "🔌 <b>Putus Sesi — pilih sesi aktif:</b>\n";
        $html .= "Total online: <b>" . count($actives) . "</b>";

        $keyboard = array();
        $row = array();
        $shown = 0;
        foreach ($actives as $a) {
            if ($shown >= 20) break;
            $u = $a['user'] ?? '';
            if ($u === '') continue;
            $row[] = array('text' => '🔌 ' . $u . ' (' . ($a['uptime'] ?? '-') . ')', 'callback_data' => 'hskick:' . $u);
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = array();
            }
            $shown++;
        }
        if (!empty($row)) $keyboard[] = $row;
        if (count($actives) > $shown) {
            $html .= "\n<i>Menampilkan {$shown} dari " . count($actives) . " sesi.</i>";
        }
        if (empty($keyboard)) {
            $html .= "\n\n<i>Tidak ada sesi aktif saat ini.</i>";
        }
        $keyboard[] = array(array('text' => '🔄 Refresh', 'callback_data' => 'hskickpick'));
        $keyboard[] = array(array('text' => '❌ Batal', 'callback_data' => 'batal'));

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $keyboard));
        } else {
            $this->sendMessage($chat_id, $html, array('inline_keyboard' => $keyboard));
        }
    }

    private function hsShowQtyPicker($chat_id, $msg_id, $profile)
    {
        $hs_cfg = MikhmonHotspotBilling::getHotspotConfig($this->config);
        $max = (int)$hs_cfg['batch_max'];

        $qtys = array(1, 5, 10, 20);
        if ($max > 20) $qtys[] = $max;

        $keyboard = array();
        $row = array();
        foreach ($qtys as $q) {
            $row[] = array('text' => '🎫 ' . $q . ' voucher', 'callback_data' => 'hsgenqty:' . $profile . ':' . $q);
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = array();
            }
        }
        if (!empty($row)) $keyboard[] = $row;
        $keyboard[] = array(array('text' => '🔙 Ganti profil', 'callback_data' => 'hsgen'));
        $keyboard[] = array(array('text' => '❌ Batal', 'callback_data' => 'batal'));

        $html = "🎫 <b>Generate Voucher</b>\n";
        $html .= "Profil: <code>" . htmlspecialchars($profile) . "</code>\n";
        $html .= "Pilih jumlah (maks {$max}):";

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $keyboard));
        } else {
            $this->sendMessage($chat_id, $html, array('inline_keyboard' => $keyboard));
        }
    }

    private function hsEnabled()
    {
        return MikhmonHotspotBilling::getHotspotConfig($this->config)['enabled'];
    }

    private function hsIsOwner($tg_id)
    {
        $owners = $this->config['owner_ids'] ?? array();
        return in_array((string)$tg_id, array_map('strval', $owners), true);
    }

    private function hsFindById($api, $id)
    {
        $rows = $api->comm('/ip/hotspot/user/print');
        foreach (is_array($rows) ? $rows : array() as $r) {
            if (($r['.id'] ?? '') === $id) {
                return $r;
            }
        }
        return null;
    }

    private function hsRenderDetail($chat_id, $msg_id, $tg_id, $user, $active)
    {
        $name = htmlspecialchars($user['name'] ?? '');
        $stat = MikhmonHotspotBilling::getStatus($user, $active);
        $comment = MikhmonHotspotBilling::parseComment($user['comment'] ?? '');

        $limitUptime = trim((string)($user['limit-uptime'] ?? ''));
        $limitBytes = (float)($user['limit-bytes-total'] ?? 0);
        $usedUptime = (string)($user['uptime'] ?? '0s');
        $usedBytes = ((float)($user['bytes-in'] ?? 0)) + ((float)($user['bytes-out'] ?? 0));

        $html = "🎫 <b>Detail Voucher Hotspot</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━\n";
        $html .= "Kode     : <code>{$name}</code>\n";
        $html .= "Status   : {$stat['icon']} <b>{$stat['label']}</b> ({$stat['hint']})\n";
        $html .= "Profil   : <code>" . htmlspecialchars($user['profile'] ?? '-') . "</code>\n";
        $html .= "Limit    : " . ($limitUptime !== '' ? htmlspecialchars($limitUptime) : '-') . " / " . ($limitBytes > 0 ? MikhmonHotspotBilling::formatBytes($limitBytes) : '-') . "\n";
        $html .= "Terpakai : " . htmlspecialchars($usedUptime) . " / " . MikhmonHotspotBilling::formatBytes($usedBytes) . "\n";
        if (!empty($comment['expiry'])) {
            $html .= "Expirasi : " . htmlspecialchars($comment['expiry']) . "\n";
        }
        if (!empty($comment['extra'])) {
            $html .= "Catatan  : " . htmlspecialchars($comment['extra']) . "\n";
        }
        if ($active !== null) {
            $html .= "Koneksi  : 🟢 <b>ONLINE</b> (IP: " . htmlspecialchars($active['address'] ?? '-') . ", uptime " . htmlspecialchars($active['uptime'] ?? '-') . ")\n";
        } else {
            $html .= "Koneksi  : ⚪ <b>OFFLINE</b>\n";
        }

        // Buttons carry the short RouterOS .id, not the voucher name, to stay
        // inside the 64-byte callback_data limit; Hapus always goes through
        // the hsdel: confirmation step first.
        $row1 = array();
        if ($active !== null) {
            $row1[] = array('text' => '🔌 Kick', 'callback_data' => 'hskick:' . $user['name']);
        }
        $row1[] = array('text' => '♻️ Reset', 'callback_data' => 'hsreset:' . $user['name']);
        $btns = array($row1);
        if ($this->hsIsOwner($tg_id)) {
            $btns[] = array(array('text' => '🗑 Hapus', 'callback_data' => 'hsdel:' . $user['name']));
        }
        $btns[] = array(array('text' => '🔄 Refresh', 'callback_data' => 'hsrefresh:' . $user['.id']));
        $btns[] = array(array('text' => '🔙 Daftar voucher', 'callback_data' => 'hslist:overview'));
        $reply_markup = array('inline_keyboard' => $btns);

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, $reply_markup);
        } else {
            $this->sendMessage($chat_id, $html, $reply_markup);
        }
    }
}
