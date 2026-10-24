<p class="eyebrow">PICKUP & CONTACT DETAILS</p>
<h1>Store settings</h1>
<section class="panel narrow">
  <form class="stack" action="<?= h(url("server.php")) ?>" method="post">
    <?php
actionFields("settings"); foreach (["store_name" => ["Store name", 100], "store_address" => ["Pickup address", 250], "store_hours" => ["Opening hours", 100], "store_notice" => ["Store notice", 250],] as $key => [$label, $max]): ?>
    <label>
      <?= h($label) ?>
      <input
        name="<?= h($key) ?>"
        value="<?= h(setting($key)) ?>"
        maxlength="<?= $max ?>"
        required
      />
    </label>
    <?php endforeach; ?>
    <button class="button">Save store details</button>
  </form>
  <p class="muted">
    Pickup dates are Monday–Saturday. The opening-hours text does not create
    appointment slots.
  </p>
</section>
