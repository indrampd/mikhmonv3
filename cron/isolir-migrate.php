<?php
/*
 * Migrasi router sekali jalan untuk portal isolir — CLI only.
 *   --check : tampilkan rencana perubahan + kondisi saat ini (tanpa menulis)
 *   --apply : eksekusi (idempoten: cek dulu sebelum buat, re-run => SKIP)
 *
 * Nilai diambil dari seksi "portal" di data/bot_config.json, bisa ditimpa
 * lewat argumen CLI (--server-ip=... --server-mac=... --domain=... dst).
 * Jika portal.domain kosong, langkah HTTPS (7-8) dilewati.
 *
 * Langkah:
 *   1. /ip pool add <pool> (rentang dari <network>)
 *   2. Static DHCP lease server (hanya bila server ada di jaringan DHCP router)
 *   3. /ppp profile set <isolir_profile> remote-address=<pool>
 *   4. /ip firewall nat add dst-nat <network>:80 -> server:<server_port>
 *   5. /system scheduler set isolir-harian disabled=yes (jika masih ada)
 *   6. /ip firewall nat add redirect <network>:53 -> router (DNS)
 *   7. /ip firewall nat add dst-nat <network>:443 -> server:443 (portal HTTPS)
 *   8. /ip dns static add <domain> -> <server_ip>
 * Sebelum menulis: /export file=pre-isolir-migration (backup).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

// include/config.php (fallback kredensial router) membaca REQUEST_URI; di CLI
// kunci itu tidak ada dan hanya memunculkan warning di output migrasi.
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '';

require_once dirname(__DIR__) . '/lib/billing.class.php';
require_once dirname(__DIR__) . '/lib/isolir_engine.class.php';

// Nilai kontrak NAT/DNS statis antara script ini, portal, dan dashboard.
const MIG_NAT_COMMENT = 'isolir-portal';
const MIG_HTTPS_NAT_COMMENT = 'isolir-https';
const MIG_DNS_NAT_COMMENT = 'isolir-dns';
const MIG_SCHEDULER = 'isolir-harian';

// --- Parameter: config portal di data/bot_config.json + override CLI ---
$portalCfg = MikhmonBilling::getConfig()['portal'] ?? array();
$opt = getopt('', array('check', 'apply', 'server-ip:', 'server-mac:', 'domain:', 'network:', 'pool:', 'port:', 'ranges:', 'profile:'));
$optVal = function ($key, $cfgKey, $default = '') use ($opt, $portalCfg) {
    if (isset($opt[$key]) && $opt[$key] !== false) return trim((string)$opt[$key]);
    return trim((string)($portalCfg[$cfgKey] ?? $default));
};

MIG_define('MIG_SERVER_IP', $optVal('server-ip', 'server_ip'));
MIG_define('MIG_SERVER_PORT', $optVal('port', 'server_port', '8081'));
MIG_define('MIG_SERVER_MAC', $optVal('server-mac', 'server_mac'));
MIG_define('MIG_PORTAL_DOMAIN', $optVal('domain', 'domain'));
MIG_define('MIG_NETWORK', $optVal('network', 'network', '192.168.6.0/24'));
MIG_define('MIG_POOL', $optVal('pool', 'pool', 'pppoe_pool_isolir'));
MIG_define('MIG_PROFILE', $optVal('profile', 'profile', MikhmonBilling::getConfig()['isolir_profile'] ?? 'pppoe-ISOLIR'));

function MIG_define($name, $value) { if (!defined($name)) define($name, $value); }

// Apakah $ip berada di subnet $cidr? Dipakai untuk memutuskan perlu-tidaknya
// lease DHCP statis: hanya server yang IP-nya dari DHCP LAN yang butuh lease.
function MIG_ip_in_cidr($ip, $cidr) {
    $parts = explode('/', trim((string)$cidr), 2);
    if (count($parts) !== 2) return false;
    $ip_long = ip2long($ip);
    $net_long = ip2long($parts[0]);
    $bits = (int)$parts[1];
    if ($ip_long === false || $net_long === false || $bits < 0 || $bits > 32) return false;
    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
    return ($ip_long & $mask) === ($net_long & $mask);
}

if (MIG_SERVER_IP === '') {
    echo "ERROR: server_ip wajib diisi.\n";
    echo "Isi di dashboard (Billing & Bot Telegram -> Portal Isolir), atau seksi 'portal'\n";
    echo "di data/bot_config.json, atau kirim --server-ip=<ip>.\n";
    exit(1);
}

// Rentang pool: dari config portal.ranges, atau turunan otomatis dari subnet /24 (.10-.254).
if (isset($opt['ranges']) && $opt['ranges'] !== false) {
    MIG_define('MIG_RANGES', trim((string)$opt['ranges']));
} elseif (($portalCfg['ranges'] ?? '') !== '') {
    MIG_define('MIG_RANGES', trim((string)$portalCfg['ranges']));
} elseif (preg_match('#^(\d+\.\d+\.\d+)\.0/24$#', MIG_NETWORK, $m)) {
    MIG_define('MIG_RANGES', $m[1] . '.10-' . $m[1] . '.254');
} else {
    echo "ERROR: subnet \"" . MIG_NETWORK . "\" bukan /24 — tentukan rentang pool via\n";
    echo "portal.ranges di data/bot_config.json atau argumen --ranges=<ip1>-<ip2>.\n";
    exit(1);
}

$apply = in_array('--apply', $argv, true);
if (!$apply && !in_array('--check', $argv, true)) {
    echo "Penggunaan: php isolir-migrate.php --check|--apply [--server-ip=.. --server-mac=.. --domain=..]\n";
    exit(1);
}

$bot = new MikhmonTelegramBot();
$api = $bot->getRouterAPI();
if (!$api) {
    echo "ERROR: tidak bisa terhubung ke router.\n";
    exit(1);
}

function rule($msg) { echo ($msg[0] === '+' ? "  + " : ($msg[0] === '~' ? "  ~ " : "  = ")) . substr($msg, 2) . "\n"; }

echo "=== Migrasi Portal Isolir — " . ($apply ? "APPLY" : "CHECK (tidak ada perubahan)") . " ===\n\n";
$changes = 0;

// 1. Pool isolir
$pool = $api->comm('/ip/pool/print', array('?name' => MIG_POOL));
if (is_array($pool) && !empty($pool[0])) {
    rule("= SKIP: pool " . MIG_POOL . " sudah ada ({$pool[0]['ranges']})");
} else {
    rule("+ /ip pool add name=" . MIG_POOL . " ranges=" . MIG_RANGES);
    $changes++;
    if ($apply) {
        $api->comm('/ip/pool/add', array('name' => MIG_POOL, 'ranges' => MIG_RANGES, 'comment' => 'pool pelanggan terisolir'));
        $pool = $api->comm('/ip/pool/print', array('?name' => MIG_POOL));
        rule(is_array($pool) && !empty($pool[0]) ? "= OK: pool dibuat" : "! GAGAL membuat pool");
    }
}

// 2. Static DHCP lease server — hanya relevan kalau server memang di jaringan
//    DHCP router (LAN). Server di balik WireGuard/VPN punya IP tunnel yang bukan
//    dari DHCP; lease tidak bisa dibuat dan tidak dibutuhkan karena dst-nat
//    tetap sampai lewat route tunnel.
$server_in_dhcp = false;
$dhcp_nets = $api->comm('/ip/dhcp-server/network/print');
if (is_array($dhcp_nets)) {
    foreach ($dhcp_nets as $dhcp_net) {
        if (MIG_ip_in_cidr(MIG_SERVER_IP, (string)($dhcp_net['address'] ?? ''))) {
            $server_in_dhcp = true;
            break;
        }
    }
}

if (MIG_SERVER_MAC === '') {
    rule("= SKIP: MAC server kosong — lease DHCP statis tidak dibuat");
} elseif (!$server_in_dhcp) {
    rule("= SKIP: " . MIG_SERVER_IP . " di luar jaringan DHCP router (mis. WireGuard/VPN) — lease tidak diperlukan");
} else {
    $lease = $api->comm('/ip/dhcp-server/lease/print', array('?address' => MIG_SERVER_IP));
    if (is_array($lease) && !empty($lease[0])) {
        $dynamic = ($lease[0]['dynamic'] ?? 'false') === 'true';
        if ($dynamic) {
            rule("~ Lease {$lease[0]['address']} (MAC {$lease[0]['mac-address']}) masih dynamic -> make-static");
            $changes++;
            if ($apply) {
                $api->comm('/ip/dhcp-server/lease/make-static', array('.id' => $lease[0]['.id']));
                rule("= OK: lease dibuat statis");
            }
        } else {
            rule("= SKIP: lease server sudah statis ({$lease[0]['mac-address']})");
        }
    } else {
        rule("+ /ip dhcp-server lease add address=" . MIG_SERVER_IP . " mac-address=" . MIG_SERVER_MAC . " (server belum punya lease; buat statis)");
        $changes++;
        if ($apply) {
            $api->comm('/ip/dhcp-server/lease/add', array('address' => MIG_SERVER_IP, 'mac-address' => MIG_SERVER_MAC, 'comment' => 'server mikhmon (portal isolir)'));
            rule("= OK: lease statis dibuat");
        }
    }
}

// 3. Profil isolir -> pool baru
$prof = $api->comm('/ppp/profile/print', array('?name' => MIG_PROFILE));
if (is_array($prof) && !empty($prof[0])) {
    $ra = $prof[0]['remote-address'] ?? '';
    if ($ra === MIG_POOL) {
        rule("= SKIP: " . MIG_PROFILE . " sudah memakai " . MIG_POOL);
    } else {
        rule("~ " . MIG_PROFILE . " remote-address: {$ra} -> " . MIG_POOL . " (rate-limit {$prof[0]['rate-limit']} tetap)");
        $changes++;
        if ($apply) {
            $api->comm('/ppp/profile/set', array('.id' => $prof[0]['.id'], 'remote-address' => MIG_POOL));
            rule("= OK: profil diubah");
        }
    }
} else {
    rule("! Profil " . MIG_PROFILE . " TIDAK ADA — cek konfigurasi billing (isolir_profile).");
}

// 4. NAT redirect portal (buat bila belum ada, perbarui bila tujuannya berubah —
//    mis. server dipindah dari IP LAN ke IP WireGuard).
$nat = $api->comm('/ip/firewall/nat/print', array('?comment' => MIG_NAT_COMMENT));
if (is_array($nat) && !empty($nat[0])) {
    $cur_to = (string)($nat[0]['to-addresses'] ?? '');
    $cur_port = (string)($nat[0]['to-ports'] ?? '');
    if ($cur_to === MIG_SERVER_IP && $cur_port === MIG_SERVER_PORT) {
        rule("= SKIP: NAT rule '" . MIG_NAT_COMMENT . "' sudah ada dan tujuannya benar");
    } else {
        rule("~ /ip firewall nat set " . MIG_NAT_COMMENT . " to-addresses=" . MIG_SERVER_IP . " to-ports=" . MIG_SERVER_PORT . " (sebelumnya {$cur_to}:{$cur_port})");
        $changes++;
        if ($apply) {
            $api->comm('/ip/firewall/nat/set', array(
                '.id' => $nat[0]['.id'], 'to-addresses' => MIG_SERVER_IP, 'to-ports' => MIG_SERVER_PORT
            ));
            rule("= OK: NAT rule diperbarui");
        }
    }
} else {
    rule("+ /ip firewall nat add chain=dstnat src-address=" . MIG_NETWORK . " protocol=tcp dst-port=80 action=dst-nat to-addresses=" . MIG_SERVER_IP . " to-ports=" . MIG_SERVER_PORT);
    $changes++;
    if ($apply) {
        $api->comm('/ip/firewall/nat/add', array(
            'chain' => 'dstnat', 'src-address' => MIG_NETWORK, 'protocol' => 'tcp',
            'dst-port' => '80', 'action' => 'dst-nat', 'to-addresses' => MIG_SERVER_IP,
            'to-ports' => MIG_SERVER_PORT, 'comment' => MIG_NAT_COMMENT
        ));
        rule("= OK: NAT rule dibuat");
    }
}

// 5. Nonaktifkan scheduler lama
$sch = $api->comm('/system/scheduler/print', array('?name' => MIG_SCHEDULER));
if (is_array($sch) && !empty($sch[0])) {
    if (($sch[0]['disabled'] ?? 'false') === 'true') {
        rule("= SKIP: scheduler " . MIG_SCHEDULER . " sudah disabled");
    } else {
        rule("~ /system scheduler set " . MIG_SCHEDULER . " disabled=yes (dipindah ke cron Mikhmon)");
        $changes++;
        if ($apply) {
            $api->comm('/system/scheduler/set', array('.id' => $sch[0]['.id'], 'disabled' => 'yes'));
            rule("= OK: scheduler dinonaktifkan");
        }
    }
} else {
    rule("= SKIP: scheduler " . MIG_SCHEDULER . " tidak ada");
}

// 6. Redirect DNS subnet isolir -> router (query DNS pelanggan dijawab router,
//    entri statis domain portal bisa diresolve walau tanpa internet).
$dnsComments = array('udp' => 'udp', 'tcp' => 'tcp');
foreach ($dnsComments as $proto => $label) {
    $dnsNat = $api->comm('/ip/firewall/nat/print', array('?comment' => MIG_DNS_NAT_COMMENT, '?protocol' => $proto));
    if (is_array($dnsNat) && !empty($dnsNat[0])) {
        rule("= SKIP: NAT redirect DNS ($label) '" . MIG_DNS_NAT_COMMENT . "' sudah ada");
    } else {
        rule("+ /ip firewall nat add chain=dstnat src-address=" . MIG_NETWORK . " protocol=$proto dst-port=53 action=redirect to-ports=53 comment=" . MIG_DNS_NAT_COMMENT);
        $changes++;
        if ($apply) {
            $api->comm('/ip/firewall/nat/add', array(
                'chain' => 'dstnat', 'src-address' => MIG_NETWORK, 'protocol' => $proto,
                'dst-port' => '53', 'action' => 'redirect', 'to-ports' => '53',
                'comment' => MIG_DNS_NAT_COMMENT
            ));
            rule("= OK: NAT redirect DNS ($label) dibuat");
        }
    }
}

// 7. dst-nat HTTPS portal (opsional, hanya jika portal.domain diisi):
//    443 dari subnet isolir -> server yang memegang sertifikat valid untuk
//    MIG_PORTAL_DOMAIN (mis. lewat Nginx Proxy Manager). SNI domain lain
//    gagal cepat di level TLS (cert mismatch), bukan timeout.
if (MIG_PORTAL_DOMAIN === '') {
    rule("= SKIP: langkah HTTPS 7-8 dilewati (portal.domain kosong)");
} else {
$natHttps = $api->comm('/ip/firewall/nat/print', array('?comment' => MIG_HTTPS_NAT_COMMENT));
if (is_array($natHttps) && !empty($natHttps[0])) {
    $cur_to = (string)($natHttps[0]['to-addresses'] ?? '');
    if ($cur_to === MIG_SERVER_IP) {
        rule("= SKIP: NAT HTTPS '" . MIG_HTTPS_NAT_COMMENT . "' sudah ada dan tujuannya benar");
    } else {
        rule("~ /ip firewall nat set " . MIG_HTTPS_NAT_COMMENT . " to-addresses=" . MIG_SERVER_IP . " (sebelumnya {$cur_to})");
        $changes++;
        if ($apply) {
            $api->comm('/ip/firewall/nat/set', array('.id' => $natHttps[0]['.id'], 'to-addresses' => MIG_SERVER_IP));
            rule("= OK: NAT HTTPS diperbarui");
        }
    }
} else {
    rule("+ /ip firewall nat add chain=dstnat src-address=" . MIG_NETWORK . " protocol=tcp dst-port=443 action=dst-nat to-addresses=" . MIG_SERVER_IP . " to-ports=443 comment=" . MIG_HTTPS_NAT_COMMENT);
    $changes++;
    if ($apply) {
        $api->comm('/ip/firewall/nat/add', array(
            'chain' => 'dstnat', 'src-address' => MIG_NETWORK, 'protocol' => 'tcp',
            'dst-port' => '443', 'action' => 'dst-nat', 'to-addresses' => MIG_SERVER_IP,
            'to-ports' => '443', 'comment' => MIG_HTTPS_NAT_COMMENT
        ));
        rule("= OK: NAT HTTPS dibuat");
    }
}

// 8. DNS statis domain portal -> IP server (dijawab router setelah langkah 6).
$dnsStatic = $api->comm('/ip/dns/static/print', array('?name' => MIG_PORTAL_DOMAIN));
if (is_array($dnsStatic) && !empty($dnsStatic[0])) {
    $cur_addr = (string)($dnsStatic[0]['address'] ?? '');
    if ($cur_addr === MIG_SERVER_IP) {
        rule("= SKIP: DNS statis " . MIG_PORTAL_DOMAIN . " sudah ada dan benar ({$cur_addr})");
    } else {
        rule("~ /ip dns static set " . MIG_PORTAL_DOMAIN . " address=" . MIG_SERVER_IP . " (sebelumnya {$cur_addr})");
        $changes++;
        if ($apply) {
            $api->comm('/ip/dns/static/set', array('.id' => $dnsStatic[0]['.id'], 'address' => MIG_SERVER_IP));
            rule("= OK: DNS statis diperbarui");
        }
    }
} else {
    rule("+ /ip dns static add name=" . MIG_PORTAL_DOMAIN . " address=" . MIG_SERVER_IP);
    $changes++;
    if ($apply) {
        $api->comm('/ip/dns/static/add', array('name' => MIG_PORTAL_DOMAIN, 'address' => MIG_SERVER_IP, 'comment' => 'portal isolir'));
        rule("= OK: DNS statis dibuat");
    }
}
} // akhir blok domain

$api->disconnect();

echo "\n";
if ($changes === 0) {
    echo "Semua sudah terpasang. Tidak ada perubahan yang diperlukan.\n";
    exit(0);
}
if ($apply) {
    echo "Selesai: {$changes} perubahan dieksekusi. Jalankan ulang --apply untuk verifikasi (semua harus SKIP).\n";
    echo "Rollback: remove NAT comment=" . MIG_NAT_COMMENT . ", " . MIG_HTTPS_NAT_COMMENT . " dan " . MIG_DNS_NAT_COMMENT . ", remove DNS statis " . MIG_PORTAL_DOMAIN . ", profile remote-address kembali ke pool lama, remove pool, scheduler enabled=yes.\n";
} else {
    echo "CHECK: {$changes} perubahan akan dilakukan. Jalankan --apply setelah Anda yakin.\n";
}
