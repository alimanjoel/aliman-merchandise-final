<div class="section-heading">
  <div>
    <p class="eyebrow">PRODUCTS & STOCK</p>
    <h1>Inventory</h1>
  </div>
  <?php if ($u["role"] === "owner"): ?>
  <a class="button" href="<?= h(url("product-edit.php")) ?>">+ Add product</a>
  <?php endif; ?>
</div>
<form class="filter-bar" method="get">
  <input
    name="q"
    value="<?= h($q) ?>"
    placeholder="Search product or SKU"
    aria-label="Search inventory"
  />
  <button class="button secondary">Search</button>
</form>
<div class="panel table-wrap">
  <table>
    <thead>
      <tr>
        <th>Product</th>
        <th>Category</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Visibility</th>
        <?php if ($u["role"] === "owner"): ?>
        <th>Actions</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $p): ?>
      <tr>
        <td>
          <div class="table-product">
            <img src="<?= h(productImage($p)) ?>" alt="" loading="lazy" />
            <div>
              <strong><?= h($p["name"]) ?></strong>
              <small><?= h($p["sku"]) ?></small>
            </div>
          </div>
        </td>
        <td><?= h($p["category_name"]) ?></td>
        <td>
          <?= money($p["price_cents"]); if ($p["price_cents"] < 1000 && $p["category_id"] == 6): ?>
          <small class="error-text">Review price</small>
          <?php endif; ?>
        </td>
        <td><span class="<?= $p["stock"] <= $p["low_stock"] ? "error-text" : "" ?>"><?= (int) $p["stock"] ?></span></td>
        <td><?= badge($p["active"] ? "active" : "hidden") ?></td>
        <?php if ($u["role"] === "owner"): ?>
        <td>
          <div class="row-actions">
            <a class="button secondary small" href="<?= h(url("product-edit.php?id=" . $p["id"])) ?>">Edit</a>
            <form
              action="<?= h(url("server.php")) ?>"
              method="post"
              data-confirm="Remove this product from the catalog? Existing receipts will be kept."
            >
              <?php actionFields("product_delete"); ?>
              <input type="hidden" name="id" value="<?= (int) $p["id"] ?>" />
              <button class="text-button danger">Remove</button>
            </form>
          </div>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; if (!$items): ?>
      <tr><td colspan="6" class="empty">No matching products.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($u["role"] === "owner"): ?>
<section class="panel spaced">
  <h2>Add a category</h2>
  <form class="filter-bar" method="post" action="<?= h(url("server.php")) ?>">
    <?php actionFields("category_add"); ?>
    <input
      name="name"
      maxlength="60"
      required
      placeholder="Category name"
      aria-label="Category name"
    />
    <button class="button secondary">Add category</button>
  </form>
</section>
<?php endif; ?>
