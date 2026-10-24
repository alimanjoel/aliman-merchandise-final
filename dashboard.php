<?php require __DIR__ . "/includes/functions.php";
$u = signedIn(["owner", "employee"]);
$d = dashboard();
$stats = [
    ["Completed sales", money($d["summary"]["revenue_cents"]), "Last 30 days"],
    ["Pending orders", $d["summary"]["pending"], "Waiting for preparation"],
    ["Ready for pickup", $d["summary"]["ready"], "Waiting for collection"],
    ["Low-stock items", $d["summary"]["low_stock"], "Review your inventory"],
];
pageStart("Store overview");
require ROOT . "/includes/views/dashboard.php";
pageEnd();
