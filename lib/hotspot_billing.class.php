<?php
/*
 * Hotspot Voucher Engine for Mikhmon v3 (Telegram bot support)
 * Voucher/prepaid model: generate, inspect, session control, reset, delete.
 * Static style mirrors MikhmonBilling.
 */

require_once __DIR__ . '/billing.class.php';
require_once __DIR__ . '/routeros_api.class.php';

class MikhmonHotspotBilling
{
    const DEFAULT_BATCH_MAX = 50;

    /* ==========================================================
     * Config
     * ========================================================== */

    /**
     * Normalized hotspot section of bot_config.json.
     */
    public static function getHotspotConfig($config = null)
    {
        $config = $config ?: MikhmonBilling::getConfig();
        $hs = $config['hotspot'] ?? array();
        return array(
            'enabled' => (bool)($hs['enabled'] ?? true),
            'default_profile' => (string)($hs['default_profile'] ?? ''),
            'gen_prefix' => (string)($hs['gen_prefix'] ?? ''),
            'batch_max' => max(1, (int)($hs['batch_max'] ?? self::DEFAULT_BATCH_MAX)),
            'notify_chat_ids' => is_array($hs['notify_chat_ids'] ?? null) ? $hs['notify_chat_ids'] : array()
        );
    }

    /* ==========================================================
     * Comment Parser
     * Mikhmon generator format: "mode-batch-m.d.y-extra"
     * e.g. "vc-123-11.16.26-" / "up-456-11.16.26-keterangan"
     * Parser is lossless: raw comment is always preserved.
     * ========================================================== */

    public static function parseComment($comment)
    {
        $raw = (string)($comment ?? '');

        $mode = null;
        $batch = null;
        $gen_date = null;
        $expiry = null;
        $extra = '';

        $tokens = explode('-', $raw);
        // Mode token: "vc"/"up" (possibly prefixed names like "vc" alone).
        if (isset($tokens[0]) && preg_match('/^(vc|up)/i', $tokens[0])) {
            $mode = strtolower($tokens[0]);
        }
        // Batch token: pure digits right after mode.
        if ($mode !== null && isset($tokens[1]) && preg_match('/^\d{2,6}$/', $tokens[1])) {
            $batch = $tokens[1];
        }
        // Generation date: m.d.y from the generator (date("m.d.y")).
        if (preg_match('/\b(\d{1,2}\.\d{1,2}\.\d{2,4})\b/', $raw, $m)) {
            $gen_date = $m[1];
        }
        // Expiry conventions seen in the field: explicit exp:/until: token,
        // or a standalone MM/DD/YY date. Verified against production in
        // Phase 0; unknown formats simply leave expiry null.
        if (preg_match('/(?:exp|until)[:=]\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $raw, $m)) {
            $expiry = $m[1];
        } elseif (preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{2,4})\b/', $raw, $m)) {
            $expiry = $m[1];
        }
        // Free text: drop recognized metadata tokens, keep the rest.
        $extra = preg_replace('/^(vc|up)[\-]?\d{2,6}[\-]/i', '', $raw);
        $extra = preg_replace('/\b\d{1,2}\.\d{1,2}\.\d{2,4}\b/', '', $extra);
        $extra = trim($extra, " -|\t\n\r\0\x0B");

        return array(
            'raw' => $raw,
            'mode' => $mode,
            'batch' => $batch,
            'gen_date' => $gen_date,
            'expiry' => $expiry,
            'extra' => $extra
        );
    }

