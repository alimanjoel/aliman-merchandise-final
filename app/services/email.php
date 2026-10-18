<?php
declare(strict_types=1);

function emailProvider(): string
{
    return "resend";
}

function providerConnectionMessage(string $provider, int $error): string
{
    $label = $provider === "paymongo" ? "PayMongo" : ucfirst($provider);
    $reason = match ($error) {
        5, 6 => "The server cannot resolve the provider address. Check its internet connection and DNS settings.",
        7 => "The server cannot connect to the provider. Check its internet connection, firewall or proxy.",
        28 => "The provider connection timed out. Please try again shortly.",
        60, 77 => "PHP could not verify the HTTPS certificate. Configure a valid CA certificate file in php.ini (curl.cainfo) and restart Apache. Keep certificate verification enabled.",
        default => "The secure connection failed. Check PHP cURL and the server connection.",
    };
    return $label . ": " . $reason . " Your order remains saved.";
}

function providerFailureMessage(
    string $provider,
    int $status,
    string $body,
): string {
    // Interpret known errors, never display raw responses: they may contain addresses or credentials.
    $details = json_decode($body, true);
    $message = strtolower(
        is_array($details) && is_string($details["message"] ?? null)
            ? $details["message"]
            : "",
    );
    $code =
        is_array($details) && is_string($details["code"] ?? null)
            ? $details["code"]
            : (is_array($details) && is_string($details["name"] ?? null)
                ? $details["name"]
                : "");
    $label =
        [
            "resend" => "Resend",
            "paymongo" => "PayMongo",
            "pwnedpasswords" => "Password check",
            "qrserver" => "QR service",
        ][$provider] ?? ucfirst($provider);
    if ($status === 0) {
        return "$label could not be reached over HTTPS. Check internet access and PHP cURL certificates. Your order remains saved; check its payment before trying again.";
    }
    if ($provider === "paymongo") {
        $pointer = $details["errors"][0]["source"]["pointer"] ?? "";
        if ($status === 401) {
            return "PayMongo rejected the secret key. Use sk_test_ for testing; do not use a pk_ public key.";
        }
        if ($status === 403) {
            return "PayMongo did not allow this request. Check account activation and enabled payment methods in its dashboard.";
        }
        if (
            is_string($pointer) &&
            str_contains($pointer, "payment_method_types")
        ) {
            return "PayMongo rejected the selected payment channels. Set paymongo_methods to only the channels enabled on your account (Maya uses paymaya).";
        }
        if (
            is_string($pointer) &&
            (str_contains($pointer, "success_url") ||
                str_contains($pointer, "cancel_url"))
        ) {
            return "PayMongo rejected the return address. Set app_url in database.php to the exact address and folder where this store runs.";
        }
        if ($status === 404) {
            return "PayMongo cannot find this checkout. Restore the same account and test/live key used when it was created.";
        }
    }
    if (
        $provider === "resend" &&
        $status === 403 &&
        str_contains($message, "testing emails")
    ) {
        return "Resend test sender can email only your Resend account address. Verify a domain and change mail_from. Your order is saved.";
    }
    if (
        $provider === "resend" &&
        $status === 403 &&
        str_contains($message, "domain")
    ) {
        return "Resend rejected the sender domain. Verify it in Resend Domains and set mail_from to an address on that domain.";
    }
    if (
        $provider === "resend" &&
        $status === 401 &&
        $code === "restricted_api_key"
    ) {
        return "This Resend key is limited to sending emails and cannot access this endpoint. This restriction does not by itself mean email sending is broken.";
    }
    if (
        $provider === "resend" &&
        $status === 403 &&
        in_array($code, ["restricted_api_key", "suspended_api_key"], true)
    ) {
        return "The Resend key is inactive or suspended. Review its status in the provider dashboard before retrying.";
    }
    if ($status === 401) {
        return "$label rejected the API key. Check the selected provider and its API key in integrations-config.php.";
    }
    if ($status === 429) {
        return "$label has reached a sending or request limit. Check your plan limits and retry later.";
    }
    if ($provider === "resend" && $status === 409) {
        return "Resend found a conflicting retry. Review its email logs before sending again.";
    }
    return "$label request failed (HTTP $status). Check the provider dashboard, configuration and service status.";
}

