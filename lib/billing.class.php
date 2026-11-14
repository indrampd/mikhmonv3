<?php
/*
 * Billing & Isolir PPPoE Engine for Mikhmon v3
 * Re-implemented natively in PHP (replaces isotagih Python backend)
 */

class MikhmonBilling
{
    const DEFAULT_ISOLIR = "pppoe-ISOLIR";
    const DEFAULT_CUTOFF = 10;

    private static $db = null;
    private static $db_path = null;

    public static function getDbPath()
    {
        if (self::$db_path === null) {
            self::$db_path = dirname(__DIR__) . '/data/billing.db';
        }
        return self::$db_path;
    }

    public static function getDb()
    {
        if (self::$db === null) {
            $path = self::getDbPath();
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            self::$db = new PDO("sqlite:" . $path);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::initSchema();
        }
        return self::$db;
    }

    private static function initSchema()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS events (
                ts TEXT, tg_id TEXT, action TEXT, target TEXT, detail TEXT
            );
            CREATE TABLE IF NOT EXISTS templates (
                name TEXT PRIMARY KEY, body TEXT NOT NULL, updated TEXT
            );
            CREATE TABLE IF NOT EXISTS access (
                kind TEXT NOT NULL,
                ref_id TEXT NOT NULL,
                title TEXT DEFAULT '',
                perms TEXT DEFAULT '[]',
                note TEXT DEFAULT '',
                created TEXT,
                updated TEXT,
                PRIMARY KEY(kind, ref_id)
            );
            CREATE TABLE IF NOT EXISTS access_log (
                ts TEXT, by_tg TEXT, action TEXT, detail TEXT
            );
            CREATE INDEX IF NOT EXISTS access_log_ts ON access_log(ts);
        ";
        self::$db->exec($sql);
    }

    public static function getConfig()
    {
        $cfg_file = dirname(__DIR__) . '/data/bot_config.json';
        if (file_exists($cfg_file)) {
            $data = json_decode(file_get_contents($cfg_file), true);
            if (is_array($data)) return $data;
        }
        return array(
            'token' => '',
            'owner_ids' => array(),
            'group_ids' => array(),
            'group_scope' => 'tagih',
            'webhook_secret' => '',
            'isolir_profile' => self::DEFAULT_ISOLIR,
            'cutoff_day' => self::DEFAULT_CUTOFF
        );
    }

    public static function saveConfig($config)
    {
        $cfg_file = dirname(__DIR__) . '/data/bot_config.json';
        $dir = dirname($cfg_file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $res = @file_put_contents($cfg_file, $json);
        if ($res === false) {
            @chmod($cfg_file, 0666);
            $res = @file_put_contents($cfg_file, $json);
        }
        return $res;
    }

    /* ==========================================================
     * Comment Parser & Builder
     * Format: "Nama Pelanggan|pkg:pppoe-P10|lunas:2026-11|tgl:10"
     * ========================================================== */

    public static function parseComment($comment)
    {
        $comment = (string)($comment ?? '');
        $pkg = null;
        $lunas = null;
        $tgl = null;

        if (preg_match('/pkg:([^|]+)/', $comment, $m)) {
            $pkg = trim($m[1]);
        }
        if (preg_match('/lunas:(\d{4}-\d{2})/', $comment, $m)) {
            $lunas = trim($m[1]);
        }
        if (preg_match('/tgl:(\d{1,2})/', $comment, $m)) {
            $val = (int)$m[1];
            if ($val >= 1 && $val <= 28) {
                $tgl = $val;
            }
        }

        // Clean label: remove metadata tokens
        $label = preg_replace('/pkg:[^|]+|lunas:\d{4}-\d{2}|tgl:\d{1,2}/', '', $comment);
        $label = trim($label, " |\t\n\r\0\x0B");

        return array(
            'label' => $label,
            'pkg' => $pkg,
            'lunas' => $lunas,
            'tgl' => $tgl
        );
    }

    public static function buildComment($currentComment, $pkg = null, $lunas = null, $tgl = null, $newLabel = null)
    {
        $parsed = self::parseComment($currentComment);
        $label = ($newLabel !== null) ? trim($newLabel) : $parsed['label'];
        $pkg = ($pkg !== null) ? $pkg : $parsed['pkg'];
        $lunas = ($lunas !== null) ? $lunas : $parsed['lunas'];
        $tgl = ($tgl !== null) ? $tgl : $parsed['tgl'];

        $parts = array();
        if ($label !== '') {
            $parts[] = $label;
        }
        if (!empty($pkg)) {
            $parts[] = "pkg:{$pkg}";
        }
        if (!empty($lunas)) {
            $parts[] = "lunas:{$lunas}";
        }
        if (!empty($tgl)) {
            $parts[] = "tgl:{$tgl}";
        }

        return implode('|', $parts);
    }

    public static function currentMonth()
    {
        return date('Y-m');
    }

    public static function getStatus($profile, $comment, $cutoffDay = null)
    {
        $parsed = self::parseComment($comment);
        $pkg = $parsed['pkg'];
        $lunas = $parsed['lunas'];

        if (empty($pkg)) {
            return array(
                'code' => 'none',
                'label' => 'Tanpa Paket',
                'badge' => 'secondary',
                'icon' => 'fa-circle-o',
                'hint' => 'Belum ada paket (pkg:) di comment'
            );
        }

        $curMonth = self::currentMonth();
        if ($lunas === $curMonth) {
            return array(
                'code' => 'lunas',
                'label' => 'Aktif',
                'badge' => 'success',
                'icon' => 'fa-check-circle',
                'hint' => 'Tagihan bulan ini sudah dibayar'
            );
        }

        $cutoff = $parsed['tgl'] ?? $cutoffDay ?? self::DEFAULT_CUTOFF;
        $today = (int)date('j');

        if ($today <= $cutoff) {
            return array(
                'code' => 'tenggang',
                'label' => 'Tenggang',
                'badge' => 'warning',
                'icon' => 'fa-clock-o',
                'hint' => "Belum bayar, jatuh tempo tgl {$cutoff}"
            );
        }

        return array(
            'code' => 'isolir',
            'label' => 'Isolir',
            'badge' => 'danger',
            'icon' => 'fa-exclamation-circle',
            'hint' => "Lewat jatuh tempo (tgl {$cutoff}). Layanan dibatasi"
        );
    }

    /* ==========================================================
     * Core Router Actions
     * ========================================================== */

    public static function markLunas($API, $username, $by = 'Web Mikhmon', $actor = '')
    {
        $secrets = $API->comm("/ppp/secret/print", array("?name" => $username));
        if (empty($secrets) || !isset($secrets[0])) {
            return array('success' => false, 'message' => "User PPPoE '{$username}' tidak ditemukan.");
        }

        $cust = $secrets[0];
        $id = $cust['.id'];
        $comment = $cust['comment'] ?? '';
        $profile = $cust['profile'] ?? '';
        $parsed = self::parseComment($comment);

        $targetPkg = $parsed['pkg'] ?? $profile;
        $newComment = self::buildComment($comment, $targetPkg, self::currentMonth(), $parsed['tgl']);

        // Update secret
        $payload = array(
            '.id' => $id,
            'comment' => $newComment
        );
        if ($profile !== $targetPkg && !empty($targetPkg)) {
            // .id, not the name: a prefix match across several profiles is
            // rejected by RouterOS and would drop this whole set call.
            $payload['profile'] = routeros_profile_id($API, 'ppp', $targetPkg);
        }

        $API->comm("/ppp/secret/set", $payload);

        // Terminate any active sessions to force re-auth
        self::killActiveSession($API, $username);

        // Log event & emit SSE
        self::logEvent($by, 'lunas', $username, $actor ?: self::currentMonth());
        self::emitLiveEvent('lunas', $username, $targetPkg);

        return array(
            'success' => true,
            'message' => "User {$username} berhasil ditandai LUNAS ({$targetPkg}).",
            'pkg' => $targetPkg,
            'comment' => $newComment
        );
    }

    public static function markIsolir($API, $username, $by = 'Web Mikhmon', $actor = '', $isolirProfile = null)
    {
        $isolirProfile = $isolirProfile ?: self::DEFAULT_ISOLIR;
        $secrets = $API->comm("/ppp/secret/print", array("?name" => $username));
        if (empty($secrets) || !isset($secrets[0])) {
            return array('success' => false, 'message' => "User PPPoE '{$username}' tidak ditemukan.");
        }

        $cust = $secrets[0];
        $id = $cust['.id'];
        $comment = $cust['comment'] ?? '';
        $parsed = self::parseComment($comment);

        // Ensure original package is preserved in comment before switching to isolir
        $origPkg = $parsed['pkg'] ?: $cust['profile'];
        $newComment = self::buildComment($comment, $origPkg, $parsed['lunas'], $parsed['tgl']);

        $API->comm("/ppp/secret/set", array(
            '.id' => $id,
            'profile' => routeros_profile_id($API, 'ppp', $isolirProfile),
            'comment' => $newComment
        ));

        self::killActiveSession($API, $username);

        self::logEvent($by, 'isolir', $username, $actor ?: $isolirProfile);
        self::emitLiveEvent('isolir', $username, $isolirProfile);

        return array(
            'success' => true,
            'message' => "User {$username} berhasil dialihkan ke {$isolirProfile}.",
            'profile' => $isolirProfile
        );
    }

    public static function restorePaket($API, $username, $by = 'Web Mikhmon', $actor = '')
    {
        $secrets = $API->comm("/ppp/secret/print", array("?name" => $username));
        if (empty($secrets) || !isset($secrets[0])) {
            return array('success' => false, 'message' => "User PPPoE '{$username}' tidak ditemukan.");
        }

        $cust = $secrets[0];
        $id = $cust['.id'];
        $comment = $cust['comment'] ?? '';
        $parsed = self::parseComment($comment);
        $targetPkg = $parsed['pkg'];

        if (empty($targetPkg)) {
            return array('success' => false, 'message' => "User {$username} belum memiliki tag paket asal (pkg:) di comment.");
        }

        $API->comm("/ppp/secret/set", array(
            '.id' => $id,
            'profile' => routeros_profile_id($API, 'ppp', $targetPkg)
        ));

        self::killActiveSession($API, $username);

        self::logEvent($by, 'pulih', $username, $actor ?: $targetPkg);
        self::emitLiveEvent('pulih', $username, $targetPkg);

        return array(
            'success' => true,
            'message' => "Paket {$username} berhasil dipulihkan ke {$targetPkg}.",
            'pkg' => $targetPkg
        );
    }

    public static function killActiveSession($API, $username)
    {
        $actives = $API->comm("/ppp/active/print", array("?name" => $username));
        $count = 0;
        if (is_array($actives)) {
            foreach ($actives as $act) {
                if (isset($act['.id'])) {
                    $API->comm("/ppp/active/remove", array(".id" => $act['.id']));
                    $count++;
                }
            }
        }
        return $count;
    }

    /* ==========================================================
     * Event Logging & Live Stream (SSE)
     * ========================================================== */

    public static function logEvent($tg_id, $action, $target, $detail = '')
    {
        try {
            $db = self::getDb();
            $ts = date('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO events (ts, tg_id, action, target, detail) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(array($ts, (string)$tg_id, (string)$action, (string)$target, (string)$detail));
        } catch (Exception $e) {
            // Silently ignore log errors
        }
    }

    public static function emitLiveEvent($action, $target, $detail = '')
    {
        $file = dirname(__DIR__) . '/data/live_event.json';
        $payload = array(
            'ts' => microtime(true),
            'action' => $action,
            'target' => $target,
            'detail' => $detail,
            'human_time' => date('H:i:s')
        );
        @file_put_contents($file, json_encode($payload));
    }

    public static function getEvents($limit = 50)
    {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT ts, tg_id, action, target, detail FROM events ORDER BY ts DESC LIMIT ?");
            $stmt->execute(array((int)$limit));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
    }
}
