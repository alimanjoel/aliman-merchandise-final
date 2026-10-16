<?php
// Optional services. The normal shop works with these left empty.
// Keep secret keys private.
return [
    "paymongo_secret_key" => "", // sk_test_... for your demonstration
    "paymongo_methods" => ["gcash", "paymaya"], // Only methods enabled on your PayMongo account
    "google_client_id" => "",
    "google_client_secret" => "",
    "resend_key" => "",
    "mail_from" => "Aliman Merchandise <onboarding@resend.dev>",
    "password_breach_check" => true, // Free, no key. Set false for an offline demo.
];
