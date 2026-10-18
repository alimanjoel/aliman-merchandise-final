<?php
declare(strict_types=1);

function shopQrDestination(string $target): string
{
    if ($target === "facebook") {
        return "https://www.facebook.com/AlimanMerchandise";
    }
    if ($target !== "shop") {
        throw new HttpError("Choose the shop or Facebook page.");
    }
    $url = appUrl();
    $parts = parse_url($url);
    $host = strtolower($parts["host"] ?? "");
    $isPublicIp = filter_var(
        $host,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
    );
    if (
        !filter_var($url, FILTER_VALIDATE_URL) ||
        ($parts["scheme"] ?? "") !== "https" ||
        isset($parts["user"]) ||
        isset($parts["pass"]) ||
        isset($parts["query"]) ||
        isset($parts["fragment"]) ||
        !str_contains($host, ".") ||
        $host === "localhost" ||
        str_ends_with($host, ".localhost") ||
        str_ends_with($host, ".local") ||
        str_ends_with($host, ".test") ||
        (filter_var($host, FILTER_VALIDATE_IP) && !$isPublicIp)
    ) {
        throw new HttpError(
            "The shop QR needs a public HTTPS app_url in database.php. Use the Facebook QR for a localhost demonstration.",
        );
    }
    return $url;
}

function shopQrKey(string $target): string
{
    return hash("sha256", "shop-qr:" . shopQrDestination($target));
}

function cachedShopQr(string $target): ?string
{
    $row = one(
        "SELECT body FROM api_cache WHERE id=? AND provider='qrserver'",
        [shopQrKey($target)],
    );
    if (!$row) {
        return null;
    }
    $bytes = base64_decode($row["body"], true);
    return is_string($bytes) && str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
        ? $bytes
        : null;
}

function generateShopQr(string $target): void
{
    $destination = shopQrDestination($target);
    if (cachedShopQr($target) !== null) {
        return;
    }
    $result = remote(
        "qrserver",
        "https://api.qrserver.com/v1/create-qr-code/?" .
            http_build_query([
                "data" => $destination,
                "size" => "512x512",
                "format" => "png",
                "ecc" => "M",
                "qzone" => 4,
            ]),
        "GET",
        null,
        [],
        false,
    );
    $bytes = $result["body"];
    $size = @getimagesizefromstring($bytes);
    if (
        !$size ||
        $size[2] !== IMAGETYPE_PNG ||
        $size[0] !== 512 ||
        $size[1] !== 512
    ) {
        apiEvent(
            "qrserver",
            "error",
            "The QR service did not return a valid PNG. Try again later.",
        );
        throw new HttpError(
            "The QR service did not return a valid PNG. Try again later.",
            502,
        );
    }
    $upsert =
        config("db_driver") === "sqlite"
            ? " ON CONFLICT(id) DO UPDATE SET body=excluded.body,created_at=excluded.created_at"
            : " ON DUPLICATE KEY UPDATE body=VALUES(body),created_at=VALUES(created_at)";
    query(
        "INSERT INTO api_cache (id,provider,body,mime,expires,created_at) VALUES (?,'qrserver',?,'image/png',0,?)" .
            $upsert,
        [shopQrKey($target), base64_encode($bytes), now()],
    );
    apiEvent(
        "qrserver",
        "connected",
        "Shop QR generated and saved locally for printing and offline reuse.",
    );
}
