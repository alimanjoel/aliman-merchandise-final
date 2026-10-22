<div class="no-print">
  <p class="eyebrow">ON YOUR COUNTER</p>
  <h1>Scan. Visit. Shop.</h1>
  <p class="muted">
    Print a QR card for your counter or packaging. Customers scan it to open
    your page.
  </p>
  <div class="qr-options">
    <a class="button <?= $target === "facebook" ? "" : "secondary" ?>" href="<?= h(url("shop-qr.php?target=facebook")) ?>">Facebook page</a>
    <a class="button <?= $target === "shop" ? "" : "secondary" ?>" href="<?= h(url("shop-qr.php?target=shop")) ?>">Online shop</a>
    <a href="<?= h(url("integrations.php")) ?>">Back to integrations</a>
  </div>
  <?php if ($error): ?>
  <div class="notice error"><?= h($error) ?></div>
  <?php endif; ?>
</div>
<?php if (!$error): ?>
<section class="qr-poster panel">
  <img class="qr-signboard" src="<?= h(url("assets/images/store-signboard.jpg")) ?>" alt="Aliman Merchandise" />
  <p class="eyebrow">YOUR NEIGHBORHOOD FINDS</p>
  <h2><?= $target === "facebook" ? "Say hello on Facebook." : "Your next find starts here." ?></h2>
  <?php if ($ready): ?>
  <img
    class="shop-qr-image"
    src="<?= h(url("qr-image.php?target=" . $target)) ?>"
    width="512"
    height="512"
    alt="QR code linking to <?= h($destination) ?>"
  />
  <p>Open your camera. Scan to visit.</p>
  <p class="qr-destination"><?= h($destination) ?></p>
  <?php else: ?>
  <div class="qr-placeholder no-print">Your QR code will appear here.</div>
  <p class="qr-destination"><?= h($destination) ?></p>
  <?php endif; ?>
</section>
<div class="qr-actions no-print">
  <?php if ($ready): ?>
  <button class="button" type="button" data-print>Print QR card</button>
  <a class="button secondary" href="<?= h(url("qr-image.php?target=" . $target . "&download=1")) ?>">Download PNG</a>
  <p class="muted">
    Saved locally. You can print this code even when the QR service is
    unavailable.
  </p>
  <?php else: ?>
  <form action="<?= h(url("server.php")) ?>" method="post">
    <?php actionFields("qr_generate", "shop-qr.php?target=" . $target); ?>
    <input type="hidden" name="target" value="<?= h($target) ?>" />
    <button class="button">Generate free QR code</button>
  </form>
  <p class="muted">
    No API key needed. Only the page link is sent to QRServer.
  </p>
  <?php endif; ?>
</div>
<?php endif; ?>
