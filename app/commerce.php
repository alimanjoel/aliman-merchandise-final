<?php
declare(strict_types=1);
function cart(int $uid): array
{
    $items = all(
        "SELECT c.quantity,p.id,p.name,p.sku,p.price_cents,p.stock,p.image_url,p.category_id,p.active,p.deleted FROM cart_items c JOIN products p ON p.id=c.product_id WHERE c.user_id=? ORDER BY p.id",
        [$uid],
    );
    return [
        "items" => $items,
        "total_cents" => array_sum(
            array_map(
                fn($i) => (int) $i["price_cents"] * (int) $i["quantity"],
                $items,
            ),
        ),
    ];
}
function placeOrder(array $u, array $b): array
{
    $key = textField($b, "idempotency_key", 80);
    if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/', $key)) {
        throw new HttpError("Invalid checkout request. Refresh and retry.");
    }
    $date = textField($b, "pickup_date", 10);
    $parsed = DateTimeImmutable::createFromFormat("!Y-m-d", $date);
    if (
        !$parsed ||
        $parsed->format("Y-m-d") !== $date ||
        $date < date("Y-m-d") ||
        $date > date("Y-m-d", strtotime("+30 days"))
    ) {
        throw new HttpError("Choose a pickup date within the next 30 days.");
    }
    if ($parsed->format("w") === "0") {
        throw new HttpError(
            "The store is closed on Sundays. Please choose another pickup date.",
        );
    }
    $notes = textField($b, "notes", 500, false);
    $payment = $b["payment_method"] ?? "cash";
    if (!in_array($payment, ["cash", "paymongo"], true)) {
        throw new HttpError("Choose a valid payment method.");
    }
    if ($payment === "paymongo") {
        requireIntegration("paymongo");
    }
    $reviewed = integer($b["reviewed_total"] ?? -1, 0, PHP_INT_MAX);
    return atomic(function () use (
        $u,
        $key,
        $date,
        $notes,
        $payment,
        $reviewed,
    ) {
        // The customer row serializes checkout and cart edits for this customer on MySQL.
        one("SELECT id FROM users WHERE id=?" . lockSuffix(), [$u["id"]]);
        $existing = one(
            "SELECT id,reference FROM orders WHERE user_id=? AND idempotency_key=?",
            [$u["id"], $key],
        );
        if ($existing) {
            return $existing;
        }
        $items = all(
            "SELECT c.quantity,p.* FROM cart_items c JOIN products p ON p.id=c.product_id WHERE c.user_id=? ORDER BY p.id" .
                lockSuffix(),
            [$u["id"]],
        );
        if (!$items) {
            throw new HttpError("Your cart is empty.");
        }
        $total = 0;
        foreach ($items as $p) {
            if (!$p["active"] || $p["deleted"] || $p["price_cents"] <= 0) {
                throw new HttpError(
                    $p["name"] . " is not available. Remove it from your cart.",
                );
            }
            if ($p["quantity"] > $p["stock"]) {
                throw new HttpError(
                    "Only " . $p["stock"] . " of " . $p["name"] . " remain.",
                    409,
                );
            }
            $total += (int) $p["price_cents"] * (int) $p["quantity"];
        }
        // Detect price changes since the customer reviewed their cart, while always calculating on the server.
        if ($reviewed !== $total) {
            throw new HttpError(
                "A price changed. Refresh your cart and review the new total.",
                409,
            );
        }
        $ref = "AM-" . date("ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));
        $claim = strtoupper(bin2hex(random_bytes(6)));
        query(
            "INSERT INTO orders (reference,user_id,email,total_cents,pickup_date,notes,claim_code,idempotency_key,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $ref,
                $u["id"],
                $u["email"],
                $total,
                $date,
                $notes,
                $claim,
                $key,
                now(),
                now(),
            ],
        );
        $oid = (int) db()->lastInsertId();
        query("UPDATE orders SET payment_method=? WHERE id=?", [
            $payment,
            $oid,
        ]);
        if ($payment === "paymongo") {
            query("UPDATE orders SET paymongo_livemode=? WHERE id=?", [
                (int) paymongoLive(),
                $oid,
            ]);
        }
        foreach ($items as $p) {
            $s = query(
                "UPDATE products SET stock=stock-?,version=version+1,updated_at=? WHERE id=? AND stock>=? AND active=1 AND deleted=0",
                [$p["quantity"], now(), $p["id"], $p["quantity"]],
            );
            if ($s->rowCount() !== 1) {
                throw new HttpError(
                    "Stock changed during checkout. Please review your cart.",
                    409,
                );
            }
            query(
                "INSERT INTO order_items (order_id,product_id,name,sku,price_cents,quantity) VALUES (?,?,?,?,?,?)",
                [
                    $oid,
                    $p["id"],
                    $p["name"],
                    $p["sku"],
                    $p["price_cents"],
                    $p["quantity"],
                ],
            );
        }
        query("DELETE FROM cart_items WHERE user_id=?", [$u["id"]]);
        audit((int) $u["id"], "Order placed", $ref);
        queueMail($oid, "placed");
        return ["id" => $oid, "reference" => $ref];
    });
}
function orderFor(int $id, array $u): array
{
    $o = one(
        "SELECT o.*,u.name AS customer_name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?",
        [$id],
    );
    if (
        !$o ||
        ($u["role"] === "customer" && (int) $o["user_id"] !== (int) $u["id"])
    ) {
        throw new HttpError("Order not found.", 404);
    }
    $o["items"] = all(
        "SELECT * FROM order_items WHERE order_id=? ORDER BY id",
        [$id],
    );
    return $o;
}
function changeOrder(
    array $u,
    int $id,
    string $next,
    int $ownedPaymentLock = 0,
): void {
    atomic(function () use ($u, $id, $next, $ownedPaymentLock) {
        $o = one("SELECT * FROM orders WHERE id=?" . lockSuffix(), [$id]);
        if (
            !$o ||
            ($u["role"] === "customer" &&
                (int) $o["user_id"] !== (int) $u["id"])
        ) {
            throw new HttpError("Order not found.", 404);
        }
        $allowed = [
            "pending" => ["ready", "cancelled"],
            "ready" => ["completed", "cancelled"],
            "completed" => [],
            "cancelled" => [],
        ];
        if (
            $u["role"] === "customer" &&
            !($o["status"] === "pending" && $next === "cancelled")
        ) {
            throw new HttpError(
                "You can cancel an order only while it is pending.",
                403,
            );
        }
        if (!in_array($next, $allowed[$o["status"]] ?? [], true)) {
            throw new HttpError(
                "This order has already changed. Refresh the order list.",
                409,
            );
        }
        if (
            (int) $o["payment_lock"] > time() - 120 &&
            (int) $o["payment_lock"] !== $ownedPaymentLock
        ) {
            throw new HttpError(
                "A payment request is in progress. Try again shortly.",
                409,
            );
        }
        if (
            $next === "cancelled" &&
            ($o["paypal_order_id"] ||
                ($o["paymongo_session_id"] && !$o["paymongo_expired"]) ||
                $o["payment_status"] === "paid")
        ) {
            throw new HttpError(
                "An online payment is pending or paid. Contact the owner to reconcile it before cancellation.",
                409,
            );
        }
        if (
            in_array($next, ["ready", "completed"], true) &&
            in_array($o["payment_method"], ["paypal", "paymongo"], true) &&
            $o["payment_status"] !== "paid"
        ) {
            throw new HttpError(
                "Verify the online payment before preparing this order.",
                409,
            );
        }
        if ($next === "cancelled") {
            foreach (
                all(
                    "SELECT product_id,quantity FROM order_items WHERE order_id=? ORDER BY product_id",
                    [$id],
                )
                as $p
            ) {
                query(
                    "UPDATE products SET stock=stock+?,version=version+1,updated_at=? WHERE id=?",
                    [$p["quantity"], now(), $p["product_id"]],
                );
            }
        }
        query("UPDATE orders SET status=?,updated_at=?,paid_at=? WHERE id=?", [
            $next,
            now(),
            $next === "completed" ? ($o["paid_at"] ?: now()) : $o["paid_at"],
            $id,
        ]);
        if ($next === "completed") {
            query("UPDATE orders SET payment_status='paid' WHERE id=?", [$id]);
        }
        queueMail($id, $next);
        audit((int) $u["id"], "Order " . $next, $o["reference"]);
    });
}
function dashboard(): array
{
    $cut = date("Y-m-d", strtotime("-29 days"));
    return [
        "summary" => [
            "revenue_cents" => (int) one(
                "SELECT COALESCE(SUM(total_cents),0) AS n FROM orders WHERE status='completed' AND paid_at>=?",
                [$cut],
            )["n"],
            "orders" => (int) one(
                "SELECT COUNT(*) AS n FROM orders WHERE created_at>=?",
                [$cut],
            )["n"],
            "pending" => (int) one(
                "SELECT COUNT(*) AS n FROM orders WHERE status='pending'",
            )["n"],
            "ready" => (int) one(
                "SELECT COUNT(*) AS n FROM orders WHERE status='ready'",
            )["n"],
            "customers" => (int) one(
                "SELECT COUNT(*) AS n FROM users WHERE role='customer' AND active=1",
            )["n"],
            "low_stock" => (int) one(
                "SELECT COUNT(*) AS n FROM products WHERE stock<=low_stock AND deleted=0 AND active=1",
            )["n"],
            "products" => (int) one(
                "SELECT COUNT(*) AS n FROM products WHERE deleted=0",
            )["n"],
            "hidden" => (int) one(
                "SELECT COUNT(*) AS n FROM products WHERE deleted=0 AND active=0",
            )["n"],
        ],
        "sales" => all(
            "SELECT SUBSTR(paid_at,1,10) AS day,SUM(total_cents) AS total FROM orders WHERE status='completed' AND paid_at>=? GROUP BY SUBSTR(paid_at,1,10) ORDER BY day",
            [$cut],
        ),
        "recent" => all(
            "SELECT o.id,o.reference,o.status,o.total_cents,o.created_at,u.name AS customer_name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 6",
        ),
        "low_stock" => all(
            "SELECT id,name,stock,low_stock,category_id FROM products WHERE deleted=0 AND active=1 AND stock<=low_stock ORDER BY stock LIMIT 6",
        ),
        "top" => all(
            "SELECT i.name,SUM(i.quantity) AS quantity,SUM(i.quantity*i.price_cents) AS total FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.status='completed' AND o.paid_at>=? GROUP BY i.name ORDER BY quantity DESC LIMIT 5",
            [$cut],
        ),
        "activity" => all(
            "SELECT a.*,u.name FROM activity a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 6",
        ),
    ];
}
