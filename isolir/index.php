<?php
/*
 * Portal Isolir — halaman publik (TANPA login admin) untuk pelanggan PPPoE
 * yang terisolir. Dijangkau lewat dst-nat MikroTik: subnet isolir
 * (192.168.6.0/24) port 80 -> server ini.
 *
 * Desain mengikuti tema halaman hotspot milik router (style.css + logo.png) agar
 * senada dengan halaman login hotspot. Aset memakai path ABSOLUT (/isolir/…)
 * karena dst-nat membuat browser mengira dirinya berada di root host mana pun
 * yang dituju pelanggan — path relatif akan 404.
 *
 * Identifikasi pengunjung: dst-nat menjaga source IP, jadi REMOTE_ADDR adalah
 * IP PPP pelanggan (dari pool isolir). IP dicocokkan ke sesi aktif PPP,
 * lalu ke secret -> komentar (nama, pkg:, lunas:) -> status tagihan.
 */

require_once dirname(__DIR__) . '/lib/routeros_api.class.php';
require_once dirname(__DIR__) . '/lib/billing.class.php';
require_once dirname(__DIR__) . '/lib/telegram_bot.class.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');

function ipInSubnet($ip, $cidr)
{
    if (strpos($cidr, '/') === false) return $ip === $cidr;
    list($net, $mask) = explode('/', $cidr, 2);
    return (ip2long($ip) & ~((1 << (32 - (int)$mask)) - 1)) === (ip2long($net) & ~((1 << (32 - (int)$mask)) - 1));
}

function portalConfig()
{
    $cfg = MikhmonBilling::getConfig();
    $portal = $cfg['portal'] ?? array();
    return array(
        'contact' => trim((string)($portal['contact'] ?? 'Admin')),
        'phone' => trim((string)($portal['phone'] ?? '')),
        'message' => trim((string)($portal['message'] ?? '')),
        'network' => trim((string)($portal['network'] ?? '192.168.6.0/24')),
        'pool' => trim((string)($portal['pool'] ?? 'pppoe_pool_isolir')),
        'domain' => trim((string)($portal['domain'] ?? '')),
        'tagline' => trim((string)($portal['tagline'] ?? '')) !== '' ? trim((string)($portal['tagline'])) : 'Layanan wifi termurah'
    );
}

function waLink($phone, $text)
{
    $num = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($num, '0') === 0) $num = '62' . substr($num, 1);
    if ($num === '') return '';
    return 'https://wa.me/' . $num . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function pageStart($title)
{
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta http-equiv="refresh" content="60">';
    echo '<meta http-equiv="pragma" content="no-cache">';
    echo '<meta http-equiv="expires" content="-1">';
    echo '<link rel="icon" href="/isolir/favicon.ico">';
    echo '<link rel="stylesheet" href="/isolir/style.css">';
    echo '<title>' . htmlspecialchars($title) . '</title></head><body>';
    echo '<div class="shell"><main class="card">';
    echo '<header class="brand"><img src="/isolir/logo.png" alt="Logo layanan">';
    echo '<p>' . htmlspecialchars($config['tagline']) . '</p></header>';
}

function pageEnd()
{
    echo '</main><p class="foot">' . htmlspecialchars($GLOBALS['config']['tagline']) . ' &middot; Layanan diisolir otomatis &middot; diperbarui tiap 60 detik</p></div></body></html>';
}

function renderLead($nama, $paket, $bulan)
{
    echo '<div class="lead"><strong>' . htmlspecialchars($nama) . '</strong>';
    echo '<span>Paket ' . htmlspecialchars($paket) . ($bulan !== '' ? ' &middot; Tagihan ' . htmlspecialchars($bulan) : '') . '</span></div>';
}

$config = portalConfig();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Dari luar subnet isolir (mis. admin mengakses langsung): halaman generik.
if (!ipInSubnet($ip, $config['network'])) {
    pageStart('Layanan Terbatas');
    echo '<p class="desc" style="text-align:center;">Perangkat ini tidak berada dalam jangkauan layanan penuh.</p>';
    echo '<p class="hint">Silakan hubungi ' . htmlspecialchars($config['contact']) . ' untuk bantuan.</p>';
    pageEnd();
    exit;
}

