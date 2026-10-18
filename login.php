<?php require __DIR__ . "/includes/functions.php";
if (user(false)) {
    go(
        !empty(user()["must_change_password"])
            ? "account.php"
            : homeFor(user()),
    );
}
$isSignup = false;
require ROOT . "/includes/auth-page.php"; ?>
