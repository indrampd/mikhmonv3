<?php
/*
 * Server-Sent Events (SSE) for Real-Time Mikhmon Billing Updates
 */

session_start();
if (!isset($_SESSION["mikhmon"])) {
    http_response_code(401);
    exit();
}
// Release session lock immediately so parallel HTTP requests and user clicks never hang
session_write_close();

// Disable compression & buffering
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$event_file = dirname(__DIR__) . '/data/live_event.json';
$last_seen = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (float)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
if (isset($_GET['last_ts'])) {
    $last_seen = (float)$_GET['last_ts'];
}

$start_time = time();
$max_seconds = 25; // Close bounded stream cleanly; browser will reconnect automatically

echo ": connected\n\n";
flush();

while (true) {
    if (connection_aborted() || (time() - $start_time) >= $max_seconds) {
        break;
    }

    if (file_exists($event_file)) {
        $content = @file_get_contents($event_file);
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['ts'])) {
            $ts = (float)$data['ts'];
            if ($ts > $last_seen) {
                $last_seen = $ts;
                echo "id: {$ts}\n";
                echo "event: billing\n";
                echo "data: " . json_encode($data) . "\n\n";
                flush();
            }
        }
    }

    // Keepalive heartbeat
    echo ": ping\n\n";
    flush();

    usleep(1000000); // 1 second interval
}
