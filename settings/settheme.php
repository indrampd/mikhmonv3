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
session_start();
// hide all error
error_reporting(0);

// check url
$url2 = explode("&set-theme", $url)[0];

$gettheme = $_GET['set-theme'];
$mtheme = array(
    "dark",
    "light",
    "blue",
    "green",
    "pink",
    
);
$theme_color = array(
    "#3a4149",
    "#008BC9",
    "#008BC9",
    "#4dbd74",
    "#e83e8c",
);


$themenum = array_search($gettheme, $mtheme);

$getthemecolor = $theme_color[$themenum];

if (empty($gettheme)) {  

} else {
    if (in_array($gettheme, $mtheme)) {
        $gen = '<?php $theme="' . $gettheme . '"; $themecolor="'.$getthemecolor.'";?>';
        $stheme = __DIR__ . '/../include/theme.php';
        $written = @file_put_contents($stheme, $gen);
        if ($written === false) {
            // The file is owned by whoever checked the repo out; PHP runs as
            // www-data, so a wrong owner silently made the theme switch do
            // nothing. Report it instead of pretending it worked.
            include_once('./include/headhtml.php');
            echo '<div style="max-width:640px;margin:10% auto;padding:18px;border:1px solid #f86c6b;border-radius:4px;background:#3a4149;color:#e4e7ea;font-family:sans-serif;">'
               . '<h3 style="margin:0 0 8px;color:#f86c6b;">Tema gagal disimpan</h3>'
               . '<p style="margin:0 0 8px;">File <code>include/theme.php</code> tidak dapat ditulis oleh proses web.</p>'
               . '<p style="margin:0;">Jalankan di server: <code>chown 82:82 include/theme.php &amp;&amp; chmod 664 include/theme.php</code></p>'
               . '</div>';
            exit();
        }
        $_SESSION['theme'] = $gettheme;
        $_SESSION['themecolor'] = $getthemecolor;
        include_once('./include/headhtml.php');
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>Load '.$gettheme.' theme...</h3></center>';
        echo "<script>window.location='" . $url2 . "'</script>";

    } else {
        include_once('./include/headhtml.php');
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>'.$gettheme.' theme not found...</h3></center>';
        echo "<script>window.location='" . $url2 . "'</script>";
    }
}

?>