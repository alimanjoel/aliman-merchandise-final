<?php
$isWelcome = in_array(currentPage(), ["login.php", "signup.php"], true);
$isStaff = $currentUser && $currentUser["role"] !== "customer";
$pageClass = pathinfo(currentPage(), PATHINFO_FILENAME) . "-page";
$bodyClasses = $pageClass . ($isWelcome ? " welcome-page" : "") . ($isStaff ? " staff-page" : "");
$cartCount = $currentUser && !$isStaff
    ? (int) one("SELECT COALESCE(SUM(quantity),0) AS n FROM cart_items WHERE user_id=?", [$currentUser["id"]])["n"]
    : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#3c513b">
  <title><?= h($pageTitle) ?> · Aliman Merchandise</title>
  <link rel="icon" href="<?= h(url("assets/images/mark.svg")) ?>" type="image/svg+xml">
  <?php foreach (["style", "layout", "store", "account", "workspace"] as $sheet): ?>
  <link rel="stylesheet" href="<?= h(assetUrl("assets/css/" . $sheet . ".css")) ?>">
  <?php endforeach; ?>
  <?php foreach (["site", "account", "orders"] as $script): ?>
  <script src="<?= h(assetUrl("assets/js/" . $script . ".js")) ?>" defer></script>
  <?php endforeach; ?>
</head>
<body class="<?= h($bodyClasses) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="announcement"><?= h(setting("store_notice", "Order online. Pick up when it is ready.")) ?></div>
<header class="site-header">
  <a class="brand" href="<?= h(url($currentUser ? "index.php" : "login.php")) ?>" aria-label="Aliman Merchandise home">
    <img src="<?= h(url("assets/images/mark.svg")) ?>" alt="" width="44" height="44">
    <span>aliman<span class="brand-sub">MERCHANDISE</span></span>
  </a>
  <nav aria-label="Main navigation">
    <?php if ($currentUser): ?>
      <a href="<?= h(url()) ?>" <?= currentPage() === "index.php" ? 'aria-current="page"' : "" ?>>Shop</a>
      <?php if ($isStaff): ?>
        <a class="nav-workspace" href="<?= h(url("dashboard.php")) ?>">Workspace</a>
      <?php else: ?>
        <a class="nav-cart" href="<?= h(url("cart.php")) ?>" <?= currentPage() === "cart.php" ? 'aria-current="page"' : "" ?>><?= icon("bag") ?> Cart <span class="count"><?= $cartCount ?></span></a>
      <?php endif; ?>
      <a href="<?= h(url("orders.php")) ?>" <?= in_array(currentPage(), ["orders.php", "order.php"], true) ? 'aria-current="page"' : "" ?>>Orders</a>
      <a href="<?= h(url("account.php")) ?>" <?= currentPage() === "account.php" ? 'aria-current="page"' : "" ?>>Account</a>
      <form action="<?= h(url("server.php")) ?>" method="post" class="inline-form">
        <?php actionFields("logout"); ?><button type="submit" class="text-button">Sign out</button>
      </form>
    <?php else: ?>
      <a href="<?= h(url("login.php#account")) ?>">Sign in</a>
      <a class="button secondary small" href="<?= h(url("signup.php#account")) ?>">Create account</a>
    <?php endif; ?>
  </nav>
</header>
<div class="app-shell">
<?php if ($isStaff): ?>
  <?php require ROOT . "/includes/workspace-nav.php"; ?>
<?php endif; ?>
<main id="main-content" class="container" tabindex="-1">
<?php if (!empty($_SESSION["flash"])):
    $notice = $_SESSION["flash"];
    unset($_SESSION["flash"]);
?>
  <div class="notice <?= h($notice["type"]) ?>" role="status"><?= h($notice["message"]) ?></div>
<?php endif; ?>
