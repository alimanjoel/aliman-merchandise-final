<?php
declare(strict_types=1);
// Link an authenticated, verified Google profile to the correct local account.
function googleAccountId(array $profile, array $flow): int
{
    if (
        empty($profile["sub"]) ||
        ($profile["email_verified"] ?? false) !== true ||
        !filter_var($profile["email"] ?? "", FILTER_VALIDATE_EMAIL)
    ) {
        throw new HttpError("Google did not provide a verified email.");
    }
    return atomic(function () use ($profile, $flow) {
        $found = one("SELECT * FROM users WHERE google_sub=?", [
            $profile["sub"],
        ]);
        if ($flow["link_id"]) {
            $current = user();
            if (
                (int) $current["id"] !== (int) $flow["link_id"] ||
                strtolower($current["email"]) !==
                    strtolower($profile["email"]) ||
                ($found && (int) $found["id"] !== (int) $current["id"])
            ) {
                throw new HttpError(
                    "Use the Google account with the same email as your signed-in account.",
                );
            }
            query("UPDATE users SET google_sub=? WHERE id=?", [
                $profile["sub"],
                $current["id"],
            ]);
            return (int) $current["id"];
        }
        if ($found) {
            if (!$found["active"]) {
                throw new HttpError("This account is disabled.");
            }
            return (int) $found["id"];
        }
        if (
            one("SELECT id FROM users WHERE email=?", [
                strtolower($profile["email"]),
            ])
        ) {
            throw new HttpError(
                "This email already has an account. Sign in with your password, then connect Google from Account.",
            );
        }
        query(
            "INSERT INTO users (name,email,password_hash,google_sub,role,created_at) VALUES (?,?,?,?,?,?)",
            [
                substr($profile["name"] ?? "Customer", 0, 100),
                strtolower($profile["email"]),
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                $profile["sub"],
                "customer",
                now(),
            ],
        );
        return (int) db()->lastInsertId();
    });
}
