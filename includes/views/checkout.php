<?php $checkoutStep = "checkout"; require ROOT . "/includes/checkout-steps.php"; ?>
<a class="back-link" href="<?= h(url("cart.php")) ?>">← Back to cart</a>
<h1>Checkout</h1>
<div class="two-column">
  <section class="panel">
    <h2>Pickup details</h2>
    <p>
      <?= h(setting("store_address")) ?>
      <br />
      <span class="muted"><?= h(setting("store_hours")) ?></span>
    </p>
    <form action="<?= h(url("server.php")) ?>" method="post" class="stack" id="checkout-form">
      <?php actionFields("checkout"); ?>
      <input type="hidden" name="idempotency_key" value="<?= h($_SESSION["checkout_key"]) ?>" />
      <input type="hidden" name="reviewed_total" value="<?= (int) $cart["total_cents"] ?>" />
      <label>
        Pickup date
        <input
          type="date"
          name="pickup_date"
          value="<?= $pickup->format("Y-m-d") ?>"
          min="<?= date("Y-m-d") ?>"
          max="<?= date("Y-m-d", strtotime("+30 days")) ?>"
          required
        />
      </label>
      <small class="muted">
        Monday–Saturday. Wait for your order to be marked ready before
        collecting.
      </small>
      <label>
        Payment method
        <select name="payment_method">
          <option value="cash">Cash on pickup</option>
          <?php if (integrationReady("paymongo")): ?>
          <option value="paymongo">
            GCash / Maya via PayMongo<?= paymongoLive() ? "" : " (test payment)" ?>
          </option>
          <?php endif; ?>
        </select>
      </label>
      <p class="muted">
        Cash on pickup: place your order now and pay at the store when
        collecting it.
      </p>
      <label>
        <span>Order notes <span class="muted">(optional)</span></span>
        <textarea
          name="notes"
          maxlength="500"
          rows="3"
          placeholder="Anything we should know?"
        ></textarea>
      </label>
    </form>
  </section>
  <aside class="panel order-summary">
    <h2>Your order</h2>
    <?php foreach ($cart["items"] as $p): ?>
    <div class="summary-line">
      <span><?= h($p["name"]) ?> × <?= (int) $p["quantity"] ?></span>
      <strong><?= money($p["price_cents"] * $p["quantity"]) ?></strong>
    </div>
    <?php endforeach; ?>
    <div class="summary-total">
      <span>Total</span>
      <strong><?= money($cart["total_cents"]) ?></strong>
    </div>
    <button type="submit" form="checkout-form" class="button full">Place order · <?= money($cart["total_cents"]) ?></button>
    <p class="muted">
      Ordering as <?= h($u["name"]) ?>
      <br />
      <?= h($u["email"]) ?>
    </p>
  </aside>
</div>