$api = (new MikhmonTelegramBot())->getRouterAPI();
if (!$api) {
    pageStart('Gangguan Sistem');
    echo '<div class="notice" role="alert">Sistem sedang tidak dapat memverifikasi tagihan Anda.</div>';
    echo '<p class="hint">Hubungi ' . htmlspecialchars($config['contact']) . ($config['phone'] !== '' ? ' (' . htmlspecialchars($config['phone']) . ')' : '') . '.</p>';
    pageEnd();
    exit;
}

$act = $api->comm('/ppp/active/print', array('?address' => $ip));
$user = '';
if (is_array($act) && !empty($act[0]['name'])) {
    $user = $act[0]['name'];
}
if ($user === '') {
    // Sesinya belum terbentuk (baru re-dial): minta coba lagi.
    $api->disconnect();
    pageStart('Sesi Terputus');
    echo '<p class="desc" style="text-align:center;">Sesi internet Anda belum aktif.</p>';
    echo '<p class="hint">Silakan putus dan sambungkan kembali koneksi PPPoE, atau hubungi ' . htmlspecialchars($config['contact']) . '.</p>';
    pageEnd();
    exit;
}

$sec = $api->comm('/ppp/secret/print', array('?name' => $user));
$api->disconnect();

if (!is_array($sec) || empty($sec[0])) {
    pageStart('Layanan Terbatas');
    echo '<p class="desc" style="text-align:center;">Data pelanggan tidak ditemukan.</p>';
    echo '<p class="hint">Hubungi ' . htmlspecialchars($config['contact']) . '.</p>';
    pageEnd();
    exit;
}

$parsed = MikhmonBilling::parseComment($sec[0]['comment'] ?? '');
$stat = MikhmonBilling::getStatus($sec[0]['profile'] ?? '', $sec[0]['comment'] ?? '', MikhmonBilling::getConfig()['cutoff_day'] ?? 10);
$nama = $parsed['label'] !== '' ? $parsed['label'] : $user;
$paket = $parsed['pkg'] !== '' ? $parsed['pkg'] : '-';

if ($stat['code'] === 'isolir') {
    pageStart('Anda Diisolir');
    renderLead($nama, $paket, $parsed['lunas']);
    $msg = $config['message'] !== ''
        ? $config['message']
        : 'Layanan internet Anda diisolir sementara karena tagihan belum dibayar. Lakukan pembayaran, lalu layanan akan dipulihkan otomatis.';
    echo '<div class="notice" role="alert">' . htmlspecialchars($msg) . '</div>';
    $wa = waLink($config['phone'], "Halo " . $config['contact'] . ", saya " . $nama . " (" . $user . ") ingin membayar tagihan internet " . ($parsed['lunas'] ?: '') . ".");
    if ($wa !== '') {
        echo '<a class="btn btn-primary" href="' . htmlspecialchars($wa) . '">💬 Hubungi via WhatsApp</a>';
    }
    echo '<p class="hint">Setelah pembayaran dikonfirmasi, halaman ini memperbarui sendiri dalam 60 detik.</p>';
    echo '<div class="contact"><p>Catatan: situs https:// lain tidak dapat dibuka.'
        . ($config['domain'] !== '' ? ' Untuk membuka halaman ini via https://, gunakan alamat <b>' . htmlspecialchars($config['domain']) . '</b>.' : '')
        . ' Buka alamat http:// mana pun untuk melihat status.</p></div>';
    pageEnd();
} elseif ($stat['code'] === 'lunas' || $stat['code'] === 'tenggang') {
    pageStart('Pembayaran Diterima');
    renderLead($nama, $paket, $parsed['lunas']);
    echo '<p class="desc" style="text-align:center;">Pembayaran Anda sudah tercatat. Layanan dipulihkan otomatis maksimal 15 menit, atau putus &amp; sambungkan kembali PPPoE sekarang.</p>';
    pageEnd();
} else {
    pageStart('Status Tagihan Belum Lengkap');
    renderLead($nama, $paket, $parsed['lunas']);
    echo '<p class="desc" style="text-align:center;">Status tagihan Anda belum terverifikasi. Hubungi ' . htmlspecialchars($config['contact']) . ' agar layanan dapat dipulihkan.</p>';
    pageEnd();
}
