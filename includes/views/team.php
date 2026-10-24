<p class="eyebrow">PEOPLE BEHIND THE STORE</p>
<h1>Team access</h1>
<div class="two-column">
  <section class="panel table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($team as $c): ?>
        <tr>
          <td>
            <strong><?= h($c["name"]) ?></strong>
            <small><?= h($c["email"]) ?></small>
          </td>
          <td><?= h(ucfirst($c["role"])) ?></td>
          <td><?= badge($c["active"] ? "active" : "disabled") ?></td>
          <td>
            <?php if ($c["role"] === "employee"): ?>
            <form action="<?= h(url("server.php")) ?>" method="post">
              <?php actionFields("user_status", "team.php"); ?>
              <input type="hidden" name="id" value="<?= (int) $c["id"] ?>" />
              <input type="hidden" name="active" value="<?= $c["active"] ? 0 : 1 ?>" />
              <button class="button secondary small"><?= $c["active"] ? "Disable" : "Enable" ?></button>
            </form>
            <?php else: ?>Owner<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <section class="panel">
    <h2>Add an employee</h2>
    <p class="muted">
      Employees can view inventory and prepare orders. Store administration
      stays with the owner.
    </p>
    <form action="<?= h(url("server.php")) ?>" method="post" class="stack">
      <?php actionFields("employee_add"); ?>
      <label>
        Full name
        <input name="name" required maxlength="100" />
      </label>
      <label>
        Email
        <input name="email" type="email" required maxlength="150" />
      </label>
      <label>
        Initial password
        <input
          name="password"
          type="password"
          minlength="12"
          maxlength="72"
          required
          autocomplete="new-password"
        />
      </label>
      <button class="button">Create employee</button>
    </form>
  </section>
</div>
