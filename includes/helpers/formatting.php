<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
}

function money(mixed $cents): string
{
    return "₱" . number_format((int) $cents / 100, 2);
}

function productImage(array $p): string
{
    $path = $p["image_url"] ?? "";
    if (
        preg_match(
            "~^https://res\.cloudinary\.com/[a-zA-Z0-9_-]+/image/upload/~",
            $path,
        )
    ) {
        return $path;
    }
    if (
        preg_match(
            '~^(imgs/product-imgs/product-\d+\.jpg|uploads/[a-f0-9]{32}\.(jpg|png|webp))$~',
            $path,
        )
    ) {
        return url($path);
    }
    return url("assets/images/category-" . (int) $p["category_id"] . ".svg");
}

function badge(string $status): string
{
    return '<span class="badge ' .
        h($status) .
        '">' .
        h(ucfirst($status)) .
        "</span>";
}

function paymentLabel(array $order): string
{
    return match ($order["payment_method"]) {
        "paymongo" => "GCash / Maya via PayMongo" .
            (!empty($order["paymongo_livemode"]) ? "" : " — TEST PAYMENT"),
        "paypal" => "PayPal (discontinued) — TEST PAYMENT",
        default => "Cash on pickup",
    };
}
