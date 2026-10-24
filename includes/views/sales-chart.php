<div class="section-heading">
  <div>
    <p class="eyebrow">COMPLETED ORDERS · LAST 30 DAYS</p>
    <h1>Sales report</h1>
  </div>
  <div class="row-actions">
    <a class="button secondary" href="<?= h(url("sales-chart.php?download=csv")) ?>">Download CSV</a>
    <a class="button secondary" href="<?= h(url("chart.php")) ?>">Download chart</a>
  </div>
</div>
<section class="stat-grid">
  <div class="panel stat">
    <p>Completed sales</p>
    <strong><?= money($d["summary"]["revenue_cents"]) ?></strong>
    <small>
      Completed cash and verified online payments; test orders included
    </small>
  </div>
  <div class="panel stat">
    <p>Orders placed</p>
    <strong><?= (int) $d["summary"]["orders"] ?></strong>
    <small>Last 30 days, all statuses</small>
  </div>
  <div class="panel stat">
    <p>Customer accounts</p>
    <strong><?= (int) $d["summary"]["customers"] ?></strong>
    <small>Active accounts</small>
  </div>
</section>
<div class="two-column balanced">
  <section class="panel">
    <h2>Daily sales</h2>
    <?php foreach ($d["sales"] as $s): ?>
    <div class="chart-row">
      <span><?= h($s["day"]) ?></span>
      <div class="bar-track"><div style="width: <?= round(($s["total"] / $max) * 100, 2) ?>%"></div></div>
      <strong><?= money($s["total"]) ?></strong>
    </div>
    <?php endforeach; if (!$d["sales"]): ?>
    <div class="empty">
      <p>Complete your first pickup order to see sales here.</p>
    </div>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>Popular products</h2>
    <?php foreach ($d["top"] as $p): ?>
    <div class="summary-line">
      <span>
        <?= h($p["name"]) ?>
        <small><?= (int) $p["quantity"] ?> sold</small>
      </span>
      <strong><?= money($p["total"]) ?></strong>
    </div>
    <?php endforeach; if (!$d["top"]): ?>
    <p class="muted">Products from completed orders will appear here.</p>
    <?php endif; ?>
  </section>
</div>
