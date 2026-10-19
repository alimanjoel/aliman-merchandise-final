<section class="shop-masthead" aria-label="Aliman Merchandise collection">
  <div>
    <p class="eyebrow">Aliman Merchandise · Iligan City</p>
    <h1>
      Everyday finds,
      <br />
      close to home.
    </h1>
    <p>Choose your favorites. We’ll get them ready for pickup.</p>
  </div>
  <figure>
    <img
      src="<?= h(url("assets/images/store-signboard.jpg")) ?>"
      width="960"
      height="255"
      alt="Aliman Merchandise store signboard"
    />
    <figcaption>Your neighborhood store, now online.</figcaption>
  </figure>
</section>
<section id="catalog">
  <div class="section-heading">
    <div>
      <p class="eyebrow">IN THE SHOP</p>
      <h2>Browse the collection</h2>
    </div>
    <span class="muted"><?= count($products) ?> products</span>
  </div>
  <div class="category-nav">
    <a class="<?= !$category ? "selected" : "" ?>" href="<?= h(url("index.php")) ?>#catalog">All products</a>
    <?php foreach ($categories as $c): ?>
    <a class="<?= $category === $c["id"] || $category === (int) $c["id"] ? "selected" : "" ?>" href="<?= h(url("index.php?category=" . $c["id"])) ?>#catalog"><?= h($c["name"]) ?></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get" action="<?= h(url("index.php")) ?>#catalog">
    <input type="hidden" name="category" value="<?= $category ?>" />
    <label class="search-field">
      <span class="sr-only">Search products</span>
      <input
        name="q"
        value="<?= h($search) ?>"
        placeholder="Search clothing, beauty, home…"
      />
    </label>
    <label>
      <span class="sr-only">Sort products</span>
      <select name="sort">
        <?php foreach (["name" => "Name: A–Z", "price_asc" => "Price: low to high", "price_desc" => "Price: high to low", "new" => "Newest first",] as $k => $v): ?>
        <option value="<?= h($k) ?>" <?= ($_GET["sort"] ?? "name") === $k ? "selected" : "" ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="button secondary">Search</button>
  </form>
  <?php if (!$products): ?>
  <div class="empty panel">
    <h3>No products found</h3>
    <p>Try another search or browse all categories.</p>
    <a class="button secondary" href="<?= h(url()) ?>">View all products</a>
  </div>
  <?php endif; ?>
  <div class="product-grid">
    <?php foreach ($products as $p): ?>
    <article class="product-card">
      <a class="product-photo" href="<?= h(url("product-page.php?id=" . $p["id"])) ?>">
        <img src="<?= h(productImage($p)) ?>" alt="<?= h($p["name"]) ?>" loading="lazy" />
        <?php if (!$p["stock"]): ?>
        <span class="photo-label">Sold out</span>
        <?php endif; ?>
      </a>
      <div class="product-copy">
        <p class="eyebrow"><?= h($p["category_name"]) ?></p>
        <h3><a href="<?= h(url("product-page.php?id=" . $p["id"])) ?>"><?= h($p["name"]) ?></a></h3>
        <div class="product-bottom">
          <strong><?= money($p["price_cents"]) ?></strong>
          <a
            class="circle-link"
            aria-label="View <?= h($p["name"]) ?>"
            href="<?= h(url("product-page.php?id=" . $p["id"])) ?>"
          >
            ↗
          </a>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>
