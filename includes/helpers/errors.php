<?php
declare(strict_types=1);

set_exception_handler(function (Throwable $e): void {
    $code = $e instanceof HttpError ? $e->status : 503;
    http_response_code($code);
    if (!($e instanceof HttpError)) {
        error_log((string) $e);
    }
    $message =
        $e instanceof HttpError
            ? $e->getMessage()
            : "Check that MySQL is running, import database/aliman_merchandise.sql, and check database.php.";
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Aliman Merchandise</title><link rel="stylesheet" href="' .
        h(url("assets/css/style.css")) .
        '"><main class="error-page panel"><p class="eyebrow">ALIMAN MERCHANDISE</p><h1>We could not open this page.</h1><p>' .
        h($message) .
        '</p><a class="button" href="' .
        h(url("index.php")) .
        '">Return to store</a></main></html>';
});
