<?php
require __DIR__ . "/includes/functions.php";
$u = signedIn();
$o = orderFor((int) ($_GET["id"] ?? 0), $u);
$isCash = $o["payment_method"] === "cash";
$unpaid = $o["payment_status"] === "unpaid";
$cancelled = $o["status"] === "cancelled";
$cashDue = $isCash && $unpaid && !$cancelled;
$editable = $o["status"] === "pending" && $unpaid && !$o["paypal_order_id"];
$canCancel = in_array($o["status"], ["pending", "ready"], true)
    && ($u["role"] !== "customer" || $o["status"] === "pending")
    && !$o["paypal_order_id"] && $unpaid;
$next = $o["status"] === "pending" ? "ready" : ($o["status"] === "ready" ? "completed" : "");
$canAdvance = $u["role"] !== "customer" && $next && ($isCash || $o["payment_status"] === "paid");
$methods = ["cash" => "Cash on pickup"];
if (integrationReady("paymongo") || $o["payment_method"] === "paymongo") {
    $methods["paymongo"] = "GCash / Maya" . (paymongoLive() ? "" : " (test)");
}
$paymentState = $cancelled && $unpaid ? "Not collected" : ($cashDue ? "Pay at pickup" : ucfirst($o["payment_status"]));
pageStart($o["reference"]);
require ROOT . "/includes/views/order.php";
pageEnd((int) $o["id"]);
