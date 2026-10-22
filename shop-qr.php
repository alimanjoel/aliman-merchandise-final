<?php
require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$target = ($_GET["target"] ?? "") === "shop" ? "shop" : "facebook";
$error = null;
$destination = "";
$ready = false;
try {
    $destination = shopQrDestination($target);
    $ready = cachedShopQr($target) !== null;
} catch (HttpError $e) {
    $error = $e->getMessage();
}
pageStart("Shop QR code");
require ROOT . "/includes/views/shop-qr.php";
pageEnd();
