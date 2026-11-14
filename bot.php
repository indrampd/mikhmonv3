<?php
/*
 * Telegram Webhook Entry Point for Mikhmon v3
 */

$log_dir = __DIR__ . '/data';
$log_file = $log_dir . '/telegram-bot.log';

// The webhook answers before the command runs, so Telegram will never retry a
// failed update. Silencing errors here would make those failures invisible:
// write them to data/telegram-bot.log instead and keep output off.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);

$log_line = function ($level, $message) use ($log_file) {
    if (@filesize($log_file) > 1048576) {          // rotate at 1 MiB
        @rename($log_file, $log_file . '.1');
    }
    @file_put_contents(
        $log_file,
        sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $level, $message),
        FILE_APPEND | LOCK_EX
    );
};

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($log_line) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $log_line('PHP', "{$errstr} in {$errfile}:{$errline}");
    return true;
});

set_exception_handler(function ($e) use ($log_line) {
    $log_line('EXCEPTION', get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
});

require_once __DIR__ . '/lib/telegram_bot.class.php';

$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(200);
    echo "Mikhmon Telegram Bot Webhook Active.";
    exit();
}

$bot = new MikhmonTelegramBot();
$config = MikhmonBilling::getConfig();

// Verify secret token if configured
$expected_secret = $config['webhook_secret'] ?? '';
if (!empty($expected_secret)) {
    $incoming_secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? $_GET['secret'] ?? '';
    if ($incoming_secret !== $expected_secret) {
        http_response_code(403);
        echo "Unauthorized secret token";
        exit();
    }
}

$update = json_decode($raw, true);

// Acknowledge before touching RouterOS or the Telegram API. Telegram only
// cares that the update was received; holding the response open for the full
// command made webhook requests take 1.4s and, when the router stalled, left
// FPM workers busy long enough to trigger retries.
http_response_code(200);
echo "OK";

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (is_array($update)) {
    $log_line('UPDATE', sprintf('id=%s chat=%s text=%s',
        $update['update_id'] ?? '-',
        $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? '-',
        $update['message']['text'] ?? $update['callback_query']['data'] ?? '-'
    ));
    try {
        $bot->handleUpdate($update);
    } catch (Throwable $e) {
        $log_line('FATAL', get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
}
