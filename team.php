<?php require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$team = all(
    "SELECT id,name,email,role,active FROM users WHERE role IN ('owner','employee') ORDER BY id",
);
pageStart("Team access");
require ROOT . "/includes/views/team.php";
pageEnd();
