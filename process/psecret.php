<?php
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit();
}

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
}

echo "<script>window.location='./?ppp=secrets&session=" . $session . "'</script>";
