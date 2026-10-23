<?php require __DIR__ . "/includes/functions.php";
$u = signedIn(["owner", "employee"]);
$q = trim(substr((string) ($_GET["q"] ?? ""), 0, 100));
$items = all(
    "SELECT p.*,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.deleted=0 AND (p.name LIKE ? OR p.sku LIKE ?) ORDER BY p.id DESC",
    ["%" . $q . "%", "%" . $q . "%"],
);
pageStart("Inventory");
require ROOT . "/includes/views/inventory.php";
pageEnd();
