<?php require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
pageStart("Store settings");
require ROOT . "/includes/views/settings.php";
pageEnd();
