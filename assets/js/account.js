// The dialog is in the HTML already. CSS :target also opens it without JS.
const accountDialog = document.querySelector("[data-auth-dialog]");
if (accountDialog && typeof accountDialog.showModal === "function") {
  const clearAccountState = () => {
    accountDialog.hidden = true;
    accountDialog.dataset.open = "false";
    document.body.classList.remove("auth-modal-open");
    if (location.hash === "#account") {
      try {
        history.replaceState(null, "", location.pathname + location.search);
      } catch {
        location.hash = "";
      }
    }
  };
  const closeAccount = () => {
    if (accountDialog.open) accountDialog.close();
    // CSS :target can show a dialog whose native open flag is already false.
    // Clear it immediately; close() does not fire an event in that state.
    clearAccountState();
  };
  const openAccount = () => {
    accountDialog.hidden = false;
    if (accountDialog.open) return;
    accountDialog.showModal();
    document.body.classList.add("auth-modal-open");
    try {
      history.replaceState(null, "", "#account");
    } catch {
      location.hash = "#account";
    }
    const target = accountDialog.querySelector(".notice")
      ? accountDialog.querySelector("h2")
      : accountDialog.querySelector("input:not([type=hidden])");
    if (target.tagName === "H2") target.tabIndex = -1;
    target.focus({ preventScroll: true });
  };
  document.querySelectorAll("a[href]").forEach((link) => {
    const destination = new URL(link.href, location.href);
    if (
      destination.origin === location.origin &&
      destination.pathname === location.pathname &&
      destination.hash === "#account"
    ) {
      link.addEventListener("click", (event) => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)
          return;
        event.preventDefault();
        openAccount();
      });
    }
  });
  accountDialog
    .querySelector("[data-auth-close]")
    .addEventListener("click", (event) => {
      closeAccount();
      event.preventDefault();
    });
  accountDialog.addEventListener("cancel", (event) => {
    event.preventDefault();
    closeAccount();
  });
  accountDialog.addEventListener("close", () => {
    // A delayed close event must not hide a newly reopened dialog.
    if (!accountDialog.open) clearAccountState();
  });
  accountDialog.addEventListener("click", (event) => {
    if (event.target !== accountDialog) return;
    const bounds = accountDialog.getBoundingClientRect();
    if (
      event.clientX < bounds.left ||
      event.clientX > bounds.right ||
      event.clientY < bounds.top ||
      event.clientY > bounds.bottom
    )
      closeAccount();
  });
  window.addEventListener("hashchange", () => {
    if (location.hash === "#account") openAccount();
    else closeAccount();
  });
  if (accountDialog.dataset.open === "true" || location.hash === "#account")
    openAccount();
}
