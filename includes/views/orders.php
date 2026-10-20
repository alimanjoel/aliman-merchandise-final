<div class="section-heading">
  <div>
    <p class="eyebrow"><?= $u["role"] === "customer" ? "YOUR PICKUPS" : "ORDER MANAGEMENT" ?></p>
    <h1><?= $u["role"] === "customer" ? "My orders" : "Store orders" ?></h1>
  </div>
  <span class="muted">Latest <?= count($orders) ?> orders</span>
</div>
<form class="filter-bar" method="get">
  <input
    name="q"
    value="<?= h($q) ?>"
    placeholder="Search reference, customer or pickup code"
    aria-label="Search orders"
  />
  <select name="status" aria-label="Order status">
    <option value="">All statuses</option>
    <?php foreach (["pending", "ready", "completed", "cancelled"] as $s): ?>
    <option value="<?= h($s) ?>" <?= $status === $s ? "selected" : "" ?>><?= h(ucfirst($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="button secondary">Filter</button>
</form>
<div class="panel table-wrap">
  <table>
    <thead>
      <tr>
        <th>Order</th>
        <?php if ($u["role"] !== "customer"): ?>
        <th>Customer</th>
        <?php endif; ?>
        <th>Pickup date</th>
        <th>Total</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td>
          <strong><?= h($o["reference"]) ?></strong>
          <small><?= h($o["created_at"]) ?></small>
        </td>
        <?php if ($u["role"] !== "customer"): ?>
        <td><?= h($o["customer_name"]) ?></td>
        <?php endif; ?>
        <td><?= h($o["pickup_date"]) ?></td>
        <td><?= money($o["total_cents"]) ?></td>
        <td><?= badge($o["status"]) ?></td>
        <td>
          <a class="button secondary small" href="<?= h(url("order.php?id=" . $o["id"])) ?>">View order</a>
        </td>
      </tr>
      <?php endforeach; if (!$orders): ?>
      <tr><td colspan="6" class="empty">No orders found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
