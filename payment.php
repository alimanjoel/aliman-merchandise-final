<?php
require __DIR__ . "/includes/functions.php";
$u = signedIn(["customer"]);
$o = orderFor((int) ($_GET["order"] ?? 0), $u);
if (
    $o["payment_method"] !== "paymongo" ||
    $o["status"] === "cancelled" ||
    $o["payment_status"] === "paid" ||
    (isset($_GET["provider"]) && $_GET["provider"] !== "paymongo") ||
    isset($_GET["token"])
) {
    go("order.php?id=" . $o["id"]);
}
$cancel = ($_GET["result"] ?? "") === "cancel";
$start = ($_GET["start"] ?? "") === "1";
$hasCheckout = !empty($o["paymongo_session_id"]);
// Only an existing checkout can be checked. Opening/retrying is always a
// visible customer action; cancel and direct links must not be dead ends.
$verifyReturn = $hasCheckout && !$cancel && !$start;
pageStart("Payment");
require ROOT . "/includes/views/payment.php";
pageEnd((int) $o["id"]);
