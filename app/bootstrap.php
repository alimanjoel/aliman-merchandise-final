<?php
declare(strict_types=1);
date_default_timezone_set("Asia/Manila");
define("ROOT", dirname(__DIR__));
$local = array_merge(
    require ROOT . "/database.php",
    require is_file(ROOT . "/integrations-config.php")
        ? ROOT . "/integrations-config.php"
        : ROOT . "/integrations-config.example.php",
);
$config = array_merge(
    [
        "db_driver" => getenv("DB_DRIVER") ?: "sqlite",
        "db_path" => getenv("DB_PATH") ?: ROOT . "/storage/aliman.sqlite",
        "db_host" => getenv("DB_HOST") ?: "127.0.0.1",
        "db_port" => getenv("DB_PORT") ?: "3306",
        "db_name" => getenv("DB_NAME") ?: "aliman_final",
        "db_user" => getenv("DB_USER") ?: "root",
        "db_pass" => getenv("DB_PASS") ?: "",
        "db_ssl_ca" => getenv("DB_SSL_CA") ?: "",
        "secure_cookies" => getenv("SECURE_COOKIES") === "true",
        "app_url" => getenv("APP_URL") ?: "http://localhost:8080",
        "google_client_id" => getenv("GOOGLE_CLIENT_ID") ?: "",
        "google_client_secret" => getenv("GOOGLE_CLIENT_SECRET") ?: "",
        "resend_key" => getenv("RESEND_KEY") ?: "",
        "password_breach_check" => true,
        "mail_from" =>
            getenv("MAIL_FROM") ?: "Aliman Merchandise <onboarding@resend.dev>",
    ],
    $local,
);
if (PHP_SAPI !== "cli") {
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    session_name("aliman_session");
    $sessionPath = ROOT . "/storage/sessions";
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }
    session_save_path($sessionPath);
    session_set_cookie_params([
        "lifetime" => 0,
        "path" =>
            rtrim(
                (string) parse_url((string) $config["app_url"], PHP_URL_PATH),
                "/",
            ) . "/",
        "secure" => $config["secure_cookies"],
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
    if (
        isset($_SESSION["last_seen"]) &&
        time() - $_SESSION["last_seen"] > 7200
    ) {
        session_unset();
        session_regenerate_id(true);
    }
    $_SESSION["last_seen"] = time();
    $_SESSION["csrf"] ??= bin2hex(random_bytes(32));
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: same-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://accounts.google.com https://checkout.paymongo.com",
    );
}
class HttpError extends RuntimeException
{
    public function __construct(string $message, public int $status = 400)
    {
        parent::__construct($message);
    }
}
function config(string $key): mixed
{
    global $config;
    return $config[$key] ?? null;
}
function db(): PDO
{
    static $db;
    if ($db) {
        return $db;
    }
    $sqlite = config("db_driver") === "sqlite";
    if ($sqlite) {
        if (PHP_SAPI !== "cli" && !is_file(config("db_path"))) {
            throw new HttpError(
                "Import database/aliman_merchandise.sql through phpMyAdmin before opening the system.",
                503,
            );
        }
        $db = new PDO("sqlite:" . config("db_path"));
        $db->exec("PRAGMA foreign_keys=ON");
        $db->exec("PRAGMA busy_timeout=5000");
        $db->exec("PRAGMA journal_mode=WAL");
    } else {
        $options = [PDO::ATTR_TIMEOUT => 5];
        if (config("db_ssl_ca")) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = config("db_ssl_ca");
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        $db = new PDO(
            "mysql:host=" .
                config("db_host") .
                ";port=" .
                config("db_port") .
                ";dbname=" .
                config("db_name") .
                ";charset=utf8mb4",
            config("db_user"),
            config("db_pass"),
            $options,
        );
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}
function query(string $sql, array $args = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($args);
    return $s;
}
function one(string $sql, array $args = []): ?array
{
    return query($sql, $args)->fetch() ?: null;
}
function all(string $sql, array $args = []): array
{
    return query($sql, $args)->fetchAll();
}
function atomic(callable $fn): mixed
{
    $sqlite = config("db_driver") === "sqlite";
    if ($sqlite) {
        db()->exec("BEGIN IMMEDIATE");
    } else {
        db()->beginTransaction();
    }
    try {
        $result = $fn();
        if ($sqlite) {
            db()->exec("COMMIT");
        } else {
            db()->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($sqlite) {
            db()->exec("ROLLBACK");
        } elseif (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}
function lockSuffix(): string
{
    return config("db_driver") === "mysql" ? " FOR UPDATE" : "";
}
function now(): string
{
    return date("Y-m-d H:i:s");
}
function respond(mixed $data, int $status = 200): never
{
    if (defined("TRADITIONAL_FORM")) {
        formResponse($data, $status);
    }
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit();
}
function body(): array
{
    if (defined("TRADITIONAL_FORM")) {
        return $_POST;
    }
    if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > 100000) {
        throw new HttpError("Request is too large.", 413);
    }
    try {
        $b = json_decode(
            file_get_contents("php://input"),
            true,
            32,
            JSON_THROW_ON_ERROR,
        );
    } catch (Throwable) {
        throw new HttpError("Invalid JSON request.");
    }
    if (!is_array($b)) {
        throw new HttpError("Expected a JSON object.");
    }
    return $b;
}
function csrf(): void
{
    if (
        !hash_equals(
            $_SESSION["csrf"] ?? "",
            $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "invalid",
        )
    ) {
        throw new HttpError(
            "Your session changed. Refresh the page and try again.",
            419,
        );
    }
}
function user(bool $required = true): ?array
{
    $u = isset($_SESSION["user_id"])
        ? one(
            "SELECT id,name,email,role,active,must_change_password,created_at FROM users WHERE id=?",
            [$_SESSION["user_id"]],
        )
        : null;
    if ($u && !$u["active"]) {
        unset($_SESSION["user_id"]);
        $u = null;
    }
    if (!$u && $required) {
        throw new HttpError("Please sign in to continue.", 401);
    }
    return $u;
}
function role(array $roles): array
{
    $u = user();
    if (!empty($u["must_change_password"])) {
        throw new HttpError(
            "Change your starter password from Account first.",
            403,
        );
    }
    if (!in_array($u["role"], $roles, true)) {
        throw new HttpError("You do not have access to this action.", 403);
    }
    return $u;
}
function textField(
    array $b,
    string $key,
    int $max = 200,
    bool $required = true,
): string {
    if (isset($b[$key]) && !is_string($b[$key])) {
        throw new HttpError("Invalid $key.");
    }
    $s = trim($b[$key] ?? "");
    if (($required && $s === "") || strlen($s) > $max) {
        throw new HttpError("Check the $key field (maximum $max characters).");
    }
    return $s;
}
function integer(mixed $v, int $min = 0, int $max = 1000000): int
{
    if (
        filter_var($v, FILTER_VALIDATE_INT) === false ||
        (int) $v < $min ||
        (int) $v > $max
    ) {
        throw new HttpError("Enter a whole number between $min and $max.");
    }
    return (int) $v;
}
function cents(mixed $v): int
{
    if (!is_scalar($v) || !preg_match('/^\d{1,7}(\.\d{1,2})?$/', (string) $v)) {
        throw new HttpError(
            "Enter a valid price with up to two decimal places.",
        );
    }
    return (int) round((float) $v * 100);
}
function audit(int $uid, string $action, string $detail): void
{
    query(
        "INSERT INTO activity (user_id,action,detail,created_at) VALUES (?,?,?,?)",
        [$uid, $action, $detail, now()],
    );
}
function setting(string $key, string $default = ""): string
{
    return one("SELECT value FROM settings WHERE name=?", [$key])["value"] ??
        $default;
}
function setSetting(string $key, string $value): void
{
    $s = query("UPDATE settings SET value=? WHERE name=?", [$value, $key]);
    if (
        !$s->rowCount() &&
        !one("SELECT name FROM settings WHERE name=?", [$key])
    ) {
        query("INSERT INTO settings (name,value) VALUES (?,?)", [$key, $value]);
    }
}
function throttle(string $key, int $max = 10, int $seconds = 60): void
{
    atomic(function () use ($key, $max, $seconds) {
        $key = hash("sha256", $key);
        $r = one("SELECT * FROM rate_limits WHERE id=?" . lockSuffix(), [$key]);
        if (!$r) {
            query("INSERT INTO rate_limits (id,hits,expires) VALUES (?,1,?)", [
                $key,
                time() + $seconds,
            ]);
            return;
        }
        if ((int) $r["expires"] <= time()) {
            query("UPDATE rate_limits SET hits=1,expires=? WHERE id=?", [
                time() + $seconds,
                $key,
            ]);
        } elseif ((int) $r["hits"] >= $max) {
            throw new HttpError(
                "Too many attempts. Please wait a minute before trying again.",
                429,
            );
        } else {
            query("UPDATE rate_limits SET hits=hits+1 WHERE id=?", [$key]);
        }
    });
}
