<?php
/*
 * PPPoE customer management + messaging commands for MikhmonTelegramBot
 * (/pp*, /reminder, /broadcast, /settemplate).
 * Trait pattern, same as TelegramHotspotCommands.
 */

trait TelegramPppCommands
{
    /**
     * Bottom row for PPPoE result/menu screens: optional back to the picker
     * plus Tutup (removes the keyboard without changing the message text).
     */
    private function pppNavRow($backAction = '', $backLabel = '🔙 Kembali')
    {
        $row = array();
        if ($backAction !== '') {
            $row[] = array('text' => $backLabel, 'callback_data' => $backAction);
        }
        $row[] = array('text' => '❌ Tutup', 'callback_data' => 'tutup');
        return $row;
    }

    /* ==========================================================
     * Customer Management (/pp*)
     * Owner-only: menambah/menghapus secret memengaruhi layanan.
     * ========================================================== */

    private function cmdPpAdd($chat_id, $tg_id, $args, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /ppadd hanya untuk owner bot.");
            return;
        }

        $user = trim((string)($args[0] ?? ''));
        $profile = trim((string)($args[1] ?? ''));
        $password = trim((string)($args[2] ?? ''));

        if ($user === '') {
            $this->sendMessage($chat_id, "➕ <b>Tambah Pelanggan PPPoE</b>\n\nKetik: <code>/ppadd &lt;nama_user&gt;</code>\nSetelah itu pilih paket lewat tombol.\n\n<i>Nama user harus diketik karena baru dibuat; sisanya cukup klik.</i>", array('inline_keyboard' => array($this->pppNavRow())));
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        // Tanpa profil: pilih lewat tombol agar tidak salah ketik.
        if ($profile === '') {
            $profiles = MikhmonBilling::listPppProfiles($api);
            $api->disconnect();
            sort($profiles, SORT_NATURAL | SORT_FLAG_CASE);
            $keyboard = array();
            $row = array();
            foreach (array_slice($profiles, 0, 14) as $p) {
                $row[] = array('text' => $p, 'callback_data' => 'ppaddprof:' . $user . ':' . $p);
                if (count($row) === 2) {
                    $keyboard[] = $row;
                    $row = array();
                }
            }
            if (!empty($row)) $keyboard[] = $row;
            $keyboard[] = array(array('text' => '❌ Batal', 'callback_data' => 'batal'));
            $this->sendMessage($chat_id, "Tambah pelanggan <code>" . htmlspecialchars($user) . "</code> — pilih paket:", array('inline_keyboard' => $keyboard));
            return;
        }

        $res = MikhmonBilling::addSecret($api, $user, $profile, $password);
        $api->disconnect();

        if ($res['success']) {
            $html = "✅ <b>" . htmlspecialchars($user) . "</b> — pelanggan PPPoE ditambahkan\n";
            $html .= "Paket    : <code>" . htmlspecialchars($profile) . "</code>\n";
            $html .= "Password : <code>" . htmlspecialchars($res['password']) . "</code>\n";
            $html .= "Komentar : <code>" . htmlspecialchars($res['comment']) . "</code>";
            $kb = array('inline_keyboard' => array($this->pppNavRow()));
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $kb);
            } else {
                $this->sendMessage($chat_id, $html, $kb);
            }
        } else {
            $kb = array('inline_keyboard' => array($this->pppNavRow()));
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, "❌ " . htmlspecialchars($res['message']), $kb);
            } else {
                $this->sendMessage($chat_id, "❌ " . htmlspecialchars($res['message']), $kb);
            }
        }
    }

    private function cmdPpProf($chat_id, $tg_id, $args, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /ppprof hanya untuk owner bot.");
            return;
        }

        $user = trim((string)($args[0] ?? ''));
        $profile = trim((string)($args[1] ?? ''));

        // Tanpa argumen: menu pilih pelanggan, lalu pilih paket — tanpa ketik manual.
        if ($user === '') {
            $this->showPickUser($chat_id, 'ppprofu', 'Ganti paket — pilih pelanggan:', $msg_id);
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        // Paket belum dipilih: tampilkan daftar paket sebagai tombol.
        if ($profile === '') {
            list($cust, $error) = MikhmonBilling::resolveSecret($api, $user);
            if ($cust === null) {
                $api->disconnect();
                $this->sendMessage($chat_id, htmlspecialchars($error), array('inline_keyboard' => array($this->pppNavRow('ppprofu', '🔙 Pilih pelanggan'))));
                return;
            }

            $profiles = MikhmonBilling::listPppProfiles($api);
            $api->disconnect();
            sort($profiles, SORT_NATURAL | SORT_FLAG_CASE);

            $name = htmlspecialchars($cust['name']);
            $cur = htmlspecialchars($cust['profile'] ?? '-');
            $keyboard = array();
            $row = array();
            foreach (array_slice($profiles, 0, 14) as $p) {
                $lbl = ($p === $cust['profile']) ? '✅ ' . $p : $p;
                $row[] = array('text' => $lbl, 'callback_data' => 'ppprofset:' . $cust['name'] . ':' . $p);
                if (count($row) === 2) {
                    $keyboard[] = $row;
                    $row = array();
                }
            }
            if (!empty($row)) $keyboard[] = $row;
            $keyboard[] = array(array('text' => '🔙 Pilih pelanggan', 'callback_data' => 'ppprofu'));
            $keyboard[] = array(array('text' => '❌ Batal', 'callback_data' => 'batal'));

            $html = "🔁 <b>Ganti Paket</b>\n";
            $html .= "Pelanggan : <code>{$name}</code>\n";
            $html .= "Paket kini: <code>{$cur}</code>\n";
            $html .= "Pilih paket baru:";

            $reply_markup = array('inline_keyboard' => $keyboard);
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $reply_markup);
            } else {
                $this->sendMessage($chat_id, $html, $reply_markup);
            }
            return;
        }

        $res = MikhmonBilling::setSecretProfile($api, $user, $profile);
        $api->disconnect();

        $html = ($res['success'] ? "✅ " : "❌ ") . htmlspecialchars($res['message']);
        $kb = array('inline_keyboard' => array($this->pppNavRow('ppprofu', '🔙 Pilih pelanggan')));
        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, $kb);
        } else {
            $this->sendMessage($chat_id, $html, $kb);
        }
    }

    private function cmdPpPass($chat_id, $tg_id, $args, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /pppass hanya untuk owner bot.");
            return;
        }

        $user = trim((string)($args[0] ?? ''));
        $password = trim((string)($args[1] ?? ''));
        if ($user === '') {
            $this->showPickUser($chat_id, 'pppassu', 'Reset password — pilih pelanggan:', $msg_id);
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $res = MikhmonBilling::setSecretPassword($api, $user, $password);
        $api->disconnect();

        if ($res['success']) {
            $html = "🔑 <b>" . htmlspecialchars($user) . "</b>\nPassword baru: <code>" . htmlspecialchars($res['password']) . "</code>\n" . htmlspecialchars($res['message']);
            $kb = array('inline_keyboard' => array($this->pppNavRow('pppassu', '🔙 Pilih pelanggan')));
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $kb);
            } else {
                $this->sendMessage($chat_id, $html, $kb);
            }
        } else {
            $kb = array('inline_keyboard' => array($this->pppNavRow('pppassu', '🔙 Pilih pelanggan')));
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, "❌ " . htmlspecialchars($res['message']), $kb);
            } else {
                $this->sendMessage($chat_id, "❌ " . htmlspecialchars($res['message']), $kb);
            }
        }
    }

    private function cmdPpDel($chat_id, $tg_id, $kode, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /ppdel hanya untuk owner bot.");
            return;
        }
        if (empty($kode)) {
            $this->showPickUser($chat_id, 'ppdel', 'Hapus pelanggan — pilih:', $msg_id);
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        list($cust, $error) = MikhmonBilling::resolveSecret($api, $kode);
        if ($cust === null) {
            $api->disconnect();
            $this->sendMessage($chat_id, htmlspecialchars($error), array('inline_keyboard' => array($this->pppNavRow('ppdel', '🔙 Pilih pelanggan'))));
            return;
        }

        $stat = MikhmonBilling::getStatus($cust['profile'] ?? '', $cust['comment'] ?? '');
        $name = htmlspecialchars($cust['name']);
        $html = "⚠️ <b>Hapus pelanggan PPPoE?</b>\n";
        $html .= "Nama    : <code>{$name}</code>\n";
        $html .= "Paket   : <code>" . htmlspecialchars($cust['profile'] ?? '-') . "</code>\n";
        $html .= "Status  : <b>{$stat['label']}</b>\n";
        $html .= "Sesi aktif akan diputus dan secret <b>dihapus permanen</b>.";

        $btns = array(
            array(
                array('text' => '✅ Ya, hapus', 'callback_data' => 'ppdelok:' . $cust['name']),
                array('text' => '❌ Batal', 'callback_data' => 'batal')
            ),
            array(
                array('text' => '🔙 Pilih pelanggan', 'callback_data' => 'ppdel')
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
     * Messaging: /reminder, /broadcast, /settemplate
     * ========================================================== */

    private function defaultReminderTemplates()
    {
        return array(
            'tenggang' => "Yth {nama},\n" .
                "Tagihan layanan internet {paket} ({user}) untuk bulan {bulan} belum kami terima. " .
                "Mohon lakukan pembayaran sebelum tanggal {tgl} agar layanan tidak diisolir otomatis.\n\n" .
                "Terima kasih.",
            'isolir' => "Yth {nama},\n" .
                "Layanan internet Anda ({user}) saat ini diisolir karena tagihan bulan {bulan} melewati jatuh tempo tanggal {tgl}. " .
                "Silakan lunasi tagihan untuk pemulihan layanan.\n\n" .
                "Terima kasih."
        );
    }

    private function cmdReminder($chat_id, $tg_id, $args, $msg_id = null)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin.");
            return;
        }

        $kategori = strtolower(trim((string)($args[0] ?? '')));
        if ($kategori === '') {
            // Menu pilih kategori — tanpa ketik manual.
            $keyboard = array(
                array(
                    array('text' => '🟡 Tenggang', 'callback_data' => 'remcat:tenggang'),
                    array('text' => '🔴 Isolir', 'callback_data' => 'remcat:isolir')
                ),
                array(array('text' => '❌ Batal', 'callback_data' => 'batal'))
            );
            $reply_markup = array('inline_keyboard' => $keyboard);
            $html = "📣 <b>Reminder</b> — pilih kategori pelanggan:";
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $reply_markup);
            } else {
                $this->sendMessage($chat_id, $html, $reply_markup);
            }
            return;
        }
        if (!in_array($kategori, array('tenggang', 'isolir'), true)) {
            $this->sendMessage($chat_id, "Kategori tidak dikenal. Gunakan /reminder lalu pilih tombol kategori.");
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $secrets = $api->comm('/ppp/secret/print');
        $api->disconnect();

        $bulan = MikhmonBilling::currentMonth();
        $matched = array();
        foreach (is_array($secrets) ? $secrets : array() as $s) {
            $stat = MikhmonBilling::getStatus($s['profile'] ?? '', $s['comment'] ?? '', $this->config['cutoff_day'] ?? 10);
            if ($stat['code'] === $kategori) {
                $matched[] = $s;
            }
        }

        if (empty($matched)) {
            $html = "⚪ Tidak ada pelanggan dalam kategori <b>{$kategori}</b>.";
            $kb = array('inline_keyboard' => array($this->pppNavRow('remmenu', '🔙 Kategori')));
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $kb);
            } else {
                $this->sendMessage($chat_id, $html, $kb);
            }
            return;
        }

        $tpl = MikhmonBilling::getTemplate('reminder_' . $kategori);
        if ($tpl === null || trim($tpl) === '') {
            $tpl = $this->defaultReminderTemplates()[$kategori];
        }

        $messages = array();
        foreach ($matched as $s) {
            $parsed = MikhmonBilling::parseComment($s['comment'] ?? '');
            $vars = array(
                '{nama}' => $parsed['label'] !== '' ? $parsed['label'] : ($s['name'] ?? ''),
                '{user}' => $s['name'] ?? '',
                '{paket}' => (MikhmonBilling::isIsolirProfile($s['profile'] ?? '') ? ($parsed['pkg'] ?: '-') : ($s['profile'] ?? '-')),
                '{bulan}' => $bulan,
                '{tgl}' => (string)($parsed['tgl'] ?: ($this->config['cutoff_day'] ?? 10))
            );
            $messages[] = str_replace(array_keys($vars), array_values($vars), $tpl);
        }

        $header = "📣 <b>Reminder {$kategori} (" . count($messages) . " pelanggan)</b>";
        if (count($messages) > 25) {
            $this->sendDocument(
                $chat_id,
                'reminder-' . $kategori . '-' . date('Ymd') . '.txt',
                implode("\n\n" . str_repeat('-', 30) . "\n\n", $messages),
                $header
            );
            return;
        }

        // Replace the category menu with the header so the old buttons do not
        // stay clickable after the reminder has been built.
        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $header);
        } else {
            $this->sendMessage($chat_id, $header);
        }
        foreach ($messages as $m) {
            $this->sendMessage($chat_id, str_replace("\n", "\n", htmlspecialchars($m)));
        }
    }

    private function cmdBroadcast($chat_id, $tg_id, $text)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /broadcast hanya untuk owner bot.");
            return;
        }
        $text = trim((string)$text);
        if ($text === '') {
            $this->sendMessage($chat_id, "Gunakan: <code>/broadcast &lt;pesan&gt;</code>\nPesan dikirim ke semua grup terdaftar + notify chat IDs.");
            return;
        }

        $targets = array_values(array_unique(array_merge(
            array_map('strval', $this->config['group_ids'] ?? array()),
            array_map('strval', $this->config['notify_chat_ids'] ?? array())
        )));

        if (empty($targets)) {
            $this->sendMessage($chat_id, "⚠️ Tidak ada target: isi Group IDs atau Notify IDs di dashboard.");
            return;
        }

        $ok = 0;
        $fail = array();
        foreach ($targets as $t) {
            $res = $this->sendMessage($t, "📢 <b>PENGUMUMAN</b>\n" . htmlspecialchars($text));
            if (($res['ok'] ?? false)) {
                $ok++;
            } else {
                $fail[] = $t . ' (' . ($res['description'] ?? 'error') . ')';
            }
        }

        $html = "📢 Broadcast terkirim ke <b>{$ok}/" . count($targets) . "</b> chat.";
        if (!empty($fail)) {
            $html .= "\nGagal: " . htmlspecialchars(implode(', ', $fail));
        }
        $this->sendMessage($chat_id, $html);
    }

    private function cmdSetTemplate($chat_id, $tg_id, $args, $msg_id = null)
    {
        if (!$this->hsIsOwner($tg_id)) {
            $this->sendMessage($chat_id, "⛔ /settemplate hanya untuk owner bot.");
            return;
        }

        $kategori = strtolower(trim((string)($args[0] ?? '')));
        if ($kategori === '') {
            // Menu pilih template — tanpa ketik manual.
            $keyboard = array(
                array(
                    array('text' => '🟡 Template Tenggang', 'callback_data' => 'tplview:tenggang'),
                    array('text' => '🔴 Template Isolir', 'callback_data' => 'tplview:isolir')
                ),
                array(array('text' => '❌ Batal', 'callback_data' => 'batal'))
            );
            $reply_markup = array('inline_keyboard' => $keyboard);
            $html = "📝 <b>Template Reminder</b>\n";
            $html .= "Pilih template untuk dilihat.\n";
            $html .= "Untuk mengubah: <code>/settemplate &lt;tenggang|isolir&gt; &lt;teks baru&gt;</code>";
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $reply_markup);
            } else {
                $this->sendMessage($chat_id, $html, $reply_markup);
            }
            return;
        }
        if (!in_array($kategori, array('tenggang', 'isolir'), true)) {
            $this->sendMessage($chat_id, "Kategori tidak dikenal. Gunakan /settemplate lalu pilih tombol template.");
            return;
        }

        $body = trim(implode(' ', array_slice($args, 1)));
        if ($body === '') {
            $tpl = MikhmonBilling::getTemplate('reminder_' . $kategori);
            if ($tpl === null || trim($tpl) === '') {
                $tpl = $this->defaultReminderTemplates()[$kategori];
            }
            $html = "📝 Template <b>{$kategori}</b> saat ini:\n";
            $html .= "<code>" . htmlspecialchars($tpl) . "</code>\n\n";
            $html .= "Placeholder: <code>{nama} {user} {paket} {bulan} {tgl}</code>";
            $kb = array('inline_keyboard' => array($this->pppNavRow('tplmenu', '🔙 Template')));
            if (!empty($msg_id)) {
                $this->editMessageText($chat_id, $msg_id, $html, $kb);
            } else {
                $this->sendMessage($chat_id, $html, $kb);
            }
            return;
        }

        MikhmonBilling::setTemplate('reminder_' . $kategori, $body);
        $this->sendMessage($chat_id, "✅ Template <b>{$kategori}</b> tersimpan.");
    }

    /* ==========================================================
     * Callbacks (pp…:)
     * ========================================================== */

    /**
     * Handle pp* callback data. Returns true when handled.
     */
    private function handlePppCallback($cb_id, $tg_id, $sender_name, $chat_id, $msg_id, $data)
    {
        // Tombol "🔙 Pilih pelanggan": kembali ke daftar pelanggan dari
        // langkah konfirmasi/picker berikutnya.
        if ($data === 'ppprofu' || $data === 'pppassu' || $data === 'ppdel') {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $titles = array(
                'ppprofu' => 'Ganti paket — pilih pelanggan:',
                'pppassu' => 'Reset password — pilih pelanggan:',
                'ppdel' => 'Hapus pelanggan — pilih:'
            );
            $this->answerCallback($cb_id, 'Memuat daftar...');
            $this->showPickUser($chat_id, $data, $titles[$data], $msg_id);
            return true;
        }

        if (str_starts_with($data, 'ppaddprof:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $rest = substr($data, 10);
            $sep = strpos($rest, ':');
            if ($sep === false) {
                $this->answerCallback($cb_id);
                return true;
            }
            $user = substr($rest, 0, $sep);
            $profile = substr($rest, $sep + 1);
            $this->answerCallback($cb_id, "Menambahkan {$user}...");
            $this->cmdPpAdd($chat_id, $tg_id, array($user, $profile), $msg_id);
            return true;
        }

        if (str_starts_with($data, 'ppdelok:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat menghapus!', true);
                return true;
            }
            $name = substr($data, 8);
            $api = $this->getRouterAPI();
            if (!$api) {
                $this->answerCallback($cb_id, '⚠️ Gagal terhubung router', true);
                return true;
            }
            $res = MikhmonBilling::deleteSecret($api, $name);
            $api->disconnect();

            if ($res['success']) {
                $this->answerCallback($cb_id, "🗑 {$name} dihapus!");
                $this->editMessageText($chat_id, $msg_id, "🗑 Pelanggan <b>" . htmlspecialchars($name) . "</b> telah dihapus oleh @{$sender_name}.", array('inline_keyboard' => array($this->pppNavRow('ppdel', '🔙 Pilih pelanggan'))));
            } else {
                $this->answerCallback($cb_id, $res['message'], true);
                $this->editMessageText($chat_id, $msg_id, "❌ " . htmlspecialchars($res['message']), array('inline_keyboard' => array($this->pppNavRow('ppdel', '🔙 Pilih pelanggan'))));
            }
            return true;
        }

        if (str_starts_with($data, 'ppdel:')) {
            $kode = substr($data, 6);
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner yang dapat menghapus!', true);
                return true;
            }
            $this->answerCallback($cb_id, "Memuat {$kode}...");
            $this->cmdPpDel($chat_id, $tg_id, $kode, $msg_id);
            return true;
        }

        if (str_starts_with($data, 'ppprofu:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $name = substr($data, 8);
            $this->answerCallback($cb_id, "Memuat paket {$name}...");
            $this->cmdPpProf($chat_id, $tg_id, array($name), $msg_id);
            return true;
        }

        if (str_starts_with($data, 'ppprofset:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $rest = substr($data, 10);
            $sep = strpos($rest, ':');
            if ($sep === false) {
                $this->answerCallback($cb_id);
                return true;
            }
            $name = substr($rest, 0, $sep);
            $profile = substr($rest, $sep + 1);
            $this->answerCallback($cb_id, "Mengubah paket {$name}...");
            $this->cmdPpProf($chat_id, $tg_id, array($name, $profile), $msg_id);
            return true;
        }

        if (str_starts_with($data, 'pppassu:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $name = substr($data, 8);
            $this->answerCallback($cb_id, "Konfirmasi reset password...");
            $btns = array(
                array(
                    array('text' => '🔑 Ya, reset acak', 'callback_data' => 'pppassok:' . $name),
                    array('text' => '❌ Batal', 'callback_data' => 'batal')
                ),
                array(
                    array('text' => '🔙 Pilih pelanggan', 'callback_data' => 'pppassu')
                )
            );
            $html = "🔑 <b>Reset password pelanggan?</b>\n";
            $html .= "Pelanggan: <code>" . htmlspecialchars($name) . "</code>\n";
            $html .= "Password baru dibuat acak dan sesi aktif diputus.";
            $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $btns));
            return true;
        }

        if (str_starts_with($data, 'pppassok:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $name = substr($data, 9);
            $this->answerCallback($cb_id, "Mereset password {$name}...");
            $this->cmdPpPass($chat_id, $tg_id, array($name), $msg_id);
            return true;
        }

        if ($data === 'remmenu' || $data === 'tplmenu') {
            if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $this->answerCallback($cb_id, 'Memuat menu...');
            if ($data === 'remmenu') {
                $this->cmdReminder($chat_id, $tg_id, array(), $msg_id);
            } else {
                $this->cmdSetTemplate($chat_id, $tg_id, array(), $msg_id);
            }
            return true;
        }

        if (str_starts_with($data, 'remcat:')) {
            if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return true;
            }
            $kategori = substr($data, 7);
            $this->answerCallback($cb_id, "Menyusun reminder {$kategori}...");
            $this->cmdReminder($chat_id, $tg_id, array($kategori), $msg_id);
            return true;
        }

        if (str_starts_with($data, 'tplview:')) {
            if (!$this->hsIsOwner($tg_id)) {
                $this->answerCallback($cb_id, '⛔ Hanya owner!', true);
                return true;
            }
            $kategori = substr($data, 8);
            $this->answerCallback($cb_id, "Memuat template {$kategori}...");
            $this->cmdSetTemplate($chat_id, $tg_id, array($kategori), $msg_id);
            return true;
        }

        return false;
    }
}
