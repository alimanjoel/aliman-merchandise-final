<?php require __DIR__ . "/includes/functions.php";
$u = signedIn();
pageStart("My account");
require ROOT . "/includes/views/account.php";
pageEnd();
