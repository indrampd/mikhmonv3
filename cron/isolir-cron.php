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

require_once dirname(__DIR__) . '/lib/isolir_engine.class.php';

MikhmonIsolirEngine::run(array(
    'dry_run' => in_array('--dry-run', $argv, true),
    'summary' => in_array('--summary', $argv, true),
    'notify' => !in_array('--no-notify', $argv, true)
));
