<?php require __DIR__ . "/includes/functions.php";
if (user(false)) {
    go(homeFor(user()));
}
$isSignup = true;
require ROOT . "/includes/auth-page.php"; ?>
