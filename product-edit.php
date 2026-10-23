<?php require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$id = (int) ($_GET["id"] ?? 0);
$p = $id
    ? one("SELECT * FROM products WHERE id=? AND deleted=0", [$id])
    : [
        "id" => 0,
        "name" => "",
        "sku" => "",
        "brand" => "",
        "category_id" => 1,
        "description" => "",
        "price_cents" => 0,
        "stock" => 0,
        "low_stock" => 10,
        "image_url" => "",
        "active" => 1,
        "version" => 1,
    ];
if (!$p) {
    throw new HttpError("Product not found.", 404);
}
$categories = all("SELECT * FROM categories ORDER BY id");
pageStart($id ? "Edit product" : "Add product");
require ROOT . "/includes/views/product-edit.php";
pageEnd();
