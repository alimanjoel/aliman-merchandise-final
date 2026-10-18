<?php
declare(strict_types=1);
// PayMongo Hosted Checkout: creation uses v2; retrieval uses the documented v1 endpoint.
function paymongoLive(): bool
{
    return str_starts_with((string) config("paymongo_secret_key"), "sk_live_");
}
function paymongoRequest(
    string $path,
    string $method = "GET",
    ?array $attributes = null,
): array {
    requireIntegration("paymongo");
    if (
        !preg_match(
            '/^sk_(test|live)_[a-zA-Z0-9]+$/D',
            (string) config("paymongo_secret_key"),
        )
    ) {
        throw new HttpError("Check the PayMongo secret key.", 503);
    }
    if (
        paymongoLive() &&
        parse_url((string) config("app_url"), PHP_URL_SCHEME) !== "https"
    ) {
        throw new HttpError(
            "Use an HTTPS app URL before accepting live payments.",
            503,
        );
    }
    return remote(
        "paymongo",
        "https://api.paymongo.com" . $path,
        $method,
        $attributes === null
            ? null
            : json_encode(
                ["data" => ["attributes" => $attributes]],
                JSON_THROW_ON_ERROR,
            ),
        [
            "Authorization: Basic " .
            base64_encode(config("paymongo_secret_key") . ":"),
            "Content-Type: application/json",
            "Accept: application/json",
        ],
    );
}
function verifyPaymongoSession(array $order, array $result): array
{
    $data = $result["data"] ?? [];
    $a = $data["attributes"] ?? [];
    if (
        ($data["id"] ?? "") !== $order["paymongo_session_id"] ||
        ($a["reference_number"] ?? "") !== $order["reference"] ||
        !is_bool($a["livemode"] ?? null) ||
        $a["livemode"] !== (bool) $order["paymongo_livemode"]
    ) {
        throw new HttpError(
            "The payment session does not match this order.",
            409,
        );
    }
    return $a;
}
function confirmPaymongoPayment(array $order, array $result): bool
{
    $a = verifyPaymongoSession($order, $result);
    $payments = array_values(
        array_filter(
            $a["payments"] ?? [],
            fn($p) => ($p["attributes"]["status"] ?? "") === "paid",
        ),
    );
    if (!$payments) {
        return false;
    }
    if (count($payments) !== 1) {
        throw new HttpError("Payment needs owner review.", 409);
    }
    $payment = $payments[0];
    $p = $payment["attributes"];
    if (
        !preg_match('/^pay_[a-zA-Z0-9]+$/D', $payment["id"] ?? "") ||
        ($p["currency"] ?? "") !== "PHP" ||
        (int) ($p["amount"] ?? -1) !== (int) $order["total_cents"] ||
        !is_bool($p["livemode"] ?? null) ||
        $p["livemode"] !== (bool) $order["paymongo_livemode"] ||
        !empty($p["refunds"]) ||
        !empty($p["disputed"])
    ) {
        throw new HttpError(
            "Payment amount or status needs review. Do not release the order.",
            409,
        );
    }
    atomic(function () use ($order, $payment) {
        $current = one("SELECT * FROM orders WHERE id=?" . lockSuffix(), [
            $order["id"],
        ]);
        if (
            !$current ||
            $current["status"] === "cancelled" ||
            $current["payment_method"] !== "paymongo" ||
            $current["paymongo_session_id"] !== $order["paymongo_session_id"]
        ) {
            throw new HttpError(
                "Order changed. Ask the owner to review the payment.",
                409,
            );
        }
        if ($current["payment_status"] === "paid") {
            return;
        }
        query(
            "UPDATE orders SET payment_status='paid',paymongo_payment_id=?,paid_at=?,updated_at=? WHERE id=?",
            [$payment["id"], now(), now(), $order["id"]],
        );
        queueMail((int) $order["id"], "paid");
        audit(
            (int) $order["user_id"],
            $order["paymongo_livemode"]
                ? "PayMongo payment verified"
                : "PayMongo test payment verified",
            $order["reference"],
        );
    });
    return true;
}
function paymongoRoute(int $id, string $action): never
{
    $u = role(["owner", "employee", "customer"]);
    if ($action === "cancel") {
        cancelOrder($u, $id);
        respond(["cancelled" => true]);
    }
    $response = processPaymongoPayment($u, $id, $action);
    respond($response);
}
function processPaymongoPayment(array $u, int $id, string $action): array
{
    if (!in_array($action, ["create", "check"], true)) {
        throw new HttpError("Unknown payment action.", 404);
    }
    $order = orderFor($id, $u);
    if ($action === "create" && $u["role"] !== "customer") {
        throw new HttpError("Only the customer can open checkout.", 403);
    }
    if (
        $order["payment_method"] !== "paymongo" ||
        $order["status"] === "cancelled"
    ) {
        throw new HttpError(
            "This order is not eligible for wallet payment.",
            409,
        );
    }
    if ($order["payment_status"] === "paid") {
        return ["paid" => true];
    }
    requireIntegration("paymongo");
    if ((bool) $order["paymongo_livemode"] !== paymongoLive()) {
        throw new HttpError(
            "This order belongs to a different payment mode. Restore the matching API key.",
            409,
        );
    }
    throttle("paymongo:" . $id, 10, 60);
    $response = withOrderPaymentLock($u, $id, function (array $order) use (
        $id,
        $action,
    ) {
        if (
            $order["payment_method"] !== "paymongo" ||
            (bool) $order["paymongo_livemode"] !== paymongoLive()
        ) {
            throw new HttpError(
                "The payment method changed. Refresh this order.",
                409,
            );
        }
        if ($order["paymongo_session_id"]) {
            $result = paymongoRequest(
                "/v1/checkout_sessions/" .
                    rawurlencode($order["paymongo_session_id"]),
            );
            $a = verifyPaymongoSession($order, $result);
            if (
                $action === "create" &&
                ($a["status"] ?? "") === "expired" &&
                !confirmPaymongoPayment($order, $result)
            ) {
                closeOrderWalletCheckout($order);
                audit(
                    (int) $order["user_id"],
                    "Expired wallet checkout replaced",
                    $order["reference"] . "; " . $order["paymongo_session_id"],
                );
                query(
                    "UPDATE orders SET paymongo_session_id=NULL,paymongo_expired=0 WHERE id=?",
                    [$id],
                );
                $order["paymongo_session_id"] = null;
            }
        }
        if (!$order["paymongo_session_id"]) {
            if ($action !== "create") {
                throw new HttpError("Open the wallet checkout first.", 409);
            }
            $methods = array_values(
                array_intersect((array) config("paymongo_methods"), [
                    "gcash",
                    "paymaya",
                    "qrph",
                    "card",
                ]),
            );
            if (!$methods) {
                throw new HttpError(
                    "Enable at least one payment method in integrations-config.php.",
                    503,
                );
            }
            $result = paymongoRequest("/v2/checkout_sessions", "POST", [
                "line_items" => array_map(
                    fn($p) => [
                        "name" => $p["name"],
                        "amount" => (int) $p["price_cents"],
                        "quantity" => (int) $p["quantity"],
                        "currency" => "PHP",
                    ],
                    $order["items"],
                ),
                "payment_method_types" => $methods,
                "billing" => [
                    "name" => $order["customer_name"],
                    "email" => $order["email"],
                ],
                "description" =>
                    "Aliman Merchandise order " . $order["reference"],
                "reference_number" => $order["reference"],
                "success_url" => appUrl(
                    "payment.php?provider=paymongo&order=" . $id,
                ),
                "cancel_url" => appUrl(
                    "payment.php?provider=paymongo&result=cancel&order=" . $id,
                ),
                "show_line_items" => true,
                "show_description" => true,
                "send_email_receipt" => false,
                "pass_on_fees" => false,
            ]);
            $session = $result["data"]["id"] ?? "";
            if (!preg_match('/^cs_[a-zA-Z0-9]+$/D', $session)) {
                throw new HttpError(
                    "Payment provider returned an invalid session.",
                    502,
                );
            }
            // Save before revealing the checkout URL. Retries reuse this session.
            query("UPDATE orders SET paymongo_session_id=? WHERE id=?", [
                $session,
                $id,
            ]);
            $order["paymongo_session_id"] = $session;
            // v2 creation may omit fields supplied by the v1 session resource.
            // Retrieve the saved session before validating its reference/mode.
            if (
                !isset($result["data"]["attributes"]["reference_number"]) ||
                !is_bool($result["data"]["attributes"]["livemode"] ?? null)
            ) {
                $result = paymongoRequest(
                    "/v1/checkout_sessions/" . rawurlencode($session),
                );
            }
        }
        $a = verifyPaymongoSession($order, $result);
        if (confirmPaymongoPayment($order, $result)) {
            $response = ["paid" => true];
        } elseif ($action === "check") {
            $response = ["paid" => false];
        } else {
            if (($a["status"] ?? "") === "expired") {
                throw new HttpError(
                    "This checkout has expired. Contact the store to review the order.",
                    409,
                );
            }
            $target = $a["checkout_url"] ?? "";
            if (
                parse_url($target, PHP_URL_SCHEME) !== "https" ||
                parse_url($target, PHP_URL_HOST) !== "checkout.paymongo.com" ||
                preg_match('/[\r\n]/', $target)
            ) {
                throw new HttpError("Invalid checkout address.", 502);
            }
            $response = ["url" => $target];
        }
        return $response;
    });
    return $response;
}