    /**
     * RouterOS uptime/limit string ("1w2d3h4m5s") to seconds. Null when empty.
     */
    public static function parseDuration($str)
    {
        $str = trim((string)$str);
        if ($str === '' || $str === '0') {
            return 0;
        }
        if (!preg_match_all('/(\d+)\s*(w|d|h|m|s)/i', $str, $m, PREG_SET_ORDER)) {
            return null;
        }
        $mult = array('w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1);
        $total = 0;
        foreach ($m as $part) {
            $total += ((int)$part[1]) * $mult[strtolower($part[2])];
        }
        return $total;
    }

    public static function formatDuration($seconds)
    {
        $seconds = (int)$seconds;
        if ($seconds <= 0) return '0s';
        $parts = array();
        foreach (array('w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1) as $unit => $size) {
            if ($seconds >= $size) {
                $parts[] = intdiv($seconds, $size) . $unit;
                $seconds %= $size;
            }
        }
        return implode(' ', array_slice($parts, 0, 2)) ?: '0s';
    }

    public static function formatBytes($bytes)
    {
        $bytes = (float)$bytes;
        if ($bytes <= 0) return '0';
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /* ==========================================================
     * Status
     * ========================================================== */

    /**
     * Voucher status from the user row (+ optional active session row).
     * Codes: unused / active / idle / exhausted / expired / disabled.
     */
    public static function getStatus($user, $activeRow = null)
    {
        if (($user['disabled'] ?? 'false') === 'true') {
            return array(
                'code' => 'disabled',
                'label' => 'Nonaktif',
                'icon' => '⛔',
                'hint' => 'User dinonaktifkan di router'
            );
        }

        if ($activeRow !== null) {
            return array(
                'code' => 'active',
                'label' => 'Aktif',
                'icon' => '🟢',
                'hint' => 'Sedang online'
            );
        }

        // Expiry date in the comment wins over usage counters.
        $parsed = self::parseComment($user['comment'] ?? '');
        if (!empty($parsed['expiry'])) {
            $exp = self::commentDateToTs($parsed['expiry']);
            if ($exp !== null && $exp < time()) {
                return array(
                    'code' => 'expired',
                    'label' => 'Kedaluwarsa',
                    'icon' => '🔴',
                    'hint' => 'Tanggal kadaluarsa terlewati (' . $parsed['expiry'] . ')'
                );
            }
        }

        $uptime = self::parseDuration($user['uptime'] ?? '');
        $limitUptime = self::parseDuration($user['limit-uptime'] ?? '');

        $bytesUsed = ((float)($user['bytes-in'] ?? 0)) + ((float)($user['bytes-out'] ?? 0));
        $limitBytes = (float)($user['limit-bytes-total'] ?? 0);

        $timeUp = ($limitUptime !== null && $limitUptime > 0 && $uptime !== null && $uptime >= $limitUptime);
        $dataUp = ($limitBytes > 0 && $bytesUsed >= $limitBytes);
        if ($timeUp || $dataUp) {
            return array(
                'code' => 'exhausted',
                'label' => 'Habis',
                'icon' => '🔴',
                'hint' => $timeUp ? 'Batas uptime tercapai' : 'Batas kuota tercapai'
            );
        }

        if ($uptime === null || $uptime === 0) {
            return array(
                'code' => 'unused',
                'label' => 'Belum Terpakai',
                'icon' => '⚪',
                'hint' => 'Voucher belum pernah login'
            );
        }

        return array(
            'code' => 'idle',
            'label' => 'Tidak Online',
            'icon' => '🔵',
            'hint' => 'Sudah dipakai, sedang offline'
        );
    }

    /**
     * MM/DD/YY or M.D.Y to unix timestamp. 2-digit years roll to 20xx.
     */
    private static function commentDateToTs($date)
    {
        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/', $date, $m)) {
            $month = (int)$m[1];
            $day = (int)$m[2];
            $year = (int)$m[3];
            if ($year < 100) $year += 2000;
            return mktime(23, 59, 59, $month, $day, $year);
        }
        return null;
    }

    /* ==========================================================
     * Lookup
     * ========================================================== */

    /**
     * Find one hotspot user by exact name, then unique partial match.
     * Same contract as MikhmonBilling::resolveSecret: [user|null, error].
     */
    public static function resolveUser($API, $query)
    {
        $query = trim((string)$query);
        if ($query === '') {
            return array(null, 'Kode voucher kosong.');
        }

        $exact = $API->comm('/ip/hotspot/user/print', array('?name' => $query));
        if (is_array($exact) && !empty($exact[0])) {
            return array($exact[0], '');
        }

        $all = $API->comm('/ip/hotspot/user/print');
        if (!is_array($all)) {
            return array(null, 'Gagal membaca data dari RouterOS.');
        }

        $needle = strtolower($query);
        $matches = array();
        foreach ($all as $row) {
            $name = strtolower($row['name'] ?? '');
            if ($name === '') continue;
            if ($name === $needle || strpos($name, $needle) === 0 || strpos($name, $needle) !== false) {
                $matches[] = $row;
            }
        }

        if (count($matches) === 1) {
            return array($matches[0], '');
        }
        if (count($matches) > 1) {
            $names = array();
            foreach (array_slice($matches, 0, 5) as $m) {
                $names[] = $m['name'];
            }
            return array(null, "'{$query}' cocok dengan " . count($matches) . ' voucher: '
                . implode(', ', $names) . (count($matches) > 5 ? ', ...' : '')
                . '. Tulis kode lengkapnya.');
        }

        return array(null, "Voucher '{$query}' tidak ditemukan.");
    }

    public static function listUsers($API, $profile = null)
    {
        $rows = $API->comm('/ip/hotspot/user/print');
        if (!is_array($rows)) {
            return array();
        }
        $profile = trim((string)$profile);
        if ($profile === '' || strcasecmp($profile, 'all') === 0) {
            return $rows;
        }
        $out = array();
        foreach ($rows as $row) {
            if (($row['profile'] ?? '') === $profile) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Map name => active row for quick online checks.
     */
    public static function listActiveMap($API)
    {
        $rows = $API->comm('/ip/hotspot/active/print');
        $map = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[$row['user'] ?? ''] = $row;
            }
        }
        return $map;
    }

    /**
     * Recap voucher counts per status, optionally filtered by profile.
     * @return array{counts, users}
     */
    public static function summarize($API, $profile = null)
    {
        $users = self::listUsers($API, $profile);
        $activeMap = self::listActiveMap($API);

        $counts = array('unused' => 0, 'active' => 0, 'idle' => 0, 'exhausted' => 0, 'expired' => 0, 'disabled' => 0);
        $rows = array();
        foreach ($users as $u) {
            // Default hotspot user entries (name "admin") are not vouchers.
            $stat = self::getStatus($u, $activeMap[$u['name'] ?? ''] ?? null);
            if (isset($counts[$stat['code']])) {
                $counts[$stat['code']]++;
            }
            $rows[] = array(
                'name' => $u['name'] ?? '',
                'profile' => $u['profile'] ?? '',
                'status' => $stat
            );
        }
        return array('counts' => $counts, 'users' => $rows, 'profiles' => self::listProfiles($API));
    }

    public static function listProfiles($API)
    {
        $rows = $API->comm('/ip/hotspot/user/profile/print');
        $names = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!empty($row['name'])) {
                    $names[] = $row['name'];
                }
            }
        }
        return $names;
    }

    /* ==========================================================
     * Write Actions
     * ========================================================== */

    /**
     * Remove active session(s) of a voucher name. Returns count removed.
     */
    public static function killSession($API, $username)
    {
        $count = 0;
        $actives = $API->comm('/ip/hotspot/active/print', array('?user' => $username));
        if (is_array($actives)) {
            foreach ($actives as $act) {
                if (isset($act['.id'])) {
                    $API->comm('/ip/hotspot/active/remove', array('.id' => $act['.id']));
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function resetCounters($API, $userId)
    {
        $API->comm('/ip/hotspot/user/reset-counters', array('.id' => $userId));
        self::logEvent('Telegram', 'hs_reset', $userId, '');
        self::emitLiveEvent('hs_reset', $userId, 'counter direset');
        return true;
    }

    public static function deleteUser($API, $userId)
    {
        $API->comm('/ip/hotspot/user/remove', array('.id' => $userId));
        self::logEvent('Telegram', 'hs_delete', $userId, '');
        self::emitLiveEvent('hs_delete', $userId, 'voucher dihapus');
        return true;
    }

    /**
     * Generate vouchers. Reuses the router-side conventions of the Mikhmon
     * web generator (vc mode: user == password, comment mode-batch-m.d.y).
     *
     * @param array $opts server, prefix, length, extra_comment
     * @return array{success, message, vouchers: array{name,password}}
     */
    public static function generateVouchers($API, $profile, $qty, $opts = array())
    {
        $qty = max(1, (int)$qty);
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string)($opts['prefix'] ?? ''));
        $length = max(3, min(12, (int)($opts['length'] ?? 6)));
        $server = (string)($opts['server'] ?? 'all');
        $extra = trim((string)($opts['extra_comment'] ?? ''));

        $existing = array();
        foreach ($API->comm('/ip/hotspot/user/print') as $u) {
            $existing[strtolower($u['name'] ?? '')] = true;
        }

        $batch = (string)rand(100, 999);
        $comment = 'vc-' . $batch . '-' . date('m.d.y');
        if ($extra !== '') {
            $comment .= '-' . str_replace(array('|', '-', ' '), array('', '-', '_'), $extra);
        }

        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $vouchers = array();
        for ($i = 0; $i < $qty; $i++) {
            // Random unique name, retry when colliding with existing users.
            $name = '';
            for ($try = 0; $try < 20; $try++) {
                $candidate = $prefix;
                for ($c = 0; $c < $length; $c++) {
                    $candidate .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                if (!isset($existing[strtolower($candidate)])) {
                    $name = $candidate;
                    break;
                }
            }
            if ($name === '') {
                return array('success' => false, 'message' => 'Gagal membuat nama voucher unik.', 'vouchers' => array());
            }
            $existing[strtolower($name)] = true;
            $vouchers[] = array('name' => $name, 'password' => $name);
        }

        foreach ($vouchers as $v) {
            $payload = array(
                'name' => $v['name'],
                'password' => $v['password'],
                'profile' => routeros_profile_id($API, 'hotspot', $profile),
                'comment' => $comment
            );
            if ($server !== '' && strcasecmp($server, 'all') !== 0) {
                $payload['server'] = $server;
            }
            $API->comm('/ip/hotspot/user/add', $payload);
        }

        self::logEvent('Telegram', 'hs_gen', $profile, "{$qty} voucher, batch {$batch}");
        self::emitLiveEvent('hs_gen', $profile, "{$qty} voucher (batch {$batch})");

        return array(
            'success' => true,
            'message' => "{$qty} voucher dibuat pada profil {$profile} (batch {$batch}).",
            'vouchers' => $vouchers,
            'comment' => $comment
        );
    }

    /* ==========================================================
     * Event Logging — reuse the shared tables/live stream
     * ========================================================== */

    public static function logEvent($tg_id, $action, $target, $detail = '')
    {
        MikhmonBilling::logEvent($tg_id, $action, $target, $detail);
    }

    public static function emitLiveEvent($action, $target, $detail = '')
    {
        MikhmonBilling::emitLiveEvent($action, $target, $detail);
    }
}
