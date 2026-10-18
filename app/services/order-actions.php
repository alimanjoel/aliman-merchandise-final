<?php
declare(strict_types=1);

// All payment creation, verification and order edits share this lock. flock
// keeps a slow provider request from overlapping after the database lease ages.
function withOrderPaymentLock(array $u, int $id, callable $action): mixed
{
    orderFor($id, $u);
    $file = fopen(ROOT . "/storage/payment-" . $id . ".lock", "c");
    if (!$file) {
        throw new HttpError(
            "Payment controls are unavailable. Try again shortly.",
            503,
        );
    }
    if (!flock($file, LOCK_EX | LOCK_NB)) {
        fclose($file);
        throw new HttpError(
            "A payment request is in progress. Try again shortly.",
            409,
        );
    }
    $token = time();
    $acquired = false;
    try {
        $locked = query(
            "UPDATE orders SET payment_lock=? WHERE id=? AND payment_lock<? AND status IN ('pending','ready') AND payment_status='unpaid'",
            [$token, $id, $token - 120],
        );
        if ($locked->rowCount() !== 1) {
            throw new HttpError(
                "This order is paid, closed or being updated. Refresh and try again.",
                409,
            );
        }
        $acquired = true;
        return $action(orderFor($id, $u), $token);
    } finally {
        if ($acquired) {
            query(
                "UPDATE orders SET payment_lock=0 WHERE id=? AND payment_lock=?",
                [$id, $token],
            );
        }
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function assertOrderEditable(array $u, array $o, bool $changingPayment): void
{
    if (
        $o["payment_status"] !== "unpaid" ||
        !in_array($o["status"], ["pending", "ready"], true)
    ) {
        throw new HttpError(
            "Paid or closed orders cannot be changed or cancelled.",
            409,
        );
    }
    if (
        ($changingPayment || $u["role"] === "customer") &&
        $o["status"] !== "pending"
    ) {
        throw new HttpError(
            "This order is already being prepared. Contact the store for help.",
            409,
        );
    }
    // Retain historical provider references: a discontinued checkout may
    // already have been paid and must be reviewed before it is abandoned.
    if ($o["paypal_order_id"]) {
        throw new HttpError(
            "This order used a discontinued payment method. Contact the store to review its payment before changing or cancelling.",
            409,
        );
    }
}

function closeOrderWalletCheckout(array $o): void
{
    if (!$o["paymongo_session_id"]) {
        return;
    }
    requireIntegration("paymongo");
    if ((bool) $o["paymongo_livemode"] !== paymongoLive()) {
        throw new HttpError(
            "Restore the matching PayMongo test/live key before changing this order.",
            409,
        );
    }
    $path = "/v1/checkout_sessions/" . rawurlencode($o["paymongo_session_id"]);
    $result = paymongoRequest($path);
    $a = verifyPaymongoSession($o, $result);
    if (confirmPaymongoPayment($o, $result)) {
        throw new HttpError(
            "Payment has already been received. This order cannot be changed or cancelled.",
            409,
        );
    }
    if (($a["status"] ?? "") !== "expired") {
        try {
            $result = paymongoRequest($path . "/expire", "POST");
        } catch (HttpError) {
            // A payment may have won the race, or the expiry response was lost.
            // Requery; never switch merely because an expiry request was sent.
            $result = paymongoRequest($path);
        }
        $a = verifyPaymongoSession($o, $result);
        if (confirmPaymongoPayment($o, $result)) {
            throw new HttpError(
                "Payment has already been received. This order cannot be changed or cancelled.",
                409,
            );
        }
    }
    $pending = array_filter(
        $a["payments"] ?? [],
        fn($p) => in_array(
            $p["attributes"]["status"] ?? "",
            ["pending", "processing"],
            true,
        ),
    );
    if (
        ($a["status"] ?? "") !== "expired" ||
        $pending ||
        ($a["payment_intent"]["attributes"]["status"] ?? "") === "processing"
    ) {
        throw new HttpError(
            "The wallet payment is still open or processing. Check payment and try again shortly.",
            409,
        );
    }
    query("UPDATE orders SET paymongo_expired=1 WHERE id=?", [$o["id"]]);
}

function cancelOrder(array $u, int $id): void
{
    withOrderPaymentLock($u, $id, function (array $o, int $token) use (
        $u,
        $id,
    ) {
        assertOrderEditable($u, $o, false);
        closeOrderWalletCheckout($o);
        changeOrder($u, $id, "cancelled", $token);
    });
}

function changeOrderPayment(
    array $u,
    int $id,
    string $payment,
    string $previous,
): void {
    orderFor($id, $u);
    if (
        !in_array($payment, ["cash", "paymongo"], true) ||
        !in_array($previous, ["cash", "paymongo", "paypal"], true)
    ) {
        throw new HttpError("Choose a valid payment method.");
    }
    if ($payment !== "cash") {
        requireIntegration($payment);
    }
    withOrderPaymentLock($u, $id, function (array $o, int $token) use (
        $u,
        $id,
        $payment,
        $previous,
    ) {
        assertOrderEditable($u, $o, true);
        if ($o["payment_method"] !== $previous) {
            throw new HttpError(
                "The payment method has already changed. Refresh this order.",
                409,
            );
        }
        if ($payment === $previous) {
            return;
        }
        closeOrderWalletCheckout($o);
        atomic(function () use ($u, $id, $payment, $previous, $token, $o) {
            $current = one("SELECT * FROM orders WHERE id=?" . lockSuffix(), [
                $id,
            ]);
            assertOrderEditable($u, $current, true);
            if (
                (int) $current["payment_lock"] !== $token ||
                $current["payment_method"] !== $previous
            ) {
                throw new HttpError(
                    "The order changed. Refresh and try again.",
                    409,
                );
            }
            query(
                "UPDATE orders SET payment_method=?,paymongo_session_id=NULL,paymongo_payment_id=NULL,paymongo_expired=0,paymongo_livemode=?,updated_at=? WHERE id=?",
                [
                    $payment,
                    $payment === "paymongo" ? (int) paymongoLive() : 0,
                    now(),
                    $id,
                ],
            );
            audit(
                (int) $u["id"],
                "Payment method changed",
                $o["reference"] .
                    ": " .
                    $previous .
                    " to " .
                    $payment .
                    ($o["paymongo_session_id"]
                        ? "; expired " . $o["paymongo_session_id"]
                        : ""),
            );
        });
    });
}
