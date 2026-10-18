<?php
declare(strict_types=1);
require __DIR__ . "/includes/functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    throw new HttpError("Submit the form to continue.", 405);
}
if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > 3 * 1024 * 1024) {
    throw new HttpError("The form is too large.", 413);
}
$_SERVER["HTTP_X_CSRF_TOKEN"] = is_string($_POST["csrf"] ?? null)
    ? $_POST["csrf"]
    : "";
csrf();
$formAction = textField($_POST, "action", 40);
$id = integer($_POST["id"] ?? 0, 0);
$defaults = [
    "login" => "login.php",
    "register" => "signup.php",
    "product_save" => "inventory.php",
    "product_delete" => "inventory.php",
    "category_add" => "inventory.php",
    "employee_add" => "team.php",
    "user_status" => "customers.php",
    "settings" => "settings.php",
    "password" => "account.php",
    "email_retry" => "integrations.php",
    "password_demo" => "integrations.php",
    "qr_generate" => "shop-qr.php",
    "order_status" => "order.php?id=" . $id,
    "order_payment" => "order.php?id=" . $id,
    "checkout" => "checkout.php",
    "paymongo_cancel" => "order.php?id=" . $id,
    "paymongo_create" => "order.php?id=" . $id,
    "paymongo_check" => "order.php?id=" . $id,
    "google" => "login.php",
];
$formReturn = safeReturn(
    (string) ($_POST["return_to"] ?? ($defaults[$formAction] ?? "cart.php")),
);
$routes = [
    "login" => ["login", "POST"],
    "register" => ["register", "POST"],
    "logout" => ["logout", "POST"],
    "cart_add" => ["cart", "PUT"],
    "cart_update" => ["cart", "PUT"],
    "cart_remove" => ["cart", "DELETE"],
    "checkout" => ["orders", "POST"],
    "order_status" => ["orders/" . $id, "PUT"],
    "order_payment" => ["orders/" . $id . "/payment", "PUT"],
    "product_save" => [
        $id ? "products/" . $id : "products",
        $id ? "PUT" : "POST",
    ],
    "product_delete" => ["products/" . $id, "DELETE"],
    "category_add" => ["categories", "POST"],
    "employee_add" => ["team", "POST"],
    "user_status" => ["users/" . $id, "PUT"],
    "settings" => ["settings", "PUT"],
    "password" => ["password", "PUT"],
    "google" => ["integrations/google/start", "POST"],
    "paymongo_cancel" => ["integrations/paymongo/" . $id . "/cancel", "POST"],
    "paymongo_create" => ["integrations/paymongo/" . $id . "/create", "POST"],
    "paymongo_check" => ["integrations/paymongo/" . $id . "/check", "POST"],
    "email_retry" => ["integrations/email/retry", "POST"],
    "password_demo" => ["integrations/passwords/test", "POST"],
    "qr_generate" => ["integrations/qr/generate", "POST"],
];
if (!isset($routes[$formAction])) {
    throw new HttpError("Unknown form action.", 404);
}
try {
    if ($formAction === "cart_add") {
        $u = role(["customer"]);
        $pid = integer($_POST["product_id"] ?? 0, 1);
        $existing = one(
            "SELECT quantity FROM cart_items WHERE user_id=? AND product_id=?",
            [$u["id"], $pid],
        );
        $_POST["quantity"] =
            (int) ($existing["quantity"] ?? 0) +
            integer($_POST["quantity"] ?? 1, 1, 99);
    }
} catch (HttpError $e) {
    flash($e->getMessage(), "error");
    go($formReturn);
}
define("TRADITIONAL_FORM", true);
$_GET["route"] = $routes[$formAction][0];
$_SERVER["REQUEST_METHOD"] = $routes[$formAction][1];
require __DIR__ . "/api.php";
