<?php
require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$target = ($_GET["target"] ?? "") === "shop" ? "shop" : "facebook";
$bytes = cachedShopQr($target);
if ($bytes === null) {
    throw new HttpError(
        "Generate the QR code from the owner workspace first.",
        404,
    );
}
header("Content-Type: image/png");
header("Cache-Control: private, no-store");
if (($_GET["download"] ?? "") === "1") {
    header(
        'Content-Disposition: attachment; filename="aliman-' .
            $target .
            '-qr.png"',
    );
}
echo $bytes;
