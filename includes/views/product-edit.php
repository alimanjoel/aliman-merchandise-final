<a class="back-link" href="<?= h(url("inventory.php")) ?>">← Inventory</a>
<h1><?= $id ? "Edit product" : "Add product" ?></h1>
<form class="panel stack" action="<?= h(url("server.php")) ?>" method="post">
  <?php actionFields("product_save", "product-edit.php" . ($id ? "?id=" . $id : "")); ?>
  <input type="hidden" name="id" value="<?= $id ?>" />
  <input type="hidden" name="version" value="<?= (int) $p["version"] ?>" />
  <div class="form-grid">
    <label>
      Product name
      <input name="name" value="<?= h($p["name"]) ?>" maxlength="150" required />
    </label>
    <label>
      SKU
      <input
        name="sku"
        value="<?= h($p["sku"]) ?>"
        maxlength="30"
        pattern="[A-Za-z0-9_-]+"
        required
        placeholder="AM-0075"
      />
    </label>
    <label>
      Category
      <select name="category_id">
        <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c["id"] ?>" <?= (int) $c["id"] === (int) $p["category_id"] ? "selected" : "" ?>><?= h($c["name"]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Brand
      <span class="muted">(optional)</span>
      <input name="brand" maxlength="80" value="<?= h($p["brand"]) ?>" />
    </label>
    <label>
      Price (PHP)
      <input
        type="number"
        min="0.01"
        step="0.01"
        max="9999999.99"
        name="price"
        value="<?= number_format($p["price_cents"] / 100, 2, ".", "") ?>"
        required
      />
    </label>
    <label>
      Stock quantity
      <input
        type="number"
        name="stock"
        min="0"
        max="100000"
        value="<?= (int) $p["stock"] ?>"
        required
      />
    </label>
    <label>
      Low-stock alert at
      <input
        type="number"
        name="low_stock"
        min="0"
        max="100000"
        value="<?= (int) $p["low_stock"] ?>"
        required
      />
    </label>
    <label>
      Visibility
      <select name="active">
        <option value="1" <?= $p["active"] ? "selected" : "" ?>>Visible in shop</option>
        <option value="0" <?= !$p["active"] ? "selected" : "" ?>>Hidden for review</option>
      </select>
    </label>
  </div>
  <label>
    Description
    <textarea name="description" maxlength="2000" rows="4">
<?= h($p["description"]) ?></textarea>
  </label>
  <div class="image-edit">
    <?php if ($p["image_url"]): ?>
    <img src="<?= h(productImage($p)) ?>" alt="Current product photo" />
    <?php endif; ?>
  </div>
  <div class="row-actions">
    <button class="button">Save product</button>
    <a class="button secondary" href="<?= h(url("inventory.php")) ?>">Back to inventory</a>
  </div>
</form>
