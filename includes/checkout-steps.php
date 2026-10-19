<ol class="checkout-steps" aria-label="Order progress">
  <li <?= $checkoutStep === "cart" ? 'aria-current="step"' : "" ?>><span>1</span> Cart</li>
  <li <?= $checkoutStep === "checkout" ? 'aria-current="step"' : "" ?>><span>2</span> Pickup &amp; payment</li>
  <li><span>3</span> Confirmation</li>
</ol>
