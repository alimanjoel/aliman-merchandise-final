<?php
require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$d = dashboard();
if (($_GET["download"] ?? "") === "csv") {
    header("Content-Type: text/csv; charset=utf-8");
    header('Content-Disposition: attachment; filename="aliman-sales.csv"');
    $out = fopen("php://output", "w");
    fputcsv($out, ["Date", "Completed sales (PHP)"]);
    foreach ($d["sales"] as $s) {
        fputcsv($out, [
            $s["day"],
            number_format($s["total"] / 100, 2, ".", ""),
        ]);
    }
    fclose($out);
    exit();
}
$max = max([1, ...array_map(fn($s) => (int) $s["total"], $d["sales"])]);
pageStart("Sales report");
require ROOT . "/includes/views/sales-chart.php";
pageEnd();
