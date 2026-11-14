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
if (is_array($update)) {
    $bot->handleUpdate($update);
}

http_response_code(200);
echo "OK";
