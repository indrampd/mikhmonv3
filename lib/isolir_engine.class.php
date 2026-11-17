<?php
/*
 * Isolir Engine — batch driver yang menggantikan script MikroTik
 * (isolir-pppoe + scheduler isolir-harian).
 *
 * Prinsip: scan /ppp/secret/print -> MikhmonBilling::getStatus() -> transisi:
 *   (a) status isolir  && profil live bukan isolir && ada pkg:  -> markIsolir
 *   (b) status lunas   && profil live isolir                    -> restorePaket
 * Idempoten: setelah apply, run berikutnya tidak menemukan transisi lagi.
 */

require_once __DIR__ . '/billing.class.php';
require_once __DIR__ . '/telegram_bot.class.php';

class MikhmonIsolirEngine
{
    const STATUS_FILE = __DIR__ . '/../data/isolir-status.json';
    const LOCK_FILE = __DIR__ . '/../data/isolir-cron.lock';

    /**
     * @param array $opts dry_run(bool), summary(bool), notify(bool)
     * @return int exit code (0 ok, 1 error)
     */
    public static function run($opts = array())
    {
        $dry = !empty($opts['dry_run']);
        $summary = !empty($opts['summary']);
        $notify = !isset($opts['notify']) || $opts['notify'];

        $t0 = microtime(true);

        $lock = self::acquireLock();
        if ($lock === null) {
            echo "[" . date('c') . "] Run sebelumnya masih berjalan, lewati.\n";
            return 0;
        }

        $config = MikhmonBilling::getConfig();

        $bot = new MikhmonTelegramBot();
        $api = $bot->getRouterAPI();
        if (!$api) {
            self::writeStatus(array(
                'ok' => false,
                'error' => 'Gagal terhubung ke RouterOS',
                'scanned' => 0,
                'isolir_applied' => array(),
                'restored' => array(),
                'errors' => array(),
                'dry_run' => $dry
            ), $t0);
            echo "[" . date('c') . "] ERROR: tidak bisa terhubung ke router.\n";
            self::releaseLock($lock);
            return 1;
        }

        list($transitions, $scanned, $scanErrors) = self::scan($api, $config);
        $result = array('isolir' => array(), 'restore' => array(), 'errors' => $scanErrors);

        if (!$dry) {
            $result = self::apply($api, $transitions, $config);
        }
        $api->disconnect();

        $isolirNames = array();
        $restoredNames = array();
        foreach ($transitions as $tr) {
            if ($tr['action'] === 'isolir') $isolirNames[] = $tr['user'];
            else $restoredNames[] = $tr['user'];
        }

        self::writeStatus(array(
            'ok' => empty($result['errors']),
            'scanned' => $scanned,
            'isolir_applied' => $isolirNames,
            'restored' => $restoredNames,
            'errors' => $result['errors'],
            'dry_run' => $dry
        ), $t0);

        $duration = (int)round((microtime(true) - $t0) * 1000);
        echo "[" . date('c') . "] scan={$scanned} isolir=" . count($isolirNames)
            . " pulih=" . count($restoredNames)
            . " error=" . count($result['errors'])
            . " ({$duration}ms)" . ($dry ? " [DRY-RUN]" : "") . "\n";
        foreach ($result['errors'] as $e) {
            echo "  ERROR: {$e}\n";
        }

        if ($notify && ($summary || !empty($isolirNames) || !empty($restoredNames) || !empty($result['errors']))) {
            $html = self::buildSummary($config, $scanned, $isolirNames, $restoredNames, $result['errors'], $dry);
            foreach (($config['owner_ids'] ?? array()) as $oid) {
                $bot->sendMessage($oid, $html);
            }
        }

        self::releaseLock($lock);
        return empty($result['errors']) ? 0 : 1;
    }

