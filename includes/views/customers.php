<p class="eyebrow">YOUR COMMUNITY</p>
<h1>Customers</h1>
<div class="panel table-wrap">
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Orders</th>
        <th>Completed purchases</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td>
          <strong><?= h($c["name"]) ?></strong>
          <small>Joined <?= h(substr($c["created_at"], 0, 10)) ?></small>
        </td>
        <td><?= h($c["email"]) ?></td>
        <td><?= (int) $c["order_count"] ?></td>
        <td><?= money($c["spent"]) ?></td>
        <td><?= badge($c["active"] ? "active" : "disabled") ?></td>
        <td>
          <form
            action="<?= h(url("server.php")) ?>"
            method="post"
            data-confirm="Change access for this customer account?"
          >
            <?php actionFields("user_status", "customers.php"); ?>
            <input type="hidden" name="id" value="<?= (int) $c["id"] ?>" />
            <input type="hidden" name="active" value="<?= $c["active"] ? 0 : 1 ?>" />
            <button class="button secondary small"><?= $c["active"] ? "Disable" : "Enable" ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; if (!$customers): ?>
      <tr>
        <td colspan="6" class="empty">
          Customer accounts will appear after registration.
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
