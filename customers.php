<?php require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$customers = all(
    "SELECT u.id,u.name,u.email,u.active,u.created_at,COUNT(o.id) AS order_count,COALESCE(SUM(CASE WHEN o.status='completed' THEN o.total_cents ELSE 0 END),0) AS spent FROM users u LEFT JOIN orders o ON o.user_id=u.id WHERE u.role='customer' GROUP BY u.id,u.name,u.email,u.active,u.created_at ORDER BY u.id DESC",
);
pageStart("Customers");
require ROOT . "/includes/views/customers.php";
pageEnd();