    /**
     * Scan semua secret, kembalikan [transisi[], jumlahScanned, errorScan[]].
     */
    public static function scan($API, $config)
    {
        $secrets = $API->comm('/ppp/secret/print');
        if (!is_array($secrets)) {
            return array(array(), 0, array('Router tidak mengembalikan daftar secret.'));
        }

        $cutoff = (int)($config['cutoff_day'] ?? 10);
        $isolirProfile = $config['isolir_profile'] ?? MikhmonBilling::DEFAULT_ISOLIR;
        $transitions = array();
        $errors = array();

        foreach ($secrets as $s) {
            if (($s['disabled'] ?? 'false') === 'true') continue;
            $name = $s['name'] ?? '';
            if ($name === '') continue;

            $st = MikhmonBilling::getStatus($s['profile'] ?? '', $s['comment'] ?? '', $cutoff);
            $parsed = MikhmonBilling::parseComment($s['comment'] ?? '');
            $liveIsolir = MikhmonBilling::isIsolirProfile($s['profile'] ?? '', $isolirProfile);

            // (a) Menunggak dan belum terisolir.
            if ($st['code'] === 'isolir' && !$liveIsolir && $parsed['pkg'] !== '') {
                $transitions[] = array(
                    'action' => 'isolir',
                    'user' => $name,
                    'pkg' => $parsed['pkg'],
                    'lunas' => $parsed['lunas'] ?: '-',
                    'cutoff' => $parsed['tgl'] ?: $cutoff
                );
            }

            // (b) Sudah bayar bulan berjalan tapi masih terisolir.
            if ($st['code'] === 'lunas' && $liveIsolir) {
                $transitions[] = array(
                    'action' => 'restore',
                    'user' => $name,
                    'pkg' => $parsed['pkg'] ?: '-',
                    'lunas' => $parsed['lunas'] ?: '-',
                    'cutoff' => ''
                );
            }
        }

        return array($transitions, count($secrets), $errors);
    }

    /**
     * Terapkan transisi via markIsolir/restorePaket (mereka yang logging).
     */
    public static function apply($API, $transitions, $config)
    {
        $result = array('isolir' => array(), 'restore' => array(), 'errors' => array());
        foreach ($transitions as $tr) {
            if ($tr['action'] === 'isolir') {
                $res = MikhmonBilling::markIsolir($API, $tr['user'], 'Cron Isolir', 'otomatis (menunggak)');
                if ($res['success']) {
                    $result['isolir'][] = $tr['user'];
                } else {
                    $result['errors'][] = "isolir {$tr['user']}: {$res['message']}";
                }
            } else {
                $res = MikhmonBilling::restorePaket($API, $tr['user'], 'Cron Isolir', 'otomatis (pembayaran terkonfirmasi)');
                if ($res['success']) {
                    $result['restore'][] = $tr['user'];
                } else {
                    $result['errors'][] = "pulih {$tr['user']}: {$res['message']}";
                }
            }
        }
        return $result;
    }

    /**
     * Ringkasan HTML untuk Telegram.
     */
    public static function buildSummary($config, $scanned, $isolirNames, $restoredNames, $errors, $dry)
    {
        $html = "⏰ <b>Laporan Mesin Isolir</b>" . ($dry ? " <i>(DRY-RUN)</i>" : "") . "\n";
        $html .= "━━━━━━━━━━━━━━━━━━━\n";
        $html .= "Pelanggan dipindai : <b>{$scanned}</b>\n";
        $html .= "Baru diisolir      : <b>" . count($isolirNames) . "</b>\n";
        $html .= "Dipulihkan         : <b>" . count($restoredNames) . "</b>\n";

        if (!empty($isolirNames)) {
            $html .= "\n<b>🔴 Diisolir:</b>\n";
            foreach (array_slice($isolirNames, 0, 20) as $n) $html .= "• <code>" . htmlspecialchars($n) . "</code>\n";
            if (count($isolirNames) > 20) $html .= "<i>... dan " . (count($isolirNames) - 20) . " lainnya</i>\n";
        }
        if (!empty($restoredNames)) {
            $html .= "\n<b>✅ Dipulihkan:</b>\n";
            foreach (array_slice($restoredNames, 0, 20) as $n) $html .= "• <code>" . htmlspecialchars($n) . "</code>\n";
            if (count($restoredNames) > 20) $html .= "<i>... dan " . (count($restoredNames) - 20) . " lainnya</i>\n";
        }
        if (!empty($errors)) {
            $html .= "\n<b>⚠️ Error:</b>\n";
            foreach (array_slice($errors, 0, 10) as $e) $html .= "• " . htmlspecialchars($e) . "\n";
        }
        return $html;
    }

    /**
     * Flock non-blocking; return resource handle atau null bila run lain
     * sedang memegang lock.
     */
    public static function acquireLock()
    {
        $fp = fopen(self::LOCK_FILE, 'c');
        if (!$fp) return null;
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    public static function releaseLock($fp)
    {
        if (is_resource($fp)) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Tulis status atomik (tmp -> rename) untuk dashboard.
     */
    public static function writeStatus($payload, $t0 = null)
    {
        $payload['last_run'] = time();
        $payload['last_run_h'] = date('Y-m-d H:i');
        if ($t0 !== null) {
            $payload['duration_ms'] = (int)round((microtime(true) - $t0) * 1000);
        }
        $tmp = self::STATUS_FILE . '.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, self::STATUS_FILE);
    }
}
