<?php
/*
 * Telegram Bot Engine for Mikhmon v3 & PPPoE Billing
 * Replaces Python isotagih with pure PHP 8.4
 */

require_once __DIR__ . '/billing.class.php';
require_once __DIR__ . '/hotspot_billing.class.php';
require_once __DIR__ . '/telegram_hotspot_commands.trait.php';
require_once __DIR__ . '/telegram_ppp_commands.trait.php';
require_once __DIR__ . '/routeros_api.class.php';

class MikhmonTelegramBot
{
    use TelegramHotspotCommands;
    use TelegramPppCommands;

    private $token;
    private $config;
    private $api_base;

    public function __construct()
    {
        $this->config = MikhmonBilling::getConfig();
        $this->token = $this->config['token'] ?? '';
        $this->api_base = "https://api.telegram.org/bot" . $this->token;
    }

    /* ==========================================================
     * Low-level Telegram API calls
     * ========================================================== */

    public function apiCall($method, $payload = array())
    {
        if (empty($this->token)) {
            return array('ok' => false, 'description' => 'Bot token not configured');
        }

        $url = $this->api_base . '/' . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        // Jangan biarkan satu panggilan menggantung lama di halaman dashboard:
        // gagal cepat lebih baik daripada badge status berubah jadi "Nonaktif".
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($res, true);
        return is_array($json) ? $json : array('ok' => false, 'description' => 'Invalid response');
    }

    public function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML')
    {
        $payload = array(
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        );
        if ($reply_markup !== null) {
            $payload['reply_markup'] = $reply_markup;
        }
        return $this->apiCall('sendMessage', $payload);
    }

    public function editMessageText($chat_id, $message_id, $text, $reply_markup = null, $parse_mode = 'HTML')
    {
        $payload = array(
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        );
        if ($reply_markup !== null) {
            $payload['reply_markup'] = $reply_markup;
        }
        return $this->apiCall('editMessageText', $payload);
    }

    public function answerCallback($callback_id, $text = '', $show_alert = false)
    {
        return $this->apiCall('answerCallbackQuery', array(
            'callback_query_id' => $callback_id,
            'text' => $text,
            'show_alert' => $show_alert
        ));
    }

    /**
     * Send a text file (used for large voucher batches). JSON payloads cannot
     * carry documents, so this uses a multipart form with CURLFile.
     */
    public function sendDocument($chat_id, $filename, $content, $caption = '')
    {
        if (empty($this->token)) {
            return array('ok' => false, 'description' => 'Bot token not configured');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'vc');
        file_put_contents($tmp, $content);

        $url = $this->api_base . '/sendDocument';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'chat_id' => $chat_id,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'document' => new CURLFile($tmp, 'text/plain', $filename)
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $res = curl_exec($ch);
        curl_close($ch);
        @unlink($tmp);

        $json = json_decode($res, true);
        return is_array($json) ? $json : array('ok' => false, 'description' => 'Invalid response');
    }

    public function setWebhook($url, $secret_token = null)
    {
        $payload = array('url' => $url);
        if (!empty($secret_token)) {
            $payload['secret_token'] = $secret_token;
        }
        $res = $this->apiCall('setWebhook', $payload);

        // Auto-fallback if Telegram resolver hasn't refreshed negative DNS cache
        if (!($res['ok'] ?? false) && str_contains($res['description'] ?? '', 'Failed to resolve host')) {
            $host = parse_url($url, PHP_URL_HOST);
            if (!empty($host)) {
                $resolved_ip = gethostbyname($host);
                if (!empty($resolved_ip) && $resolved_ip !== $host) {
                    $payload['ip_address'] = $resolved_ip;
                    $res = $this->apiCall('setWebhook', $payload);
                }
            }
        }

        return $res;
    }

    public function deleteWebhook()
    {
        return $this->apiCall('deleteWebhook');
    }

    public function getWebhookInfo()
    {
        return $this->apiCall('getWebhookInfo');
    }

