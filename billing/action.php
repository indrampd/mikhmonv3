<?php
/*
 * Billing AJAX Action Handler for Mikhmon v3
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["mikhmon"])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Unauthorized'));
    exit();
}

$session = $_POST['session'] ?? $_GET['session'] ?? '';
$action  = $_POST['action']  ?? $_GET['action']  ?? '';
$target  = $_POST['target']  ?? $_GET['target']  ?? '';

if (empty($session) || empty($action) || empty($target)) {
    echo json_encode(array('success' => false, 'message' => 'Missing parameter'));
    exit();
}

include_once dirname(__DIR__) . '/include/config.php';
include_once dirname(__DIR__) . '/lib/routeros_api.class.php';
include_once dirname(__DIR__) . '/lib/billing.class.php';

if (!isset($data[$session])) {
    echo json_encode(array('success' => false, 'message' => 'Invalid session'));
    exit();
}

$c = $data[$session];
$ip = explode('!', $c[1])[1] ?? '';
$user = explode('@|@', $c[2])[1] ?? '';
$pass = decrypt(explode('#|#', $c[3])[1] ?? '');

$API = new RouterosAPI();
if (!$API->connect($ip, $user, $pass)) {
    echo json_encode(array('success' => false, 'message' => 'Gagal terhubung ke router'));
    exit();
}

$res = array('success' => false, 'message' => 'Unknown action');

if ($action === 'lunas') {
    $res = MikhmonBilling::markLunas($API, $target, 'Admin Web');
} elseif ($action === 'isolir') {
    $res = MikhmonBilling::markIsolir($API, $target, 'Admin Web');
} elseif ($action === 'pulih') {
    $res = MikhmonBilling::restorePaket($API, $target, 'Admin Web');
}

$API->disconnect();
echo json_encode($res);
