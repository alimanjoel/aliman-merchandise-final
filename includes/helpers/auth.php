<?php
declare(strict_types=1);

function homeFor(array $u): string
{
    return $u["role"] === "customer" ? "index.php" : "dashboard.php";
}

function signedIn(array $roles = []): array
{
    $u = user(false);
    if (!$u) {
        flash("Please sign in to continue.", "error");
        go("login.php");
    }
    if ($roles && !in_array($u["role"], $roles, true)) {
        throw new HttpError("You do not have access to this page.", 403);
    }
    if (
        !empty($u["must_change_password"]) &&
        basename($_SERVER["SCRIPT_NAME"] ?? "") !== "account.php"
    ) {
        go("account.php");
    }
    return $u;
}
