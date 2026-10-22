<?php
require __DIR__ . "/includes/functions.php";
signedIn(["owner"]);
$events = [];
foreach (all("SELECT * FROM api_events") as $event) {
    $events[$event["provider"]] = $event;
}
$mail = all(
    "SELECT e.*,o.reference FROM email_outbox e JOIN orders o ON o.id=e.order_id WHERE e.status='pending' ORDER BY e.id DESC LIMIT 25",
);
$services = [
    "paymongo" => [
        "GCash / Maya",
        "PayMongo hosted checkout for local wallet payments.",
        "https://dashboard.paymongo.com/",
        "Test with a customer account. Returning from checkout verifies payment automatically; the receipt also has a Check payment button.",
    ],
    "google" => [
        "Google Sign-In",
        "Customers can sign in with Google or link their existing account.",
        "https://console.cloud.google.com/apis/credentials",
        "Register the exact callback shown below. Then use Continue with Google on Sign in.",
    ],
    "resend" => [
        "Resend email",
        "Send confirmations, pickup updates and payment notices.",
        "https://resend.com/api-keys",
        "Use a verified sender in mail_from. Place an order with a real permitted email address, then review pending notifications below.",
    ],
    "pwnedpasswords" => [
        "Password protection",
        "Reject known breached passwords on registration and password changes.",
        "https://haveibeenpwned.com/Passwords",
        "Free, no key. Only the first five characters of a password hash leave the server. Local password rules stay active during outages.",
    ],
    "qrserver" => [
        "Shop QR code",
        "Create a printable counter card linking to your shop or Facebook.",
        "https://goqr.me/api/",
        "Free, no key. Facebook works for a localhost demo. The shop code needs a public HTTPS app_url.",
    ],
    "quickchart" => [
        "Sales chart downloads",
        "Export a chart of completed sales as a PNG image.",
        "https://quickchart.io/",
        "No account key is required. Use Download sales chart to make an actual request.",
    ],
];
pageStart("Integrations");
require ROOT . "/includes/views/integrations.php";
pageEnd();
