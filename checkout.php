<?php
require __DIR__ . "/includes/functions.php";
$u = signedIn(["customer"]);
$cart = cart((int) $u["id"]);
if (!$cart["items"]) {
    go("cart.php");
}
$signature = hash("sha256", json_encode($cart));
$checkoutKey = $_SESSION["checkout_key"] ?? "";
$usedKey = is_string($checkoutKey) && $checkoutKey !== "" && one(
    "SELECT id FROM orders WHERE user_id=? AND idempotency_key=?",
    [$u["id"], $checkoutKey],
);
if (($_SESSION["checkout_signature"] ?? "") !== $signature || !$checkoutKey || $usedKey) {
    $_SESSION["checkout_signature"] = $signature;
    $_SESSION["checkout_key"] = bin2hex(random_bytes(24));
}
$pickup = new DateTimeImmutable("today");
if ($pickup->format("w") === "0") {
    $pickup = $pickup->modify("+1 day");
}
pageStart("Checkout");
require ROOT . "/includes/views/checkout.php";
pageEnd();
