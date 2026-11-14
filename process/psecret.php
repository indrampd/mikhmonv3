<?php
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

require_once dirname(__DIR__) . '/lib/billing.class.php';

if (!empty($enablesecr)) {
    $API->comm("/ppp/secret/enable", array(
        ".id" => "$enablesecr",
    ));
} elseif (!empty($disablesecr)) {
    $API->comm("/ppp/secret/disable", array(
        ".id" => "$disablesecr",
    ));
} elseif (!empty($removesecr)) {
    $API->comm("/ppp/secret/remove", array(
        ".id" => "$removesecr",
    ));
} elseif (!empty($lunassecret)) {
    MikhmonBilling::markLunas($API, $lunassecret, 'Admin Web');
} elseif (!empty($isolirsecret)) {
    MikhmonBilling::markIsolir($API, $isolirsecret, 'Admin Web');
} elseif (!empty($pulihsecret)) {
    MikhmonBilling::restorePaket($API, $pulihsecret, 'Admin Web');
}

echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
