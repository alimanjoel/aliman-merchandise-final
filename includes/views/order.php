<a class="back-link no-print" href="<?= h(url("orders.php")) ?>">← All orders</a>
<div class="section-heading">
  <div>
    <p class="eyebrow">ORDER RECEIPT</p>
    <h1><?= h($o["reference"]) ?></h1>
  </div>
  <div>
    <?= badge($o["status"]) ?>
    <button class="button secondary small no-print" type="button" data-print>
      Print receipt
    </button>
  </div>
</div>
<?php if ($cashDue): ?>
<div class="notice" role="status">
  <strong><?= $o["status"] === "ready" ? "Ready for pickup." : "Your cash order is confirmed." ?></strong>
  Pay <?= money($o["total_cents"]) ?> at the store when collecting your order. <?= $o["status"] === "pending" ? "Wait until it is marked ready for pickup." : "Show your pickup code to the staff." ?>
</div>
<?php elseif ($cancelled): ?>
<div class="notice" role="status">
  This order is cancelled. It cannot be collected.
</div>
<?php endif; ?>
<div class="two-column">
  <section class="panel">
    <div class="receipt-head">
      <div>
        <h2>Aliman Merchandise</h2>
        <p><?= h(setting("store_address")) ?></p>
      </div>
      <div>
        <strong><?= h($o["customer_name"]) ?></strong>
        <p>
          <?= h($o["email"]) ?>
          <br />
          <?= h($o["created_at"]) ?>
        </p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($o["items"] as $p): ?>
          <tr>
            <td>
              <?= h($p["name"]) ?>
              <small><?= h($p["sku"]) ?></small>
            </td>
            <td><?= money($p["price_cents"]) ?></td>
            <td><?= (int) $p["quantity"] ?></td>
            <td><?= money($p["price_cents"] * $p["quantity"]) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="summary-total">
      <span>Order total</span>
      <strong><?= money($o["total_cents"]) ?></strong>
    </div>
    <p>
      Payment: <?= h(paymentLabel($o)) ?> ·
      <strong><?= h($paymentState) ?></strong>
    </p>
    <?php if ($o["payment_status"] === "paid" && $o["paid_at"]): ?>
    <p class="muted">Payment recorded: <?= h($o["paid_at"]) ?></p>
    <?php endif; ?>
    <?php if ($o["notes"]): ?>
    <p>
      <strong>Notes:</strong>
      <?= nl2br(h($o["notes"])) ?>
    </p>
    <?php endif; ?>
  </section>
  <aside class="panel order-summary">
    <p class="eyebrow">PICKUP CODE</p>
    <div class="pickup-code"><?= h($o["claim_code"]) ?></div>
    <p>
      Pickup date:
      <strong><?= h($o["pickup_date"]) ?></strong>
      <br />
      <?= h(setting("store_hours")) ?>
    </p>
    <?php if ($o["status"] === "completed"): ?>
    <p class="muted">Collected. Thank you for shopping with us.</p>
    <?php elseif (!$cancelled): ?>
    <p class="muted">Show this code when collecting your order.</p>
    <?php endif; ?>
    <div class="stack no-print">
      <?php if ($o["payment_method"] === "paymongo" && $unpaid && !$cancelled): ?>
      <?php if ($u["role"] === "customer"): ?>
      <form method="post" action="<?= h(url("server.php")) ?>">
        <?php actionFields("paymongo_create"); ?>
        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
        <button type="submit" class="button full">Continue to payment</button>
      </form>
      <p class="muted">At checkout, open E-Wallets and choose GCash or Maya.</p>
      <?php endif; ?>
      <?php if ($o["paymongo_session_id"]): ?>
      <form method="post" action="<?= h(url("server.php")) ?>">
        <?php actionFields("paymongo_check"); ?>
        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
        <button type="submit" class="button secondary full">
          Check payment
        </button>
      </form>
      <p class="muted">Already paid? Check its status before trying again.</p>
      <?php endif; ?>
      <?php endif; ?> <?php if ($canAdvance): ?>
      <form
        method="post"
        action="<?= h(url("server.php")) ?>"
        <?php if ($next === "completed"): ?>data-confirm="<?= h($isCash ? "Confirm that " . money($o["total_cents"]) . " cash was received and the order was collected?" : "Confirm that the customer collected this paid order?") ?>"
        <?php endif; ?>
      >
        <?php actionFields("order_status"); ?>
        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
        <input type="hidden" name="status" value="<?= h($next) ?>" />
        <button type="submit" class="button full"><?= $next === "ready" ? "Mark ready for pickup" : ($isCash ? "Confirm cash received &amp; collected" : "Confirm collection") ?></button>
      </form>
      <?php elseif ($u["role"] !== "customer" && $next && !$isCash && $unpaid): ?>
      <p class="muted">
        Online payment must be verified before preparing this order.
      </p>
      <?php endif; ?>
      <?php if ($editable && (count($methods) > 1 || !isset($methods[$o["payment_method"]]))): ?>
      <form
        method="post"
        action="<?= h(url("server.php")) ?>"
        class="stack"
        data-confirm="Change this order's payment method? Any open wallet checkout will be closed first."
      >
        <?php actionFields("order_payment"); ?>
        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
        <input
          type="hidden"
          name="current_payment_method"
          value="<?= h($o["payment_method"]) ?>"
        />
        <label>
          Payment method
          <select name="payment_method" required>
            <?php foreach ($methods as $value => $label): ?>
            <option value="<?= h($value) ?>" <?= $value === $o["payment_method"] ? "selected" : "" ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="button secondary full">
          Change payment
        </button>
      </form>
      <?php endif; ?>
      <?php if ($canCancel): ?>
      <form
        method="post"
        action="<?= h(url("server.php")) ?>"
        data-confirm="Cancel this order? Any unpaid wallet checkout will be closed and the reserved items returned to stock."
      >
        <?php actionFields("order_status"); ?>
        <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
        <input type="hidden" name="status" value="cancelled" />
        <button type="submit" class="button secondary danger full">
          Cancel order
        </button>
      </form>
      <?php endif; ?>
      <?php if ($unpaid && !$cancelled): ?>
      <p class="muted"><?= $o["paypal_order_id"] ? "This order used a discontinued payment method. Contact the store to review its payment before changing or cancelling." : ($o["status"] === "pending" ? "You can change payment or cancel before preparation." : "Contact the store if you need to change this order.") ?></p>
      <?php endif; ?>
    </div>
  </aside>
</div>
