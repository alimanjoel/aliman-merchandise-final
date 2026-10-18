<section class="store-hero" aria-label="Welcome to Aliman Merchandise">
  <div class="store-intro">
    <div>
      <p class="eyebrow">Your neighborhood store · Iligan City</p>
      <h1>For you.<br><em>For your home.</em></h1>
    </div>
    <div class="store-welcome">
      <p>Clothing, beauty &amp; everyday essentials. Find them here, pick them up in store.</p>
      <a class="button" href="<?= h($heroHref) ?>"><?= h($heroLabel) ?><?= icon("arrow") ?></a>
    </div>
  </div>
  <figure class="store-photo">
    <img src="<?= h(url("assets/images/store-signboard.jpg")) ?>" width="960" height="255" alt="The original Aliman Merchandise direct-selling signboard above the store" fetchpriority="high">
    <figcaption>
      <strong>Aliman Merchandise <span> / Direct selling</span></strong>
      <a href="https://www.facebook.com/AlimanMerchandise" target="_blank" rel="noopener noreferrer" aria-label="Visit Aliman Merchandise on Facebook (opens in a new tab)">Visit our Facebook <?= icon("external") ?></a>
    </figcaption>
  </figure>
  <div class="store-details">
    <span><?= icon("pin") ?><?= h(setting("store_address")) ?></span>
    <span><?= icon("clock") ?><?= h(setting("store_hours")) ?></span>
    <span><?= icon("bag") ?>Order online. Collect in store.</span>
  </div>
</section>
