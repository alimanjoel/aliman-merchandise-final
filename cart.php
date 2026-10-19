<?php require __DIR__ . "/includes/functions.php";
$u = signedIn(["customer"]);
$cart = cart((int) $u["id"]);
pageStart("Your cart");
require ROOT . "/includes/views/cart.php";
pageEnd();
