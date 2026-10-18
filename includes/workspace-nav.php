<?php
$links = [
    "dashboard.php" => ["Overview", "grid"],
    "inventory.php" => ["Inventory", "box"],
    "orders.php" => ["Orders", "orders"],
];
if ($currentUser["role"] === "owner") {
    $links += [
        "customers.php" => ["Customers", "people"],
        "team.php" => ["Team", "people"],
        "sales-chart.php" => ["Sales", "chart"],
        "integrations.php" => ["Integrations", "link"],
        "settings.php" => ["Store settings", "settings"],
    ];
}
$activePage = ["order.php" => "orders.php", "product-edit.php" => "inventory.php", "shop-qr.php" => "integrations.php"][currentPage()] ?? currentPage();
?>
<nav class="workspace-nav" aria-label="Store workspace">
  <span class="role-label"><?= h(ucfirst($currentUser["role"])) ?> workspace</span>
  <?php foreach ($links as $path => [$label, $symbol]): ?>
    <a href="<?= h(url($path)) ?>" <?= $activePage === $path ? 'aria-current="page"' : "" ?>><?= icon($symbol) ?><span><?= h($label) ?></span></a>
  <?php endforeach; ?>
  <p class="workspace-note">Aliman Merchandise<br>Iligan City</p>
</nav>
