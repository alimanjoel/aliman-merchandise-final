// Only payment-status checks auto-submit. Starting or reopening checkout is
// an explicit button click, and every payment form also works without JS.
document.querySelectorAll("form[data-payment-submit]").forEach((form) => {
  if (typeof form.requestSubmit === "function") form.requestSubmit();
});

// Orders and receipts are already saved before optional email delivery starts.
// Failure leaves the durable notification queued for the owner's retry tool.
document.querySelectorAll("[data-order-email]").forEach((notice) => {
  if (typeof fetch !== "function") return;
  fetch(notice.dataset.url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "X-CSRF-Token": notice.dataset.csrf,
      "Content-Type": "application/json",
    },
    body: "{}",
  }).catch(() => {});
});
