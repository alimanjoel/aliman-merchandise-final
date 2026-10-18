<?php
declare(strict_types=1);
require_once ROOT . "/app/services/paymongo.php";
require_once ROOT . "/app/services/order-actions.php";
require_once ROOT . "/app/services/google.php";
require_once ROOT . "/app/services/email.php";
require_once ROOT . "/app/services/passwords.php";
require_once ROOT . "/app/services/shop-qr.php";
// Every remote URL is chosen by server code. Secret keys never reach the browser.
function integrationFields(string $provider): array
{
    $keys = [
        "paymongo" => ["paymongo_secret_key"],
        "google" => ["google_client_id", "google_client_secret"],
        "resend" => ["resend_key"],
        "pwnedpasswords" => [],
        "qrserver" => [],
        "quickchart" => [],
    ];
    return $keys[$provider] ?? ["missing"];
}
function integrationReady(string $provider): bool
{
    if ($provider === "pwnedpasswords") {
        return (bool) config("password_breach_check");
    }
    foreach (integrationFields($provider) as $key) {
        if (!config($key)) {
            return false;
        }
    }
    return true;
}
function apiEvent(string $provider, string $status, string $message): void
{
    $upsert =
        config("db_driver") === "sqlite"
            ? " ON CONFLICT(provider) DO UPDATE SET status=excluded.status,message=excluded.message,checked_at=excluded.checked_at"
            : " ON DUPLICATE KEY UPDATE status=VALUES(status),message=VALUES(message),checked_at=VALUES(checked_at)";
    query(
        "INSERT INTO api_events (provider,status,message,checked_at) VALUES (?,?,?,?)" .
            $upsert,
        [$provider, $status, $message, now()],
    );
}
function remote(
    string $provider,
    string $url,
    string $method = "GET",
    mixed $data = null,
    array $headers = [],
    bool $json = true,
): mixed {
    if (!function_exists("curl_init")) {
        throw new HttpError(
            "Enable the PHP cURL extension to use external APIs.",
            503,
        );
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (
        parse_url($url, PHP_URL_SCHEME) !== "https" ||
        !in_array(
            $host,
            [
                "api.paymongo.com",
                "oauth2.googleapis.com",
                "openidconnect.googleapis.com",
                "api.resend.com",
                "api.pwnedpasswords.com",
                "api.qrserver.com",
                "quickchart.io",
            ],
            true,
        )
    ) {
        throw new HttpError("External endpoint is not allowed.", 500);
    }
    $ch = curl_init($url);
    $response = "";
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $provider === "pwnedpasswords" ? 5 : 15,
        CURLOPT_TIMEOUT => $provider === "pwnedpasswords" ? 8 : 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => "AlimanMerchandise/2.0",
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$response) {
            if (strlen($response) + strlen($chunk) > 6 * 1024 * 1024) {
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        },
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $ok = curl_exec($ch);
    $connectionError = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($ok === false || $status < 200 || $status >= 300) {
        $message = $ok === false
            ? providerConnectionMessage($provider, $connectionError)
            : providerFailureMessage($provider, $status, $response);
        apiEvent($provider, "error", $message);
        throw new HttpError($message, 502);
    }
    if (!$json) {
        apiEvent(
            $provider,
            "connected",
            "Last request completed successfully.",
        );
        return ["body" => $response, "mime" => $mime];
    }
    try {
        $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("Expected a response object.");
        }
        apiEvent(
            $provider,
            "connected",
            "Last request completed successfully.",
        );
        return $decoded;
    } catch (Throwable) {
        apiEvent(
            $provider,
            "error",
            "The provider returned an invalid response.",
        );
        throw new HttpError("The provider returned an invalid response.", 502);
    }
}
function requireIntegration(string $p): void
{
    if (!integrationReady($p)) {
        throw new HttpError(
            ucfirst($p) .
                " is not configured yet. The owner must add its developer credentials.",
            503,
        );
    }
}
function appUrl(string $path = ""): string
{
    return rtrim((string) config("app_url"), "/") . "/" . ltrim($path, "/");
}
function integrationRoute(string $route, string $method): never
{
    if (
        $method === "POST" &&
        preg_match('~^paymongo/(\d+)/(create|check|cancel)$~', $route, $m)
    ) {
        paymongoRoute((int) $m[1], $m[2]);
    }
    if ($route === "status" && $method === "GET") {
        role(["owner"]);
        $events = [];
        foreach (all("SELECT * FROM api_events") as $e) {
            $events[$e["provider"]] = $e;
        }
        $items = [];
        foreach (
            [
                "paymongo",
                "google",
                "resend",
                "pwnedpasswords",
                "qrserver",
                "quickchart",
            ]
            as $p
        ) {
            $items[] = [
                "provider" => $p,
                "configured" => integrationReady($p),
                "event" => $events[$p] ?? null,
            ];
        }
        respond([
            "integrations" => $items,
            "pending_emails" => (int) one(
                "SELECT COUNT(*) AS n FROM email_outbox WHERE status='pending'",
            )["n"],
        ]);
    }
    if ($route === "google/start" && $method === "POST") {
        requireIntegration("google");
        throttle("google:" . ($_SERVER["REMOTE_ADDR"] ?? ""), 10, 60);
        $state = bin2hex(random_bytes(32));
        $verifier = bin2hex(random_bytes(32));
        $_SESSION["oauth"] = [
            "state" => $state,
            "verifier" => $verifier,
            "expires" => time() + 600,
            "link_id" => user(false)["id"] ?? null,
        ];
        $args = [
            "client_id" => config("google_client_id"),
            "redirect_uri" => appUrl("oauth.php"),
            "response_type" => "code",
            "scope" => "openid email profile",
            "state" => $state,
            "code_challenge" => rtrim(
                strtr(
                    base64_encode(hash("sha256", $verifier, true)),
                    "+/",
                    "-_",
                ),
                "=",
            ),
            "code_challenge_method" => "S256",
            "prompt" => "select_account",
        ];
        respond([
            "url" =>
                "https://accounts.google.com/o/oauth2/v2/auth?" .
                http_build_query($args),
        ]);
    }
    if ($route === "chart" && $method === "GET") {
        role(["owner"]);
        throttle("chart", 10, 60);
        $d = dashboard();
        $sales = $d["sales"];
        $chart = [
            "type" => "bar",
            "data" => [
                "labels" => array_column($sales, "day"),
                "datasets" => [
                    [
                        "label" => "Completed sales (PHP)",
                        "data" => array_map(
                            fn($v) => $v / 100,
                            array_column($sales, "total"),
                        ),
                        "backgroundColor" => "#256b5e",
                    ],
                ],
            ],
            "options" => [
                "plugins" => ["legend" => ["display" => false]],
                "scales" => ["y" => ["beginAtZero" => true]],
            ],
        ];
        $url =
            "https://quickchart.io/chart?" .
            http_build_query([
                "c" => json_encode($chart),
                "width" => 1000,
                "height" => 450,
                "format" => "png",
                "version" => "4",
                "backgroundColor" => "white",
            ]);
        $key = hash("sha256", $url);
        $cached = one("SELECT body FROM api_cache WHERE id=? AND expires>?", [
            $key,
            time(),
        ]);
        if ($cached) {
            $bytes = base64_decode($cached["body"]);
        } else {
            $data = remote("quickchart", $url, "GET", null, [], false);
            $bytes = $data["body"];
            if (!str_starts_with($bytes, "\x89PNG")) {
                throw new HttpError(
                    "Chart service returned an invalid image.",
                    502,
                );
            }
            query("DELETE FROM api_cache WHERE id=?", [$key]);
            query(
                "INSERT INTO api_cache (id,provider,body,mime,expires,created_at) VALUES (?,?,?,?,?,?)",
                [
                    $key,
                    "quickchart",
                    base64_encode($bytes),
                    "image/png",
                    time() + 3600,
                    now(),
                ],
            );
        }
        header("Content-Type: image/png");
        header(
            'Content-Disposition: attachment; filename="aliman-sales-' .
                date("Y-m-d") .
                '.png"',
        );
        header("Cache-Control: private, max-age=0");
        echo $bytes;
        exit();
    }
    if ($route === "email/retry" && $method === "POST") {
        role(["owner"]);
        $id = integer(body()["order_id"] ?? 0, 1);
        if (!integrationReady(emailProvider())) {
            throw new HttpError(
                "Configure Resend in integrations-config.php before retrying emails.",
                503,
            );
        }
        throttle("mail-retry:" . user()["id"], 5, 60);
        respond(["sent" => sendOrderMail($id, true)]);
    }
    if ($route === "passwords/test" && $method === "POST") {
        $owner = role(["owner"]);
        requireIntegration("pwnedpasswords");
        throttle("password-demo:" . $owner["id"], 5, 60);
        $count = breachedPasswordCount("Password123!");
        if ($count === null) {
            throw new HttpError(
                "Password breach service could not be checked. Try again when internet access is available.",
                503,
            );
        }
        respond([
            "message" =>
                $count > 0
                    ? "The known breached demo password was detected. Password protection is working."
                    : "The provider responded, but the demo password was not found in its current data.",
        ]);
    }
    if ($route === "qr/generate" && $method === "POST") {
        $owner = role(["owner"]);
        throttle("qr:" . $owner["id"], 5, 60);
        generateShopQr(textField(body(), "target", 16));
        respond(["ok" => true]);
    }
    throw new HttpError("Integration endpoint not found.", 404);
}
