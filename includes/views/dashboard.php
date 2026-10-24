<div class="section-heading">
  <div>
    <p class="eyebrow">STORE OVERVIEW</p>
    <h1>Hello, <?= h(explode(" ", $u["name"])[0]) ?>.</h1>
    <p class="muted">Orders, stock and sales, all in one place.</p>
  </div>
  <span class="date-label"><?= date("D, M j, Y") ?></span>
</div>
<section class="stat-grid">
  <?php foreach ($stats as [$label, $value, $hint]): ?>
  <article class="panel stat">
    <p><?= h($label) ?></p>
    <strong><?= h($value) ?></strong>
    <small><?= h($hint) ?></small>
  </article>
  <?php endforeach; ?>
</section>
<div class="two-column balanced">
  <section class="panel">
    <div class="section-heading compact">
      <h2>Recent orders</h2>
      <a href="<?= h(url("orders.php")) ?>">View all ↗</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Reference</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($d["recent"] as $o): ?>
          <tr>
            <td><a href="<?= h(url("order.php?id=" . $o["id"])) ?>"><?= h($o["reference"]) ?></a></td>
            <td><?= h($o["customer_name"]) ?></td>
            <td><?= money($o["total_cents"]) ?></td>
            <td><?= badge($o["status"]) ?></td>
          </tr>
          <?php endforeach; if (!$d["recent"]): ?>
          <tr>
            <td colspan="4" class="empty">
              Your first orders will appear here.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="panel">
    <div class="section-heading compact">
      <h2>Stock watch</h2>
      <a href="<?= h(url("inventory.php")) ?>">Inventory ↗</a>
    </div>
    <?php foreach ($d["low_stock"] as $p): ?>
    <div class="summary-line">
      <span><?= h($p["name"]) ?></span>
      <span class="badge pending"><?= (int) $p["stock"] ?> left</span>
    </div>
    <?php endforeach; if (!$d["low_stock"]): ?>
    <p class="muted">No active products are below their stock threshold.</p>
    <?php endif; ?>
    <p class="muted">
      <?= (int) $d["summary"]["hidden"] ?> products are hidden from the storefront. Review their prices
      and availability in Inventory.
    </p>
  </section>
</div>
<section class="panel spaced">
  <h2>Recent activity</h2>
  <?php foreach ($d["activity"] as $a): ?>
  <div class="activity-row">
    <span>
      <strong><?= h($a["action"]) ?></strong>
      · <?= h($a["detail"]) ?>
    </span>
    <small><?= h($a["name"]) ?> · <?= h($a["created_at"]) ?></small>
  </div>
  <?php endforeach; if (!$d["activity"]): ?>
  <p class="muted">Product and order changes will be recorded here.</p>
  <?php endif; ?>
</section>