    /**
     * Register the command list so the Telegram client palette ("/") shows
     * every available command. Old aliases (/tagihan, /lunas, ...) stay
     * functional but are not listed, keeping the palette tidy.
     */
    public function syncCommands()
    {
        $hs = MikhmonHotspotBilling::getHotspotConfig($this->config);
        $commands = array(
            array('command' => 'help', 'description' => 'Panduan lengkap bot'),
            array('command' => 'pptagihan', 'description' => 'Status tagihan semua pelanggan PPPoE'),
            array('command' => 'pplunas', 'description' => 'Tandai pelanggan lunas bulan ini'),
            array('command' => 'ppisolir', 'description' => 'Isolir pelanggan menunggak'),
            array('command' => 'ppcek', 'description' => 'Cek detail pelanggan & koneksi'),
            array('command' => 'reminder', 'description' => 'Susun pesan reminder tenggang/isolir'),
        );
        if ($hs['enabled']) {
            array_splice($commands, 5, 0, array(
                array('command' => 'hs', 'description' => 'Panduan modul voucher hotspot'),
                array('command' => 'hscek', 'description' => 'Detail voucher hotspot'),
                array('command' => 'hslist', 'description' => 'Rekap voucher per profil'),
                array('command' => 'hsonline', 'description' => 'Daftar sesi hotspot aktif'),
                array('command' => 'hskick', 'description' => 'Putus sesi voucher aktif'),
                array('command' => 'hsreset', 'description' => 'Reset counter pemakaian voucher'),
            ));
            // Owner-only commands last, marked in the description.
            array_push($commands,
                array('command' => 'hsgen', 'description' => '(Owner) Generate voucher baru'),
                array('command' => 'hsdel', 'description' => '(Owner) Hapus voucher'),
                array('command' => 'ppadd', 'description' => '(Owner) Tambah pelanggan PPPoE'),
                array('command' => 'ppprof', 'description' => '(Owner) Ganti paket pelanggan'),
                array('command' => 'pppass', 'description' => '(Owner) Reset password pelanggan'),
                array('command' => 'ppdel', 'description' => '(Owner) Hapus pelanggan PPPoE'),
                array('command' => 'settemplate', 'description' => '(Owner) Atur template reminder'),
                array('command' => 'broadcast', 'description' => '(Owner) Pengumuman ke semua chat')
            );
        }
        return $this->apiCall('setMyCommands', array('commands' => $commands));
    }

    /* ==========================================================
     * Router Connection Helper
     * ========================================================== */

    public function getRouterAPI()
    {
        // 1. Try bot_config.json router definition
        $r_cfg = $this->config['router'] ?? null;
        if (!empty($r_cfg['host']) && !empty($r_cfg['user'])) {
            $api = new RouterosAPI();
            $api->timeout = 5;
            $api->attempts = 1;
            $api->delay = 0;
            $pass = $r_cfg['password'] ?? '';
            if ($api->connect($r_cfg['host'], $r_cfg['user'], $pass)) {
                return $api;
            }
        }

        // 2. Fallback to Mikhmon config.php sessions
        $cfg_file = dirname(__DIR__) . '/include/config.php';
        if (file_exists($cfg_file)) {
            include $cfg_file;
            if (isset($data) && is_array($data)) {
                foreach ($data as $ses => $val) {
                    if ($ses === 'mikhmon' || empty($val[1])) continue;
                    $ip = explode('!', $val[1])[1] ?? '';
                    $user = explode('@|@', $val[2])[1] ?? '';
                    $pass = decrypt(explode('#|#', $val[3])[1] ?? '');
                    if (!empty($ip) && !empty($user)) {
                        $api = new RouterosAPI();
                        $api->timeout = 5;
                        $api->attempts = 1;
                        $api->delay = 0;
                        if ($api->connect($ip, $user, $pass)) {
                            return $api;
                        }
                    }
                }
            }
        }

        return null;
    }

    /* ==========================================================
     * Authorization Checks
     * ========================================================== */

    public function isAuthorized($tg_id, $chat_id, $action = 'lihat')
    {
        $owners = $this->config['owner_ids'] ?? array();
        if (in_array((string)$tg_id, array_map('strval', $owners), true)) {
            return true;
        }

        // Check group scope
        $groups = $this->config['group_ids'] ?? array();
        if (in_array((string)$chat_id, array_map('strval', $groups), true)) {
            $scope = $this->config['group_scope'] ?? 'tagih';
            if ($scope === 'penuh') return true;
            if ($scope === 'tagih' && in_array($action, array('lihat', 'cek', 'lunas'), true)) return true;
            if ($scope === 'lihat' && in_array($action, array('lihat', 'cek'), true)) return true;
        }

        // Check access table in SQLite
        try {
            $db = MikhmonBilling::getDb();
            $stmt = $db->prepare("SELECT perms FROM access WHERE (kind='user' AND ref_id=?) OR (kind='group' AND ref_id=?)");
            $stmt->execute(array((string)$tg_id, (string)$chat_id));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $perms = json_decode($row['perms'] ?? '[]', true);
                if (is_array($perms) && (in_array($action, $perms) || in_array('semua', $perms))) {
                    return true;
                }
            }
        } catch (Exception $e) {}

