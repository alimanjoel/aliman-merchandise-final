<?php
// Keep validation messages beside the form, including after a failed POST.
$authNotice = null;
if (($_SESSION["flash"]["type"] ?? "") === "error") {
    $authNotice = $_SESSION["flash"]["message"];
    unset($_SESSION["flash"]);
}
pageStart($isSignup ? "Create account" : "Welcome");
$heroHref = $isSignup ? url("login.php#account") : "#account";
$heroLabel = "Sign in to shop";
require ROOT . "/includes/store-hero.php";
?>

<dialog id="account" class="auth-dialog" aria-labelledby="auth-title" data-auth-dialog data-open="<?= $isSignup ||
$authNotice !== null
    ? "true"
    : "false" ?>">
<section class="entry-form" aria-labelledby="auth-title" data-auth-form>
    <div class="entry-brand-row">
        <div class="entry-brand"><img src="<?= h(
            url("assets/images/mark.svg"),
        ) ?>" width="42" height="42" alt=""><span>aliman<small>MERCHANDISE</small></span></div>
        <a href="<?= h(
            url("login.php"),
        ) ?>" class="entry-close" data-auth-close aria-label="Close account form"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></a>
    </div>
    <h2 id="auth-title"><?= $isSignup ? "Create account" : "Sign in" ?></h2>
    <?php if ($authNotice !== null): ?>
        <div class="notice error" role="alert"><?= h($authNotice) ?></div>
    <?php endif; ?>
    <form action="<?= h(url("server.php")) ?>" method="post" class="stack">
        <?php actionFields($isSignup ? "register" : "login"); ?>
        <?php if ($isSignup): ?>
            <label for="auth-name">Your name</label>
            <input id="auth-name" name="name" required maxlength="100" autocomplete="name">
        <?php endif; ?>
        <label for="auth-email">Email address</label>
        <input id="auth-email" type="email" name="email" required maxlength="150" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false">
        <label for="auth-password">Password</label>
        <div class="password-field">
            <input id="auth-password" type="password" name="password" required <?= $isSignup
                ? 'minlength="12" aria-describedby="password-hint"'
                : "" ?> maxlength="72" autocomplete="<?= $isSignup
     ? "new-password"
     : "current-password" ?>">
            <button type="button" class="password-toggle" data-password-toggle aria-controls="auth-password" aria-label="Show password" aria-pressed="false" hidden>Show</button>
        </div>
        <?php if ($isSignup): ?>
            <small id="password-hint" class="muted">Use 12–72 characters.</small>
        <?php endif; ?>
        <button type="submit" class="button full auth-submit"><?= $isSignup
            ? "Create account"
            : "Sign in" ?><span aria-hidden="true">↗</span></button>
    </form>
    <?php if (integrationReady("google")): ?>
        <div class="entry-divider"><span>or</span></div>
        <form action="<?= h(url("server.php")) ?>" method="post">
            <?php actionFields("google"); ?>
            <button class="button secondary full">Continue with Google</button>
        </form>
    <?php endif; ?>
    <p class="entry-switch"><?= $isSignup
        ? "Already have an account?"
        : "New here?" ?> <a href="<?= h(
     url($isSignup ? "login.php#account" : "signup.php#account"),
 ) ?>"><?= $isSignup ? "Sign in" : "Create an account" ?></a></p>
</section>
</dialog>
<?php pageEnd(); ?>
