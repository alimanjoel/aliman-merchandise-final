<section class="panel narrow">
  <p class="eyebrow">ORDER <?= h($o["reference"]) ?></p>
  <h1><?= $cancel ? "Continue when you’re ready" : "Complete your payment" ?></h1>
  <div class="summary-total">
    <span>Total</span>
    <strong><?= money($o["total_cents"]) ?></strong>
  </div>
  <p><?= h(paymentLabel($o)) ?></p>
  <p><?= $cancel ? "Your order is still saved. You can reopen checkout below." : "On the next page, open E-Wallets, choose GCash or Maya, then click Continue." ?></p>
  <form action="<?= h(url("server.php")) ?>" method="post" class="stack">
    <?php actionFields("paymongo_create"); ?>
    <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
    <button type="submit" class="button full">Continue to payment</button>
  </form>
  <?php if ($hasCheckout): ?>
  <form action="<?= h(url("server.php")) ?>" method="post" class="spaced" <?= $verifyReturn ? "data-payment-submit" : "" ?>>
    <?php actionFields("paymongo_check"); ?>
    <input type="hidden" name="id" value="<?= (int) $o["id"] ?>" />
    <button type="submit" class="button secondary full">
      I already paid — check payment
    </button>
  </form>
  <p class="muted"><?= $verifyReturn ? "Checking your payment. If this page stays open, use the check button above." : "Already paid? Check its status before trying again." ?></p>
  <?php endif; ?>
  <p><a href="<?= h(url("order.php?id=" . $o["id"])) ?>">Back to order</a></p>
</section>
