<?php
declare(strict_types=1);
require_once __DIR__ . "/app/bootstrap.php";
require_once ROOT . "/app/commerce.php";
require_once ROOT . "/app/services/integrations.php";
try {
    $r = trim($_GET["route"] ?? "", "/");
    $method = $_SERVER["REQUEST_METHOD"];
    if (!in_array($method, ["GET", "POST", "PUT", "DELETE"], true)) {
        throw new HttpError("Method not allowed.", 405);
    }
    if ($method !== "GET") {
        csrf();
    }
    if ($r === "session" && $method === "GET") {
        $settings = [];
        foreach (all("SELECT * FROM settings") as $s) {
            $settings[$s["name"]] = $s["value"];
        }
        $oauthError = $_SESSION["oauth_error"] ?? null;
        unset($_SESSION["oauth_error"]);
        respond([
            "user" => user(false),
            "csrf" => $_SESSION["csrf"],
            "categories" => user(false)
                ? all("SELECT * FROM categories ORDER BY id")
                : [],
            "settings" => $settings,
            "features" => [
                "paymongo" => integrationReady("paymongo"),
                "google" => integrationReady("google"),
                "email" => integrationReady(emailProvider()),
            ],
            "oauth_error" => $oauthError,
        ]);
    }
    if (in_array($r, ["login", "register"], true) && $method === "POST") {
        $b = body();
        $email = strtolower(textField($b, "email", 150));
        $pass = textField($b, "password", 72);
        throttle("auth:" . ($_SERVER["REMOTE_ADDR"] ?? "local"), 15, 60);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError("Enter a valid email address.");
        }
        if ($r === "register") {
            $name = textField($b, "name", 100);
            if (strlen($pass) < 12) {
                throw new HttpError(
                    "Use at least 12 characters for your password.",
                );
            }
            if (one("SELECT id FROM users WHERE email=?", [$email])) {
                throw new HttpError(
                    "This email already has an account. Please sign in.",
                    409,
                );
            }
            checkNewPassword($pass);
            query(
                "INSERT INTO users (name,email,password_hash,role,created_at) VALUES (?,?,?,?,?)",
                [
                    $name,
                    $email,
                    password_hash($pass, PASSWORD_DEFAULT),
                    "customer",
                    now(),
                ],
            );
            $id = (int) db()->lastInsertId();
        } else {
            $u = one("SELECT * FROM users WHERE email=?", [$email]);
            $valid = password_verify(
                $pass,
                $u["password_hash"] ??
                    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
            );
            if (!$u || !$valid || !$u["active"]) {
                throw new HttpError("Email or password is incorrect.", 401);
            }
            $id = (int) $u["id"];
            if (password_needs_rehash($u["password_hash"], PASSWORD_DEFAULT)) {
                query("UPDATE users SET password_hash=? WHERE id=?", [
                    password_hash($pass, PASSWORD_DEFAULT),
                    $id,
                ]);
            }
        }
        session_regenerate_id(true);
        $_SESSION["user_id"] = $id;
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        respond(["user" => user(), "csrf" => $_SESSION["csrf"]]);
    }
    if ($r === "logout" && $method === "POST") {
        session_unset();
        session_regenerate_id(true);
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        respond(["ok" => true, "csrf" => $_SESSION["csrf"]]);
    }
    // Apply the first-login requirement to every API route, including routes
    // that only call user() instead of a role-specific workspace guard.
    $signedInUser = user(false);
    if (
        $signedInUser &&
        !empty($signedInUser["must_change_password"]) &&
        $r !== "password"
    ) {
        throw new HttpError(
            "Change your starter password from Account first.",
            403,
        );
    }
    if ($r === "products" && $method === "GET") {
        user();
        $admin = ($_GET["manage"] ?? "") === "1";
        if ($admin) {
            role(["owner", "employee"]);
        }
        $where = "p.deleted=0";
        $args = [];
        if (!$admin) {
            $where .= " AND p.active=1";
        }
        if (!empty($_GET["q"])) {
            $q = "%" . substr((string) $_GET["q"], 0, 100) . "%";
            $where .=
                " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $args = [$q, $q, $q];
        }
        if (!empty($_GET["category"])) {
            $where .= " AND p.category_id=?";
            $args[] = integer($_GET["category"], 1);
        }
        $sort =
            [
                "name" => "p.name",
                "price_asc" => "p.price_cents ASC",
                "price_desc" => "p.price_cents DESC",
                "new" => "p.id DESC",
                "stock" => "p.stock ASC",
            ][$_GET["sort"] ?? "name"] ?? "p.name";
        respond([
            "products" => all(
                "SELECT p.*,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY $sort",
                $args,
            ),
        ]);
    }
    if (
        preg_match('~^products(?:/(\d+))?$~', $r, $m) &&
        in_array($method, ["POST", "PUT", "DELETE"], true)
    ) {
        $u = role(["owner"]);
        $id = isset($m[1]) ? (int) $m[1] : 0;
        if ($method === "DELETE") {
            if (!$id) {
                throw new HttpError("Product not found.", 404);
            }
            query(
                "UPDATE products SET deleted=1,active=0,version=version+1,updated_at=? WHERE id=?",
                [now(), $id],
            );
            audit((int) $u["id"], "Product deleted", "Product #" . $id);
            respond(["ok" => true]);
        }
        $b = body();
        $name = textField($b, "name", 150);
        $sku = strtoupper(textField($b, "sku", 30));
        if (!preg_match('/^[A-Z0-9_-]+$/', $sku)) {
            throw new HttpError(
                "SKU can contain letters, numbers, dashes, and underscores.",
            );
        }
        $cat = integer($b["category_id"] ?? 0, 1);
        if (!one("SELECT id FROM categories WHERE id=?", [$cat])) {
            throw new HttpError("Choose an existing category.");
        }
        $price = cents($b["price"] ?? "");
        $stock = integer($b["stock"] ?? 0, 0, 100000);
        $low = integer($b["low_stock"] ?? 10, 0, 100000);
        if ($price < 1) {
            throw new HttpError("Price must be greater than zero.");
        }
        $active = integer($b["active"] ?? 1, 0, 1);
        $values = [
            $sku,
            $name,
            textField($b, "brand", 80, false),
            $cat,
            textField($b, "description", 2000, false),
            $price,
            $stock,
            $low,
            $active,
            now(),
        ];
        if ($id) {
            $version = integer($b["version"] ?? 0, 1);
            $s = query(
                "UPDATE products SET sku=?,name=?,brand=?,category_id=?,description=?,price_cents=?,stock=?,low_stock=?,active=?,updated_at=?,version=version+1 WHERE id=? AND version=? AND deleted=0",
                array_merge($values, [$id, $version]),
            );
            if (!$s->rowCount()) {
                throw new HttpError(
                    "Stock or product details changed. Close this form and refresh before editing.",
                    409,
                );
            }
        } else {
            query(
                "INSERT INTO products (sku,name,brand,category_id,description,price_cents,stock,low_stock,active,updated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                array_merge($values, [now()]),
            );
            $id = (int) db()->lastInsertId();
        }
        audit((int) $u["id"], "Product saved", $name);
        respond(["id" => $id]);
    }
    if ($r === "categories" && $method === "POST") {
        $u = role(["owner"]);
        $name = textField(body(), "name", 60);
        query("INSERT INTO categories (name) VALUES (?)", [$name]);
        audit((int) $u["id"], "Category created", $name);
        respond(["id" => db()->lastInsertId(), "name" => $name]);
    }
    if ($r === "cart") {
        $u = role(["customer"]);
        if ($method === "GET") {
            respond(cart((int) $u["id"]));
        }
        $b = body();
        $pid = integer($b["product_id"] ?? 0, 1);
        $qty = $method === "DELETE" ? 0 : integer($b["quantity"] ?? 1, 0, 99);
        atomic(function () use ($u, $pid, $qty) {
            one("SELECT id FROM users WHERE id=?" . lockSuffix(), [$u["id"]]);
            if ($qty === 0) {
                query(
                    "DELETE FROM cart_items WHERE user_id=? AND product_id=?",
                    [$u["id"], $pid],
                );
                return;
            }
            $p = one(
                "SELECT * FROM products WHERE id=? AND deleted=0 AND active=1",
                [$pid],
            );
            if (!$p) {
                throw new HttpError("Product is no longer available.", 404);
            }
            if ($qty > $p["stock"]) {
                throw new HttpError(
                    "Only " . $p["stock"] . " items are available.",
                    409,
                );
            }
            if (
                one(
                    "SELECT quantity FROM cart_items WHERE user_id=? AND product_id=?",
                    [$u["id"], $pid],
                )
            ) {
                query(
                    "UPDATE cart_items SET quantity=? WHERE user_id=? AND product_id=?",
                    [$qty, $u["id"], $pid],
                );
            } else {
                query(
                    "INSERT INTO cart_items (user_id,product_id,quantity) VALUES (?,?,?)",
                    [$u["id"], $pid, $qty],
                );
            }
        });
        respond(cart((int) $u["id"]));
    }
    if ($r === "orders" && $method === "POST") {
        $u = role(["customer"]);
        $b = body();
        $order = placeOrder($u, $b);
        // A completed attempt must not be reused by the next purchase. Old
        // POST retries still retain their key and safely return the same order.
        if (($_SESSION["checkout_key"] ?? null) === $b["idempotency_key"]) {
            unset($_SESSION["checkout_key"], $_SESSION["checkout_signature"]);
        }
        $order["email_queued"] = hasPendingOrderMail((int) $order["id"]);
        respond($order, 201);
    }
    if ($r === "orders" && $method === "GET") {
        $u = user();
        $where = "1=1";
        $args = [];
        if ($u["role"] === "customer") {
            $where .= " AND o.user_id=?";
            $args[] = $u["id"];
        }
        if (!empty($_GET["status"])) {
            $where .= " AND o.status=?";
            $args[] = textField($_GET, "status", 20);
        }
        if (!empty($_GET["q"])) {
            $q = "%" . substr((string) $_GET["q"], 0, 100) . "%";
            $where .=
                " AND (o.reference LIKE ? OR u.name LIKE ? OR o.claim_code LIKE ?)";
            array_push($args, $q, $q, $q);
        }
        respond([
            "orders" => all(
                "SELECT o.id,o.reference,o.status,o.total_cents,o.pickup_date,o.created_at,u.name AS customer_name,(SELECT SUM(quantity) FROM order_items WHERE order_id=o.id) AS item_count FROM orders o JOIN users u ON u.id=o.user_id WHERE $where ORDER BY o.id DESC LIMIT 250",
                $args,
            ),
        ]);
    }
    if (preg_match('~^orders/(\d+)/payment$~', $r, $m) && $method === "PUT") {
        $u = role(["customer", "owner", "employee"]);
        $b = body();
        changeOrderPayment(
            $u,
            (int) $m[1],
            textField($b, "payment_method", 20),
            textField($b, "current_payment_method", 20),
        );
        respond([
            "ok" => true,
            "message" =>
                "Payment method updated. Your items and pickup date are unchanged.",
        ]);
    }
    if (preg_match('~^orders/(\d+)/notifications$~', $r, $m) && $method === "POST") {
        $u = user();
        $id = (int) $m[1];
        orderFor($id, $u);
        throttle("order-mail:" . $id, 5, 60);
        // Release the browser session before the optional network request so
        // it cannot hold up the customer's next cart or payment action.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        respond(["sent" => sendOrderMail($id)]);
    }
    if (preg_match('~^orders/(\d+)$~', $r, $m)) {
        $u = user();
        if ($method === "GET") {
            respond(orderFor((int) $m[1], $u));
        }
        if ($method === "PUT") {
            $next = textField(body(), "status", 20);
            if ($next === "cancelled") {
                cancelOrder($u, (int) $m[1]);
            } else {
                changeOrder($u, (int) $m[1], $next);
            }
            respond(["ok" => true, "email_queued" => hasPendingOrderMail((int) $m[1])]);
        }
    }
    if ($r === "dashboard" && $method === "GET") {
        role(["owner", "employee"]);
        respond(dashboard());
    }
    if ($r === "customers" && $method === "GET") {
        role(["owner"]);
        respond([
            "customers" => all(
                "SELECT u.id,u.name,u.email,u.active,u.created_at,COUNT(o.id) AS order_count,COALESCE(SUM(CASE WHEN o.status='completed' THEN o.total_cents ELSE 0 END),0) AS spent FROM users u LEFT JOIN orders o ON o.user_id=u.id WHERE u.role='customer' GROUP BY u.id,u.name,u.email,u.active,u.created_at ORDER BY u.id DESC",
            ),
        ]);
    }
    if ($r === "team" && $method === "GET") {
        role(["owner"]);
        respond([
            "team" => all(
                "SELECT id,name,email,role,active,created_at FROM users WHERE role IN ('owner','employee') ORDER BY id",
            ),
        ]);
    }
    if ($r === "team" && $method === "POST") {
        $u = role(["owner"]);
        $b = body();
        $email = strtolower(textField($b, "email", 150));
        $name = textField($b, "name", 100);
        $pass = textField($b, "password", 72);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 12) {
            throw new HttpError(
                "Use a valid email and a password of at least 12 characters.",
            );
        }
        checkNewPassword($pass);
        query(
            "INSERT INTO users (name,email,password_hash,role,created_at) VALUES (?,?,?,?,?)",
            [
                $name,
                $email,
                password_hash($pass, PASSWORD_DEFAULT),
                "employee",
                now(),
            ],
        );
        audit((int) $u["id"], "Employee added", $name);
        respond(["ok" => true]);
    }
    if (preg_match('~^users/(\d+)$~', $r, $m) && $method === "PUT") {
        $u = role(["owner"]);
        $target = one("SELECT id,role,name FROM users WHERE id=?", [
            (int) $m[1],
        ]);
        if (!$target || $target["role"] === "owner") {
            throw new HttpError("This account cannot be changed here.", 403);
        }
        $active = integer(body()["active"] ?? -1, 0, 1);
        query("UPDATE users SET active=? WHERE id=?", [$active, $target["id"]]);
        audit(
            (int) $u["id"],
            "Account " . ($active ? "enabled" : "disabled"),
            $target["name"],
        );
        respond(["ok" => true]);
    }
    if ($r === "settings" && $method === "PUT") {
        $u = role(["owner"]);
        $b = body();
        atomic(function () use ($u, $b) {
            foreach (
                [
                    "store_name" => 100,
                    "store_address" => 250,
                    "store_hours" => 100,
                    "store_notice" => 250,
                ]
                as $k => $max
            ) {
                setSetting($k, textField($b, $k, $max));
            }
            setSetting("location_confirmed", "1");
            audit(
                (int) $u["id"],
                "Store settings updated",
                "Pickup location and store details",
            );
        });
        respond(["ok" => true]);
    }
    if ($r === "password" && $method === "PUT") {
        $u = user();
        $b = body();
        $current = textField($b, "current", 72);
        $next = textField($b, "password", 72);
        throttle("password:" . $u["id"], 5, 60);
        $hash = one("SELECT password_hash FROM users WHERE id=?", [$u["id"]])[
            "password_hash"
        ];
        if (!password_verify($current, $hash)) {
            throw new HttpError("Current password is incorrect.");
        }
        if (password_verify($next, $hash)) {
            throw new HttpError("Choose a different new password.");
        }
        if (strlen($next) < 12) {
            throw new HttpError("Use at least 12 characters.");
        }
        checkNewPassword($next);
        query(
            "UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?",
            [password_hash($next, PASSWORD_DEFAULT), $u["id"]],
        );
        session_regenerate_id(true);
        respond(["ok" => true]);
    }
    if (str_starts_with($r, "integrations/")) {
        integrationRoute(substr($r, 13), $method);
    }
    throw new HttpError("Endpoint not found.", 404);
} catch (HttpError $e) {
    respond(["error" => $e->getMessage()], $e->status);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $duplicate = in_array((string) $e->getCode(), ["23000", "23505"], true);
    respond(
        [
            "error" => $duplicate
                ? "This SKU, email, or category already exists."
                : "Database operation failed. Check setup or contact the administrator.",
        ],
        $duplicate ? 409 : 500,
    );
} catch (Throwable $e) {
    error_log((string) $e);
    respond(
        [
            "error" =>
                "Something went wrong. Your changes may not have been saved. Please refresh and check.",
        ],
        500,
    );
}
