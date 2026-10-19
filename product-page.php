<?php require __DIR__ . "/includes/functions.php";
signedIn();
$p = one(
    "SELECT p.*,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1 AND p.deleted=0",
    [(int) ($_GET["id"] ?? 0)],
);
if (!$p) {
    throw new HttpError("Product not found.", 404);
}
$u = user(false);
pageStart($p["name"]);
require ROOT . "/includes/views/product-page.php";
pageEnd();
