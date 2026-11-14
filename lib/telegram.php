<?php
/**
 * Telegram Notification Helper for Mikhmon v3
 */

function sendTelegramNotification($token, $chat_id, $message)
{
    if (empty($token) || empty($chat_id) || empty($message)) {
        return false;
    }

    $url = "https://api.telegram.org/bot" . trim($token) . "/sendMessage";
    $payload = array(
        'chat_id'                  => trim($chat_id),
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("[Telegram Notification] cURL error: " . $err);
        return false;
    }

    $json = json_decode($res, true);
    return ($json && isset($json['ok']) && $json['ok'] === true);
}