        return false;
    }

    /* ==========================================================
     * Webhook Update Processor
     * ========================================================== */

    public function handleUpdate($update)
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    private function handleMessage($msg)
    {
        $chat_id = $msg['chat']['id'] ?? '';
        $tg_id = $msg['from']['id'] ?? '';
        $text = trim($msg['text'] ?? '');
        $sender_name = $msg['from']['first_name'] ?? 'Admin';

        if (empty($text) || $text[0] !== '/') {
            return;
        }

        // Parse command and args, strip bot username e.g. /tagihan@mybot
        $parts = preg_split('/\s+/', $text);
        $cmd = strtolower(explode('@', substr($parts[0], 1))[0]);
        $arg = trim($parts[1] ?? '');
        $args = array_slice($parts, 1);

        switch ($cmd) {
            case 'start':
            case 'help':
            case 'menu':
                $this->cmdHelp($chat_id, $tg_id);
                break;

            case 'pptagihan':
            case 'tagihan':          // alias lama
                $this->cmdTagihan($chat_id, $tg_id);
                break;

            case 'pplunas':
            case 'lunas':            // alias lama
                $this->cmdLunas($chat_id, $tg_id, $arg, $sender_name);
                break;

            case 'ppisolir':
            case 'isolir':           // alias lama
                $this->cmdIsolir($chat_id, $tg_id, $arg, $sender_name);
                break;

            case 'ppcek':
            case 'cek':              // alias lama
                $this->cmdCek($chat_id, $tg_id, $arg);
                break;

            /* Hotspot voucher commands (/hs*) */
            case 'hs':
                $this->cmdHsHelp($chat_id, $tg_id);
                break;

            case 'hscek':
                $this->cmdHsCek($chat_id, $tg_id, $arg);
                break;

            case 'hsonline':
                $this->cmdHsOnline($chat_id, $tg_id);
                break;

            case 'hslist':
                $this->cmdHsList($chat_id, $tg_id, $arg);
                break;

            case 'hskick':
                $this->cmdHsKick($chat_id, $tg_id, $arg, $sender_name);
                break;

            case 'hsreset':
                $this->cmdHsReset($chat_id, $tg_id, $arg, $sender_name);
                break;

            case 'hsgen':
                $this->cmdHsGen($chat_id, $tg_id, $args, $sender_name);
                break;

            case 'hsdel':
                $this->cmdHsDel($chat_id, $tg_id, $arg);
                break;

            /* PPPoE customer management + messaging (/pp*, /reminder, ...) */
            case 'ppadd':
                $this->cmdPpAdd($chat_id, $tg_id, $args);
                break;

            case 'ppprof':
                $this->cmdPpProf($chat_id, $tg_id, $args);
                break;

            case 'pppass':
                $this->cmdPpPass($chat_id, $tg_id, $args);
                break;

            case 'ppdel':
                $this->cmdPpDel($chat_id, $tg_id, $arg);
                break;

            case 'reminder':
                $this->cmdReminder($chat_id, $tg_id, $args);
                break;

            case 'broadcast':
                $this->cmdBroadcast($chat_id, $tg_id, implode(' ', $args));
                break;

            case 'settemplate':
                $this->cmdSetTemplate($chat_id, $tg_id, $args);
                break;

            default:
                $this->sendMessage($chat_id, "Perintah <code>/{$cmd}</code> tidak dikenal.\nKetik /help untuk panduan.");
                break;
        }
    }

    private function cmdHelp($chat_id, $tg_id)
    {
        $html = "<b>🤖 Bot Administrasi PPPoE &amp; Hotspot</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━\n";
        $html .= "<i>Tips: kirim perintah tanpa argumen untuk memilih via tombol.</i>\n\n";

        $html .= "<b>📡 PPPoE</b>\n";
        $html .= "/pptagihan — Status tagihan semua pelanggan\n";
        $html .= "/pplunas <code>[user]</code> — Tandai lunas &amp; pulihkan paket\n";
        $html .= "/ppisolir <code>[user]</code> — Isolir pelanggan menunggak\n";
        $html .= "/ppcek <code>[user]</code> — Detail pelanggan &amp; koneksi\n\n";

        if (MikhmonHotspotBilling::getHotspotConfig($this->config)['enabled']) {
            $html .= "<b>🎫 Voucher Hotspot</b>\n";
            $html .= "/hscek <code>[kode]</code> — Detail voucher &amp; status online\n";
            $html .= "/hslist <code>[profil]</code> — Rekap voucher per profil\n";
            $html .= "/hsonline — Sesi aktif + tombol putus sesi\n";
            $html .= "/hskick <code>[kode]</code> — Putus sesi aktif\n";
            $html .= "/hsreset <code>[kode]</code> — Reset counter pemakaian\n";
            $html .= "/hsgen · /hsdel <code>[kode]</code> — <i>owner</i>\n\n";
        }

        $html .= "<b>👤 Kelola Pelanggan &amp; Pesan</b> — <i>owner</i>\n";
        $html .= "/ppadd <code>[user]</code> — Tambah pelanggan (paket via tombol)\n";
        $html .= "/ppprof — Ganti paket (pilih pelanggan → paket)\n";
        $html .= "/pppass — Reset password (pilih pelanggan)\n";
        $html .= "/ppdel — Hapus pelanggan (pilih pelanggan)\n";
        $html .= "/reminder — Susun pesan reminder (pilih kategori)\n";
        $html .= "/settemplate — Atur template reminder\n";
        $html .= "/broadcast <code>[pesan]</code> — Pengumuman ke semua chat\n\n";

        $html .= "Panduan lengkap: /help\n\n";
        $html .= "<i>Status: ✅ Aktif · 🟡 Tenggang · 🔴 Isolir</i>";

        $this->sendMessage($chat_id, $html);
    }

    private function cmdTagihan($chat_id, $tg_id, $message_id = null)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'lihat')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin untuk melihat daftar tagihan.");
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS. Pastikan router aktif dan API menyala.");
            return;
        }

        $secrets = $api->comm("/ppp/secret/print");
        $api->disconnect();

        $cur_month = MikhmonBilling::currentMonth();
        $counts = array('lunas' => 0, 'tenggang' => 0, 'isolir' => 0, 'none' => 0);
        $user_lists = array('tenggang' => array(), 'isolir' => array(), 'lunas' => array());

        foreach ($secrets as $s) {
            $uname = $s['name'] ?? '';
            $comm = $s['comment'] ?? '';
            $prof = $s['profile'] ?? '';
            $stat = MikhmonBilling::getStatus($prof, $comm);
            $code = $stat['code'];

            if (isset($counts[$code])) {
                $counts[$code]++;
            }

            $parsed = MikhmonBilling::parseComment($comm);
            $label = $parsed['label'] ?: $uname;

            if (isset($user_lists[$code])) {
                $user_lists[$code][] = array('name' => $uname, 'label' => $label, 'pkg' => $parsed['pkg']);
            }
        }

        $total = count($secrets);
        $html = "💳 <b>Status Tagihan PPPoE ({$cur_month})</b>\n";
        $html .= "Total Pelanggan: <b>{$total}</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━\n";
        $html .= "✅ Aktif/Lunas : <b>{$counts['lunas']}</b>\n";
        $html .= "🟡 Masa Tenggang: <b>{$counts['tenggang']}</b>\n";
        $html .= "🔴 Terisolir   : <b>{$counts['isolir']}</b>\n";
        if ($counts['none'] > 0) {
            $html .= "⚪ Tanpa Paket : <b>{$counts['none']}</b>\n";
        }
        $html .= "━━━━━━━━━━━━━━━━━━━\n";

        // Show buttons to view list
        $keyboard = array(
            array(
                array('text' => "🔴 Isolir ({$counts['isolir']})", 'callback_data' => 'list:isolir'),
                array('text' => "🟡 Tenggang ({$counts['tenggang']})", 'callback_data' => 'list:tenggang'),
            ),
            array(
                array('text' => "✅ Lunas ({$counts['lunas']})", 'callback_data' => 'list:lunas'),
                array('text' => "🔄 Refresh", 'callback_data' => 'refresh_tagihan')
            )
        );

        $reply_markup = array('inline_keyboard' => $keyboard);

        if ($message_id) {
            $this->editMessageText($chat_id, $message_id, $html, $reply_markup);
        } else {
            $this->sendMessage($chat_id, $html, $reply_markup);
        }
    }

    private function cmdLunas($chat_id, $tg_id, $user, $sender_name)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'lunas')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin untuk menandai lunas.");
            return;
        }

        if (empty($user)) {
            $this->showPickUser($chat_id, 'lunas', 'Pilih pelanggan yang akan ditandai LUNAS:');
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $res = MikhmonBilling::markLunas($api, $user, "Telegram (@{$sender_name})", MikhmonBilling::currentMonth());
        $api->disconnect();

        if ($res['success']) {
            $cur_m = MikhmonBilling::currentMonth();
            $html = "✅ <b>{$user}</b> — Berhasil ditandai <b>LUNAS</b>!\n";
            $html .= "Pembayaran tercatat untuk: <b>{$cur_m}</b>\n";
            $html .= "Paket dipulihkan ke: <code>{$res['pkg']}</code>\n";
            $html .= "Diproses oleh: <b>{$sender_name}</b>";
            $this->sendMessage($chat_id, $html);
        } else {
            $this->sendMessage($chat_id, "❌ " . $res['message']);
        }
    }

    private function cmdIsolir($chat_id, $tg_id, $user, $sender_name)
    {
        if (!$this->isAuthorized($tg_id, $chat_id, 'isolir')) {
            $this->sendMessage($chat_id, "⛔ Anda tidak memiliki izin untuk mengisolir pelanggan.");
            return;
        }

        if (empty($user)) {
            $this->showPickUser($chat_id, 'isolir', 'Pilih pelanggan yang akan di-ISOLIR:');
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $res = MikhmonBilling::markIsolir($api, $user, "Telegram (@{$sender_name})", "Manual Isolir");
        $api->disconnect();

        if ($res['success']) {
            $html = "🔴 <b>{$user}</b> — Pelanggan berhasil di-<b>ISOLIR</b>!\n";
            $html .= "Profil dialihkan ke: <code>{$res['profile']}</code>\n";
            $html .= "Sesi aktif telah diputus.\n";
            $html .= "Diproses oleh: <b>{$sender_name}</b>";
            $this->sendMessage($chat_id, $html);
        } else {
            $this->sendMessage($chat_id, "❌ " . $res['message']);
        }
    }

    private function cmdCek($chat_id, $tg_id, $user, $msg_id = null)
    {
        if (empty($user)) {
            // Same click-through list as /lunas and /isolir: typing a name is
            // optional when the customer can be picked from a button.
            $this->showPickUser($chat_id, 'pickcek', 'Pilih pelanggan untuk dicek:', $msg_id);
            return;
        }

        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        // The secret is usually "name@host", so a bare "deni" must be resolved
        // before the exact-name lookup, otherwise /cek reports it as missing.
        list($c, $resolveError) = MikhmonBilling::resolveSecret($api, $user);
        if ($c === null) {
            $api->disconnect();
            $this->sendMessage($chat_id, htmlspecialchars($resolveError));
            return;
        }
        $resolved = $c['name'] ?? $user;

        $actives = $api->comm("/ppp/active/print", array("?name" => $resolved));
        $api->disconnect();

        $is_online = !empty($actives);
        $active_info = $is_online ? $actives[0] : null;

        $parsed = MikhmonBilling::parseComment($c['comment'] ?? '');
        $stat = MikhmonBilling::getStatus($c['profile'] ?? '', $c['comment'] ?? '');

        $html = "👤 <b>Detail Pelanggan PPPoE</b>\n";
        $html .= "━━━━━━━━━━━━━━━━━━━\n";
        $html .= "Nama/Label : <b>" . ($parsed['label'] ?: $resolved) . "</b>\n";
        $html .= "Username   : <code>{$resolved}</code>\n";
        $html .= "Status     : <b>{$stat['label']}</b> ({$stat['hint']})\n";
        $html .= "Paket Asli : <code>" . ($parsed['pkg'] ?: '-') . "</code>\n";
        $html .= "Profil ROS : <code>" . ($c['profile'] ?? '-') . "</code>\n";
        $html .= "Lunas Bln  : <code>" . ($parsed['lunas'] ?: 'Belum pernah') . "</code>\n";
        $html .= "Jatuh Tempo: Tgl <b>" . ($parsed['tgl'] ?: '10') . "</b>\n";
        $html .= "Koneksi    : " . ($is_online ? "🟢 <b>ONLINE</b> (IP: " . ($active_info['address'] ?? '-') . ")" : "⚪ <b>OFFLINE</b>") . "\n";
        if ($is_online) {
            $html .= "Uptime     : " . ($active_info['uptime'] ?? '-') . "\n";
        }

        // Actions address the resolved secret name, not the raw query: a partial
        // "deni" must not be written back into callback_data.
        $btns = array(
            array(
                array('text' => '✅ Tandai Lunas', 'callback_data' => "lunas:{$resolved}"),
                array('text' => '🔴 Isolir', 'callback_data' => "isolir:{$resolved}")
            ),
            array(
                array('text' => '↩️ Kembali ke daftar', 'callback_data' => 'pickcek')
            )
        );
        $reply_markup = array('inline_keyboard' => $btns);

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, $html, $reply_markup);
        } else {
            $this->sendMessage($chat_id, $html, $reply_markup);
        }
    }

    private function showPickUser($chat_id, $action, $title, $msg_id = null)
    {
        $api = $this->getRouterAPI();
        if (!$api) {
            $this->sendMessage($chat_id, "⚠️ Gagal terhubung ke RouterOS.");
            return;
        }

        $secrets = $api->comm("/ppp/secret/print");
        $api->disconnect();

        $keyboard = array();
        $row = array();
        foreach ($secrets as $s) {
            $name = $s['name'] ?? '';
            $comm = $s['comment'] ?? '';
            $parsed = MikhmonBilling::parseComment($comm);
            $lbl = $parsed['label'] ? substr($parsed['label'], 0, 15) : $name;

            $row[] = array(
                'text' => $lbl,
                'callback_data' => "{$action}:{$name}"
            );
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = array();
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        $keyboard[] = array(
            array('text' => '❌ Batal', 'callback_data' => 'batal')
        );

        if (!empty($msg_id)) {
            $this->editMessageText($chat_id, $msg_id, "<b>{$title}</b>", array('inline_keyboard' => $keyboard));
        } else {
            $this->sendMessage($chat_id, "<b>{$title}</b>", array('inline_keyboard' => $keyboard));
        }
    }

    /* ==========================================================
     * Inline Keyboard Callbacks
     * ========================================================== */

    private function handleCallback($cb)
    {
        $cb_id = $cb['id'] ?? '';
        $tg_id = $cb['from']['id'] ?? '';
        $sender_name = $cb['from']['first_name'] ?? 'Admin';
        $chat_id = $cb['message']['chat']['id'] ?? '';
        $msg_id = $cb['message']['message_id'] ?? '';
        $data = $cb['data'] ?? '';

        // Hotspot routes (hs…:) come first — their prefix cannot collide
        // with the PPPoE namespaces below.
        if ($this->handleHotspotCallback($cb_id, $tg_id, $sender_name, $chat_id, $msg_id, $data)) {
            return;
        }

        if ($this->handlePppCallback($cb_id, $tg_id, $sender_name, $chat_id, $msg_id, $data)) {
            return;
        }

        if ($data === 'batal') {
            $this->answerCallback($cb_id, 'Dibatalkan');
            $this->editMessageText($chat_id, $msg_id, "<i>Tindakan dibatalkan.</i>");
            return;
        }

        if ($data === 'refresh_tagihan') {
            $this->answerCallback($cb_id, 'Memperbarui data...');
            $this->cmdTagihan($chat_id, $tg_id, $msg_id);
            return;
        }

        if (str_starts_with($data, 'list:')) {
            $filter = substr($data, 5);
            $this->showCategoryList($chat_id, $msg_id, $filter, $cb_id);
            return;
        }

        if ($data === 'pickcek') {
            $this->answerCallback($cb_id, 'Memuat daftar...');
            $this->showPickUser($chat_id, 'pickcek', 'Pilih pelanggan untuk dicek:', $msg_id);
            return;
        }

        if (str_starts_with($data, 'pickcek:')) {
            $user = substr($data, 8);
            $this->answerCallback($cb_id, "Memuat {$user}...");
            $this->cmdCek($chat_id, $tg_id, $user, $msg_id);
            return;
        }

        if (str_starts_with($data, 'lunas:')) {
            $user = substr($data, 6);
            if (!$this->isAuthorized($tg_id, $chat_id, 'lunas')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return;
            }

            $api = $this->getRouterAPI();
            if (!$api) {
                $this->answerCallback($cb_id, '⚠️ Gagal terhubung router', true);
                return;
            }

            $res = MikhmonBilling::markLunas($api, $user, "Telegram (@{$sender_name})", MikhmonBilling::currentMonth());
            $api->disconnect();

            if ($res['success']) {
                $this->answerCallback($cb_id, "✅ {$user} ditandai LUNAS!", false);
                $this->cmdCek($chat_id, $tg_id, $user, $msg_id);
            } else {
                $this->answerCallback($cb_id, $res['message'], true);
            }
            return;
        }

        if (str_starts_with($data, 'isolir:')) {
            $user = substr($data, 7);
            if (!$this->isAuthorized($tg_id, $chat_id, 'isolir')) {
                $this->answerCallback($cb_id, '⛔ Tidak memiliki izin!', true);
                return;
            }

            $api = $this->getRouterAPI();
            if (!$api) {
                $this->answerCallback($cb_id, '⚠️ Gagal terhubung router', true);
                return;
            }

            $res = MikhmonBilling::markIsolir($api, $user, "Telegram (@{$sender_name})", "Inline Isolir");
            $api->disconnect();

            if ($res['success']) {
                $this->answerCallback($cb_id, "🔴 {$user} berhasil diisolir!", false);
                $this->cmdCek($chat_id, $tg_id, $user, $msg_id);
            } else {
                $this->answerCallback($cb_id, $res['message'], true);
            }
            return;
        }
    }

    private function showCategoryList($chat_id, $msg_id, $category, $cb_id)
    {
        $api = $this->getRouterAPI();
        if (!$api) {
            $this->answerCallback($cb_id, 'Gagal terhubung router', true);
            return;
        }

        $secrets = $api->comm("/ppp/secret/print");
        $api->disconnect();

        $matched = array();
        foreach ($secrets as $s) {
            $uname = $s['name'] ?? '';
            $comm = $s['comment'] ?? '';
            $prof = $s['profile'] ?? '';
            $stat = MikhmonBilling::getStatus($prof, $comm);

            if ($stat['code'] === $category) {
                $parsed = MikhmonBilling::parseComment($comm);
                $lbl = $parsed['label'] ? substr($parsed['label'], 0, 16) : $uname;
                $matched[] = array('name' => $uname, 'label' => $lbl);
            }
        }

        $this->answerCallback($cb_id);

        $titles = array(
            'isolir' => '🔴 Daftar Pelanggan Terisolir',
            'tenggang' => '🟡 Daftar Pelanggan Masa Tenggang',
            'lunas' => '✅ Daftar Pelanggan Lunas'
        );
        $title = $titles[$category] ?? 'Daftar Pelanggan';

        if (empty($matched)) {
            $this->editMessageText($chat_id, $msg_id, "<b>{$title}</b>\n\n<i>Tidak ada pelanggan dalam kategori ini.</i>", array(
                'inline_keyboard' => array(
                    array(array('text' => '🔙 Kembali ke Ringkasan', 'callback_data' => 'refresh_tagihan'))
                )
            ));
            return;
        }

        $keyboard = array();
        $row = array();
        foreach ($matched as $item) {
            $row[] = array(
                'text' => $item['label'],
                'callback_data' => ($category === 'lunas' ? "isolir:{$item['name']}" : "lunas:{$item['name']}")
            );
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = array();
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        $keyboard[] = array(
            array('text' => '🔙 Kembali ke Ringkasan', 'callback_data' => 'refresh_tagihan')
        );

        $hint = ($category === 'lunas') ? 'Klik nama untuk ISOLIR:' : 'Klik nama untuk TANDAI LUNAS:';
        $html = "<b>{$title} (" . count($matched) . ")</b>\n<i>{$hint}</i>";

        $this->editMessageText($chat_id, $msg_id, $html, array('inline_keyboard' => $keyboard));
    }
}