function sendTransactionalEmail(
    array $order,
    array $job,
    string $subject,
    string $message,
): void {
    $provider = emailProvider();
    requireIntegration($provider);
    $key = "aliman-order-" . $order["reference"] . "-" . $job["event"];
    $result = remote(
        "resend",
        "https://api.resend.com/emails",
        "POST",
        json_encode(
            [
                "from" => config("mail_from"),
                "to" => [$order["email"]],
                "subject" => $subject,
                "text" => $message,
            ],
            JSON_THROW_ON_ERROR,
        ),
        [
            "Authorization: Bearer " . config("resend_key"),
            "Content-Type: application/json",
            "Idempotency-Key: " . $key,
        ],
    );
    $ack = $result["id"] ?? null;
    if (!is_string($ack) || $ack === "") {
        throw new HttpError(
            "Email provider did not acknowledge the message. Review its logs before retrying.",
            502,
        );
    }
    apiEvent(
        $provider,
        "connected",
        "Email accepted by the provider. Check its delivery logs for inbox delivery.",
    );
}

function hasPendingOrderMail(int $id): bool
{
    return (bool) one(
        "SELECT id FROM email_outbox WHERE order_id=? AND status='pending' AND attempts<5 LIMIT 1",
        [$id],
    );
}

function sendOrderMail(int $id, bool $retryFailed = false): bool
{
    $provider = emailProvider();
    if (!integrationReady($provider)) {
        return false;
    }
    // Prevent double-clicks/concurrent order updates from sending the same pending message together.
    $lock = @fopen(ROOT . "/storage/email-" . $id . ".lock", "c");
    if (!$lock) {
        apiEvent(
            $provider,
            "error",
            "Email queue cannot acquire a lock. Check storage folder write permissions.",
        );
        return false;
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return false;
    }
    try {
        if ($retryFailed) {
            query(
                "UPDATE email_outbox SET attempts=0 WHERE order_id=? AND status='pending' AND attempts>=5",
                [$id],
            );
        }
        $jobs = all(
            "SELECT * FROM email_outbox WHERE order_id=? AND status='pending' AND attempts<5 ORDER BY id",
            [$id],
        );
        $order = one(
            "SELECT o.*,u.name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?",
            [$id],
        );
        if (!$order) {
            return false;
        }
        foreach ($jobs as $job) {
            $labels = [
                "placed" => "Order confirmation",
                "ready" => "Your order is ready for pickup",
                "completed" => "Your purchase receipt",
                "cancelled" => "Order cancelled",
                "paid" =>
                    $order["payment_method"] === "paypal" ||
                    ($order["payment_method"] === "paymongo" &&
                        !$order["paymongo_livemode"])
                        ? "Test payment confirmed"
                        : "Payment confirmed",
            ];
            $subject =
                ($labels[$job["event"]] ?? "Order update") .
                " · " .
                $order["reference"];
            $items = "";
            foreach (
                all(
                    "SELECT name,quantity,price_cents FROM order_items WHERE order_id=? ORDER BY id",
                    [$id],
                )
                as $item
            ) {
                $items .=
                    "\n" .
                    $item["quantity"] .
                    " x " .
                    $item["name"] .
                    " — PHP " .
                    number_format(
                        ($item["price_cents"] * $item["quantity"]) / 100,
                        2,
                    );
            }
            $message =
                "Hello " .
                $order["name"] .
                ",\n\n" .
                $subject .
                $items .
                "\n\nOrder total: PHP " .
                number_format($order["total_cents"] / 100, 2) .
                "\nPickup date: " .
                $order["pickup_date"] .
                "\nStore: " .
                setting("store_address") .
                "\n\nView your order: " .
                appUrl("order.php?id=" . $id) .
                "\n\nAliman Merchandise";
            try {
                sendTransactionalEmail($order, $job, $subject, $message);
                query(
                    "UPDATE email_outbox SET status='sent',sent_at=?,attempts=attempts+1,last_error='' WHERE id=?",
                    [now(), $job["id"]],
                );
            } catch (Throwable $e) {
                $error =
                    $e instanceof HttpError
                        ? $e->getMessage()
                        : "Email could not be sent. Review the provider logs before retrying.";
                apiEvent($provider, "error", $error);
                query(
                    "UPDATE email_outbox SET attempts=attempts+1,last_error=? WHERE id=?",
                    [substr($error, 0, 250), $job["id"]],
                );
                return false;
            }
        }
        return !one(
            "SELECT id FROM email_outbox WHERE order_id=? AND status='pending'",
            [$id],
        );
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function queueMail(int $id, string $event): void
{
    if (
        !one("SELECT id FROM email_outbox WHERE order_id=? AND event=?", [
            $id,
            $event,
        ])
    ) {
        query(
            "INSERT INTO email_outbox (order_id,event,created_at) VALUES (?,?,?)",
            [$id, $event, now()],
        );
    }
}
