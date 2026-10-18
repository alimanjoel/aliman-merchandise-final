<p class="eyebrow">ACCOUNT DETAILS</p>
<h1>My account</h1>
<?php if (!empty($u["must_change_password"])): ?>
<div class="notice error">
  Change the starter password below before using your owner workspace.
</div>
<?php endif; ?>
<div class="two-column balanced">
  <section class="panel">
    <h2><?= h($u["name"]) ?></h2>
    <p><?= h($u["email"]) ?></p>
    <?= badge($u["role"]) ?>
<?php if (integrationReady("google") && empty($u["must_change_password"])): ?>
    <form class="spaced" method="post" action="<?= h(url("server.php")) ?>">
      <?php actionFields("google", "account.php"); ?>
      <button class="button secondary">Connect my Google account</button>
      <p class="muted">Use the Google account with the same email address.</p>
    </form>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>Change password</h2>
    <form class="stack" method="post" action="<?= h(url("server.php")) ?>">
      <?php actionFields("password"); ?>
      <label>
        Current password
        <input
          name="current"
          type="password"
          maxlength="72"
          required
          autocomplete="current-password"
        />
      </label>
      <label>
        New password
        <input
          name="password"
          type="password"
          minlength="12"
          maxlength="72"
          required
          autocomplete="new-password"
        />
      </label>
      <small class="muted">Choose a unique password of 12–72 characters.</small>
      <button class="button">Change password</button>
    </form>
  </section>
</div>
