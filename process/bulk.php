<?php
/*
 * Handler aksi BULK (checkbox) untuk PPP Secrets & Hotspot Users.
 * Di-include oleh index.php pada POST dengan parameter bulk_action,
 * SEBELUM output apa pun, lalu exit — respons JSON untuk fetch().
 *
 *   bulk_scope : ppp | hotspot
 *   bulk_action: enable | disable | remove | lunas (lunas hanya ppp)
 *   ids[]      : .id RouterOS tiap baris terpilih
 *   names[]    : nama user tiap baris terpilih (untuk markLunas)
 */

header('Content-Type: application/json');

$action = $_POST['bulk_action'] ?? '';
$scope = $_POST['bulk_scope'] ?? '';
$ids = array_values((array)($_POST['ids'] ?? array()));
$names = array_values((array)($_POST['names'] ?? array()));

if ($action === '' || $scope === '' || empty($ids)) {
    echo json_encode(array('success' => false, 'message' => 'Tidak ada data yang dikirim.'));
    exit;
}

if (empty($API) || empty($API->connected)) {
    echo json_encode(array('success' => false, 'message' => 'Router tidak terhubung.'));
    exit;
}

$ok = 0;
$fail = 0;

// Peta scope => aksi => perintah RouterOS tunggal per .id
$commands = array(
    'ppp' => array(
        'enable'  => '/ppp/secret/enable',
        'disable' => '/ppp/secret/disable',
        'remove'  => '/ppp/secret/remove',
    ),
    'hotspot' => array(
        'enable'  => '/ip/hotspot/user/enable',
        'disable' => '/ip/hotspot/user/disable',
        'remove'  => '/ip/hotspot/user/remove',
    ),
    'host' => array(
        'remove' => '/ip/hotspot/host/remove',
    ),
    'cookie' => array(
        'remove' => '/ip/hotspot/cookie/remove',
    ),
    'hs-active' => array(
        'kick' => '/ip/hotspot/active/remove',
    ),
    'ppp-active' => array(
        'kick' => '/ppp/active/remove',
    ),
    'dhcp-lease' => array(
        'remove' => '/ip/dhcp-server/lease/remove',
    ),
    'ip-binding' => array(
        'enable'  => '/ip/hotspot/ip-binding/enable',
        'disable' => '/ip/hotspot/ip-binding/disable',
        'remove'  => '/ip/hotspot/ip-binding/remove',
    ),
    'scheduler' => array(
        'enable'  => '/system/scheduler/enable',
        'disable' => '/system/scheduler/disable',
        'remove'  => '/system/scheduler/remove',
    ),
    'hs-profile' => array(
        'remove' => '/ip/hotspot/user/profile/remove',
    ),
    'ppp-profile' => array(
        'remove' => '/ppp/profile/remove',
    ),
    'selling' => array(
        'remove' => '/system/script/remove',
    ),
);

// Perintah tunggal RouterOS yang aman dipanggil per .id
$single = function($cmd) use ($API, &$ok, &$fail, $ids) {
    foreach ($ids as $id) {
        $res = $API->comm($cmd, array('.id' => $id));
        if ($res !== false) { $ok++; } else { $fail++; }
    }
};

if ($scope === 'ppp' && $action === 'lunas') {
    require_once dirname(__DIR__) . '/lib/billing.class.php';
    // Tandai lunas per username (aktor bulk), pulihkan paket asal ikut.
    foreach ($names as $name) {
        if ($name === '' || $name === null) { continue; }
        MikhmonBilling::markLunas($API, (string)$name, 'Admin Web (bulk)');
        $ok++;
    }
} elseif (isset($commands[$scope][$action])) {
    $single($commands[$scope][$action]);
} else {
    echo json_encode(array('success' => false, 'message' => 'Scope/aksi tidak dikenal.'));
    exit;
}

echo json_encode(array(
    'success' => true,
    'ok' => $ok,
    'fail' => $fail,
    'message' => $ok . ' berhasil' . ($fail > 0 ? ', ' . $fail . ' gagal' : ''),
));
exit;
