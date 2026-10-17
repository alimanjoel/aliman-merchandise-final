<?php
declare(strict_types=1);

function url(string $path = "index.php"): string
{
    $base = rtrim(
        (string) parse_url((string) config("app_url"), PHP_URL_PATH),
        "/",
    );
    return $base . "/" . ltrim($path, "/");
}

function go(string $path): never
{
    header("Location: " . url($path), true, 303);
    exit();
}

function currentPage(): string
{
    return basename($_SERVER["SCRIPT_NAME"] ?? "index.php");
}

function safeReturn(string $path): string
{
    $page = explode("?", $path)[0];
    $allowed = [
        "index.php",
        "login.php",
        "signup.php",
        "cart.php",
        "checkout.php",
        "orders.php",
        "order.php",
        "inventory.php",
        "product-edit.php",
        "customers.php",
        "team.php",
        "settings.php",
        "integrations.php",
        "shop-qr.php",
        "account.php",
        "dashboard.php",
    ];
    return in_array($page, $allowed, true) &&
        preg_match('/^[a-z-]+\.php(?:\?[a-zA-Z0-9_=&%.-]*)?$/D', $path)
        ? $path
        : "index.php";
}
