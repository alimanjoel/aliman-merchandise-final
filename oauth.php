<?php
declare(strict_types=1);
require __DIR__ . "/app/bootstrap.php";
require ROOT . "/app/services/integrations.php";
try {
    requireIntegration("google");
    $flow = $_SESSION["oauth"] ?? null;
    unset($_SESSION["oauth"]);
    if (
        !$flow ||
        $flow["expires"] < time() ||
        !hash_equals($flow["state"], (string) ($_GET["state"] ?? ""))
    ) {
        throw new HttpError("Google sign-in expired. Please try again.");
    }
    if (!isset($_GET["code"])) {
        throw new HttpError("Google sign-in was cancelled.");
    }
    $token = remote(
        "google",
        "https://oauth2.googleapis.com/token",
        "POST",
        http_build_query([
            "code" => $_GET["code"],
            "client_id" => config("google_client_id"),
            "client_secret" => config("google_client_secret"),
            "redirect_uri" => appUrl("oauth.php"),
            "grant_type" => "authorization_code",
            "code_verifier" => $flow["verifier"],
        ]),
        ["Content-Type: application/x-www-form-urlencoded"],
    );
    if (empty($token["access_token"])) {
        throw new HttpError("Google did not grant sign-in access.");
    }
    $profile = remote(
        "google",
        "https://openidconnect.googleapis.com/v1/userinfo",
        "GET",
        null,
        ["Authorization: Bearer " . $token["access_token"]],
    );
    $id = googleAccountId($profile, $flow);
    session_regenerate_id(true);
    $_SESSION["user_id"] = $id;
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
    header(
        "Location: " .
            (user()["role"] === "customer" ? "index.php" : "dashboard.php"),
    );
} catch (Throwable $e) {
    $_SESSION["oauth_error"] =
        $e instanceof HttpError
            ? $e->getMessage()
            : "Google sign-in failed. Please try again.";
    $_SESSION["flash"] = [
        "message" => $_SESSION["oauth_error"],
        "type" => "error",
    ];
    header("Location: login.php");
}
exit();
