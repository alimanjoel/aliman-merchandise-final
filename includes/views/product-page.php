<a class="back-link" href="<?= h(url()) ?>">← Back to collection</a>
<section class="product-detail">
  <div class="detail-photo"><img src="<?= h(productImage($p)) ?>" alt="<?= h($p["name"]) ?>" /></div>
  <div>
    <p class="eyebrow"><?= h($p["category_name"]) ?></p>
    <h1><?= h($p["name"]) ?></h1>
    <p class="detail-price"><?= money($p["price_cents"]) ?></p>
    <p class="description"><?= nl2br(h($p["description"])) ?></p>
    <p class="muted">SKU <?= h($p["sku"]) . ($p["brand"] ? " · " . h($p["brand"]) : "") ?></p>
    <p><?= $p["stock"] ? (int) $p["stock"] . " available for pickup" : "Currently sold out" ?></p>
    <?php if ($u && $u["role"] === "customer"): ?>
    <form method="post" action="<?= h(url("server.php")) ?>" class="stack">
      <?php actionFields("cart_add", "cart.php"); ?>
      <input type="hidden" name="product_id" value="<?= (int) $p["id"] ?>" />
      <label class="quantity-label">
        Quantity
        <input
          type="number"
          name="quantity"
          min="1"
          max="<?= min(99, (int) $p["stock"]) ?>"
          value="1"
          required
        />
      </label>
      <button class="button" <?= $p["stock"] ? "" : "disabled" ?>>Add to cart</button>
    </form>
    <?php elseif (!$u): ?>
    <a class="button" href="<?= h(url("login.php")) ?>">Sign in to add to cart</a>
    <?php else: ?>
    <a class="button secondary" href="<?= h(url("inventory.php")) ?>">View inventory</a>
    <?php endif; ?>
    <div class="pickup-note">
      <?= icon("pin") ?>
      <div>
        <strong>Local pickup</strong>
        <p>
          <?= h(setting("store_address")) ?>
          <br />
          <?= h(setting("store_hours")) ?>
        </p>
      </div>
    </div>
  </div>
</section>
