<?php
require __DIR__ . "/includes/functions.php";
$u = signedIn();
$where = "1=1";
$args = [];
$status = (string) ($_GET["status"] ?? "");
$q = trim(substr((string) ($_GET["q"] ?? ""), 0, 100));
if ($u["role"] === "customer") {
    $where .= " AND o.user_id=?";
    $args[] = $u["id"];
}
if (in_array($status, ["pending", "ready", "completed", "cancelled"], true)) {
    $where .= " AND o.status=?";
    $args[] = $status;
}
if ($q !== "") {
    $where .=
        " AND (o.reference LIKE ? OR u.name LIKE ? OR o.claim_code LIKE ?)";
    array_push($args, "%" . $q . "%", "%" . $q . "%", "%" . $q . "%");
}
$orders = all(
    "SELECT o.*,u.name AS customer_name FROM orders o JOIN users u ON u.id=o.user_id WHERE $where ORDER BY o.id DESC LIMIT 250",
    $args,
);
pageStart("Orders");
require ROOT . "/includes/views/orders.php";
pageEnd();
