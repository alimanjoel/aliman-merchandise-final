<div class="section-heading">
  <div><p class="eyebrow">STORE CONNECTIONS</p><h1>Integrations</h1></div>
  <a class="button secondary" href="#email-queue">Pending emails</a>
</div>
<p class="muted">Payments, sign-in and store tools. “Ready to test” means configuration is present.</p>
<div class="integration-grid">
  <?php foreach ($services as $provider => [$label, $description, $setup, $hint]):
      $configured = integrationReady($provider);
      $event = $events[$provider] ?? null;
      $missing = array_values(array_filter(integrationFields($provider), fn($key) => !config($key)));
      $state = !$configured
          ? ($provider === "pwnedpasswords" ? "Disabled" : "Setup needed")
          : (($event["status"] ?? "") === "error" ? "Needs attention"
              : (($event["status"] ?? "") === "connected" ? "Last request succeeded" : "Ready to test"));
      $tone = $configured && ($event["status"] ?? "") === "connected" ? "active" : "pending";
  ?>
  <section class="panel integration-card">
    <h2><?= h($label) ?></h2>
    <span class="badge <?= h($tone) ?>"><?= h($state) ?></span>
    <p class="spaced"><?= h($description) ?></p>
    <details>
      <summary>Setup and status</summary>
      <?php if ($provider === "resend" && str_contains(strtolower((string) config("mail_from")), "@resend.dev")): ?>
      <p class="error-text">This test sender emails only your Resend account address. Verify a domain and update <code>mail_from</code> to email customers.</p>
      <?php endif; ?>
      <?php if ($missing): ?>
      <p class="muted">
        Add:
        <code><?= h(implode(", ", $missing)) ?></code>
      </p>
      <?php endif; ?>
      <p class="muted"><?= h($hint) ?></p>
      <?php if ($provider === "google"): ?>
      <p class="muted">
        Google callback:
        <br />
        <code><?= h(appUrl("oauth.php")) ?></code>
      </p>
      <?php endif; ?>
      <?php if ($provider === "paymongo" && $configured): ?>
      <p><strong><?= paymongoLive() ? "Live key configured" : "Test key configured — no real money" ?></strong></p>
      <?php endif; ?>
      <?php if ($event): ?>
      <p class="muted">
        Last request: <?= h($event["checked_at"]) ?>
        <br />
        <?= h($event["message"]) ?>
      </p>
      <?php else: ?>
      <p class="muted">No request recorded in this installation yet.</p>
      <?php endif; ?>
    </details>
    <?php if ($provider === "qrserver"): ?>
    <a class="button secondary" href="<?= h(url("shop-qr.php")) ?>">Create shop QR</a>
    <?php elseif ($provider === "pwnedpasswords"): ?>
    <form class="spaced" method="post" action="<?= h(url("server.php")) ?>">
      <?php actionFields("password_demo"); ?>
      <button class="button secondary" <?= !$configured ? "disabled" : "" ?>>Run demo check</button>
    </form>
    <?php elseif ($provider === "quickchart"): ?>
    <a class="button secondary" href="<?= h(url("chart.php")) ?>">Download sales chart</a>
    <?php else: ?>
    <a href="<?= h($setup) ?>" target="_blank" rel="noopener noreferrer">
      Open provider setup ↗
    </a>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>
<section id="email-queue" class="panel spaced">
  <h2>Pending email notifications</h2>
  <p class="muted">Fix the reported issue before retrying. For timeouts, check provider logs first. API acceptance does not guarantee inbox delivery.</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Event</th>
          <th>Attempts</th>
          <th>Reason / next step</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($mail as $message): ?>
        <tr>
          <td><a href="<?= h(url("order.php?id=" . $message["order_id"])) ?>"><?= h($message["reference"]) ?></a></td>
          <td><?= h($message["event"]) ?></td>
          <td><?= (int) $message["attempts"] ?></td>
          <td><?= h($message["last_error"]) ?></td>
          <td>
            <form action="<?= h(url("server.php")) ?>" method="post">
              <?php actionFields("email_retry"); ?>
              <input type="hidden" name="order_id" value="<?= (int) $message["order_id"] ?>" />
              <button class="button secondary small">Retry email</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$mail): ?>
        <tr><td colspan="5" class="empty">No pending emails.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
