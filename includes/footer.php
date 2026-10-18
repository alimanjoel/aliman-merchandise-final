</main>
</div>
<?php if (in_array(currentPage(), ["login.php", "signup.php"], true)): ?>
<footer class="entry-footer">
  <span>© <?= date("Y") ?> Aliman Merchandise</span>
  <span>Clothing · Beauty · Home</span>
</footer>
<?php else: ?>
<footer class="site-footer">
  <div>
    <strong class="footer-brand">aliman merchandise</strong>
    <p>Everyday finds, close to home.</p>
    <a class="footer-link" href="https://www.facebook.com/AlimanMerchandise" target="_blank" rel="noopener noreferrer" aria-label="Visit Aliman Merchandise on Facebook (opens in a new tab)">Find us on Facebook <?= icon("external") ?></a>
  </div>
  <div>
    <strong class="footer-label">Visit & collect</strong>
    <p><?= h(setting("store_address", "Iligan City, Philippines")) ?><br><?= h(setting("store_hours", "Monday–Saturday · 9 AM–6 PM")) ?></p>
  </div>
  <p class="footer-bottom">© <?= date("Y") ?> Aliman Merchandise</p>
</footer>
<?php endif; ?>
</body>
</html>
