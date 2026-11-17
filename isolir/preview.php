<?php
/*
 * PREVIEW portal isolir — file sementara untuk melihat tampilan tanpa harus
 * punya pelanggan terisolir nyata. Hapus file ini setelah desain disetujui.
 * Desain senada dengan tema halaman hotspot router (style.css + logo.png).
 *   /isolir/preview.php           -> varian "Anda Diisolir"
 *   /isolir/preview.php?v=paid    -> varian "Pembayaran Diterima"
 *   /isolir/preview.php?v=generic -> varian IP tidak dikenal
 */

header('Content-Type: text/html; charset=utf-8');

$v = $_GET['v'] ?? 'isolir';
$wa = 'https://wa.me/6281234567890?text=' . rawurlencode('Halo Admin, saya Budi Santoso (budi) ingin membayar tagihan internet 2026-11.');

pageStart('PREVIEW: ' . ($v === 'paid' ? 'Pembayaran Diterima' : ($v === 'generic' ? 'Layanan Terbatas' : 'Anda Diisolir')));

function renderLead($nama, $paket, $bulan)
{
    echo '<div class="lead"><strong>' . htmlspecialchars($nama) . '</strong>';
    echo '<span>Paket ' . htmlspecialchars($paket) . ($bulan !== '' ? ' &middot; Tagihan ' . htmlspecialchars($bulan) : '') . '</span></div>';
}

if ($v === 'paid') {
    renderLead('Budi Santoso', 'pppoe-P20', '2026-11');
    echo '<p class="desc" style="text-align:center;">Pembayaran Anda sudah tercatat. Layanan dipulihkan otomatis maksimal 15 menit, atau putus &amp; sambungkan kembali PPPoE sekarang.</p>';
} elseif ($v === 'generic') {
    echo '<p class="desc" style="text-align:center;">Perangkat ini tidak berada dalam jangkauan layanan penuh.</p>';
    echo '<p class="hint">Silakan hubungi Admin untuk bantuan.</p>';
} else {
    renderLead('Budi Santoso', 'pppoe-P20', '2026-11');
    echo '<div class="notice" role="alert">Layanan internet Anda diisolir sementara karena tagihan belum dibayar. Lakukan pembayaran, lalu layanan akan dipulihkan otomatis.</div>';
    echo '<a class="btn btn-primary" href="' . htmlspecialchars($wa) . '">💬 Hubungi via WhatsApp</a>';
    echo '<p class="hint">Setelah pembayaran dikonfirmasi, halaman ini memperbarui sendiri dalam 60 detik.</p>';
    echo '<div class="contact"><p>Catatan: situs https:// mungkin tidak menampilkan halaman ini &mdash; buka alamat http:// untuk melihat status.</p></div>';
}

pageEnd();

function pageStart($title)
{
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<link rel="icon" href="/isolir/favicon.ico">';
    echo '<link rel="stylesheet" href="/isolir/style.css">';
    echo '<title>' . htmlspecialchars($title) . '</title></head><body>';
    echo '<div class="shell"><main class="card">';
    echo '<header class="brand"><img src="/isolir/logo.png" alt="Logo layanan">';
    echo '<p>' . portalTagline() . '</p></header>';
}

function pageEnd()
{
    echo '</main><p class="foot">' . portalTagline() . ' &middot; PREVIEW</p></div></body></html>';
}

function portalTagline()
{
    $f = dirname(__DIR__) . '/data/bot_config.json';
    $c = is_readable($f) ? json_decode((string)@file_get_contents($f), true) : array();
    $t = trim((string)($c['portal']['tagline'] ?? ''));
    return htmlspecialchars($t !== '' ? $t : 'Layanan wifi termurah');
}
