<?php
require __DIR__ . "/includes/functions.php";
if (!user(false)) {
    go("login.php");
}
signedIn();
$search = trim(substr((string) ($_GET["q"] ?? ""), 0, 100));
$category = max(0, (int) ($_GET["category"] ?? 0));
$sort =
    [
        "name" => "p.name",
        "price_asc" => "p.price_cents ASC",
        "price_desc" => "p.price_cents DESC",
        "new" => "p.id DESC",
    ][$_GET["sort"] ?? "name"] ?? "p.name";
$where = "p.active=1 AND p.deleted=0";
$args = [];
if ($search !== "") {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $args = array_fill(0, 3, "%" . $search . "%");
}
if ($category) {
    $where .= " AND p.category_id=?";
    $args[] = $category;
}
$products = all(
    "SELECT p.*,c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY $sort",
    $args,
);
$categories = all("SELECT * FROM categories ORDER BY id");
$u = user(false);
pageStart("Shop");
require ROOT . "/includes/views/index.php";
pageEnd();
