<?php
declare(strict_types=1);

// SHA-1 is only used for the provider's range lookup. Stored passwords still use password_hash.
function breachedPasswordCount(string $password): ?int
{
    $hash = strtoupper(sha1($password));
    try {
        $result = remote(
            "pwnedpasswords",
            "https://api.pwnedpasswords.com/range/" . substr($hash, 0, 5),
            "GET",
            null,
            ["Add-Padding: true"],
            false,
        );
        $lines = preg_split('/\r?\n/', trim($result["body"]));
        $count = 0;
        if (!$lines || count($lines) < 1) {
            throw new RuntimeException();
        }
        foreach ($lines as $line) {
            if (!preg_match('/^([A-F0-9]{35}):([0-9]+)$/', $line, $m)) {
                throw new RuntimeException();
            }
            if (hash_equals(substr($hash, 5), $m[1])) {
                $count = (int) $m[2];
            }
        }
        apiEvent(
            "pwnedpasswords",
            "connected",
            "Password range lookup succeeded. No password was sent or logged.",
        );
        return $count;
    } catch (Throwable) {
        // A temporary API outage must not lock users out. Local length checks still apply.
        apiEvent(
            "pwnedpasswords",
            "error",
            "Breach lookup unavailable. Local password rules remain active; try the demo check again later.",
        );
        return null;
    }
}

function checkNewPassword(string $password): void
{
    if (!integrationReady("pwnedpasswords")) {
        return;
    }
    $count = breachedPasswordCount($password);
    if ($count !== null && $count > 0) {
        throw new HttpError(
            "This password appears in known data breaches. Choose a different, unique password.",
        );
    }
}
