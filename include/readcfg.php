<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// hide all error
error_reporting(0);
if (substr($_SERVER["REQUEST_URI"] ?? '', -11) == "readcfg.php") {
    header("Location:./");
    exit();
}
// read config

if (!function_exists('cfg_val')) {
    function cfg_val($str, $delim) {
        if (empty($str)) return '';
        $parts = explode($delim, $str);
        return $parts[1] ?? '';
    }
}

$c_sess = (!empty($session) && isset($data[$session])) ? $data[$session] : array();

$iphost      = cfg_val($c_sess[1] ?? '', '!');
$userhost    = cfg_val($c_sess[2] ?? '', '@|@');
$passwdhost  = cfg_val($c_sess[3] ?? '', '#|#');
$hotspotname = cfg_val($c_sess[4] ?? '', '%');
$dnsname     = cfg_val($c_sess[5] ?? '', '^');
$currency    = cfg_val($c_sess[6] ?? '', '&');
$areload     = cfg_val($c_sess[7] ?? '', '*');
$iface       = cfg_val($c_sess[8] ?? '', '(');
$infolp      = cfg_val($c_sess[9] ?? '', ')');
$idleto      = cfg_val($c_sess[10] ?? '', '=');
$sesname     = cfg_val($c_sess[10] ?? '', '+');
$useradm     = cfg_val($data['mikhmon'][1] ?? '', '<|<');
$passadm     = cfg_val($data['mikhmon'][2] ?? '', '>|>');
$livereport  = cfg_val($c_sess[11] ?? '', '@!@');

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);


