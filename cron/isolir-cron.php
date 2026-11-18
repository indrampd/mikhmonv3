<?php
/*
 * Entry CLI mesin isolir — dipanggil dari host cron:
 *   docker exec mikhmon php /var/www/cron/isolir-cron.php [--dry-run] [--summary] [--no-notify]
 * Tidak boleh diakses via web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

// include/config.php (fallback kredensial router) membaca REQUEST_URI; di CLI
// kunci itu tidak ada dan hanya memunculkan warning di log cron.
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '';

require_once dirname(__DIR__) . '/lib/isolir_engine.class.php';

MikhmonIsolirEngine::run(array(
    'dry_run' => in_array('--dry-run', $argv, true),
    'summary' => in_array('--summary', $argv, true),
    'notify' => !in_array('--no-notify', $argv, true)
));
