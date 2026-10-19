<?php $checkoutStep = "cart"; require ROOT . "/includes/checkout-steps.php"; ?>
<div class="section-heading">
  <div>
    <p class="eyebrow">YOUR FINDS</p>
    <h1>Shopping cart</h1>
  </div>
  <a href="<?= h(url()) ?>">Continue shopping ↗</a>
</div>
<?php if (!$cart["items"]): ?>
<div class="panel empty">
  <h2>Your basket is waiting</h2>
  <p>Find something you love in our collection.</p>
  <a class="button" href="<?= h(url()) ?>">Browse products</a>
</div>
<?php else: ?>
<div class="two-column">
  <section class="panel">
    <?php foreach ($cart["items"] as $p): ?>
    <article class="cart-row">
      <img src="<?= h(productImage($p)) ?>" alt="<?= h($p["name"]) ?>" />
      <div class="cart-info">
        <h3><?= h($p["name"]) ?></h3>
        <p><?= money($p["price_cents"]) ?> each</p>
        <?php if (!$p["active"] || $p["deleted"] || $p["quantity"] > $p["stock"]): ?>
        <p class="error-text">
          Availability changed. Update or remove this item.
        </p>
        <?php endif; ?>
        <form class="inline-form" method="post" action="<?= h(url("server.php")) ?>">
          <?php actionFields("cart_update", "cart.php"); ?>
          <input type="hidden" name="product_id" value="<?= (int) $p["id"] ?>" />
          <label>
            <span class="sr-only">Quantity for <?= h($p["name"]) ?></span>
            <input
              class="quantity"
              name="quantity"
              type="number"
              min="0"
              max="99"
              value="<?= (int) $p["quantity"] ?>"
              required
            />
          </label>
          <button class="button secondary small">Update</button>
        </form>
        <form class="inline-form" method="post" action="<?= h(url("server.php")) ?>">
          <?php actionFields("cart_remove", "cart.php"); ?>
          <input type="hidden" name="product_id" value="<?= (int) $p["id"] ?>" />
          <button class="text-button danger">Remove</button>
        </form>
      </div>
      <strong><?= money($p["price_cents"] * $p["quantity"]) ?></strong>
    </article>
    <?php endforeach; ?>
  </section>
  <aside class="panel order-summary">
    <h2>Order summary</h2>
    <div class="summary-line">
      <span>Subtotal</span>
      <strong><?= money($cart["total_cents"]) ?></strong>
    </div>
    <div class="summary-line">
      <span>Collection</span>
      <span>Store pickup</span>
    </div>
    <div class="summary-total">
      <span>Total</span>
      <strong><?= money($cart["total_cents"]) ?></strong>
    </div>
    <a class="button full" href="<?= h(url("checkout.php")) ?>">Continue to checkout</a>
    <p class="muted">Stock is reserved when your order is placed.</p>
  </aside>
</div>
<?php endif; ?>
