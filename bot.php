<?php
/*
 * Telegram Webhook Entry Point for Mikhmon v3
 */

ini_set('display_errors', '0');
error_reporting(0);

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
    $bot->handleUpdate($update);
}
