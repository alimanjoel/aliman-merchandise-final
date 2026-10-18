<?php
declare(strict_types=1);

function csrfField(): void
{
    echo '<input type="hidden" name="csrf" value="' .
        h($_SESSION["csrf"]) .
        '">';
}

function actionFields(string $action, string $return = ""): void
{
    csrfField();
    echo '<input type="hidden" name="action" value="' . h($action) . '">';
    if ($return !== "") {
        echo '<input type="hidden" name="return_to" value="' .
            h($return) .
            '">';
    }
}

function flash(string $message, string $type = "success"): void
{
    $_SESSION["flash"] = ["message" => $message, "type" => $type];
}

function formResponse(mixed $data, int $status): never
{
    global $formAction, $formReturn;
    if ($status >= 400) {
        if (
            in_array($status, [401, 403, 419], true) &&
            !in_array($formAction, ["login", "register"], true)
        ) {
            throw new HttpError(
                $data["error"] ?? "Request not permitted.",
                $status,
            );
        }
        flash($data["error"] ?? "The request could not be completed.", "error");
        go($formReturn);
    }
    if (in_array($formAction, ["login", "register"], true)) {
        $u = user();
        go(!empty($u["must_change_password"]) ? "account.php" : homeFor($u));
    }
    if ($formAction === "logout") {
        flash("You have been signed out.");
        go("login.php");
    }
    if ($formAction === "checkout") {
        flash("Order placed. Keep your pickup code.");
        $order = orderFor((int) $data["id"], user());
        if (
            $order["payment_method"] === "paymongo" &&
            $order["payment_status"] === "unpaid" &&
            $order["status"] === "pending"
        ) {
            go(
                "payment.php?order=" .
                    (int) $order["id"] .
                    "&provider=" .
                    $order["payment_method"] .
                    "&start=1",
            );
        }
        go("order.php?id=" . (int) $data["id"]);
    }
    if ($formAction === "google") {
        $target = $data["url"] ?? "";
        if (
            parse_url($target, PHP_URL_HOST) !== "accounts.google.com" ||
            parse_url($target, PHP_URL_SCHEME) !== "https"
        ) {
            throw new HttpError("Invalid Google sign-in link.", 502);
        }
        header("Location: " . $target, true, 303);
        exit();
    }
    if (
        in_array(
            $formAction,
            ["paymongo_create", "paymongo_check", "paymongo_cancel"],
            true,
        )
    ) {
        if (!empty($data["url"])) {
            header("Location: " . $data["url"], true, 303);
            exit();
        }
        if (!empty($data["cancelled"])) {
            flash(
                "Unpaid checkout closed and order cancelled. Stock restored.",
            );
            go($formReturn);
        }
        flash(
            !empty($data["paid"])
                ? "Payment verified. See the order receipt for payment mode."
                : $data["message"] ??
                    "Payment is not confirmed yet. Try Check payment again after completing checkout.",
            !empty($data["paid"]) ? "success" : "error",
        );
        go($formReturn);
    }
    if ($formAction === "product_save") {
        flash("Product saved.");
        go("inventory.php");
    }
    if ($formAction === "email_retry") {
        flash(
            !empty($data["sent"])
                ? "Pending emails accepted by the provider. Check its logs for delivery."
                : "Email was not sent. See the reason under Pending email notifications.",
            !empty($data["sent"]) ? "success" : "error",
        );
        go($formReturn);
    }
    if ($formAction === "password_demo") {
        flash($data["message"]);
        go($formReturn);
    }
    if ($formAction === "qr_generate") {
        flash("QR code is ready to download or print.");
        go(
            "shop-qr.php?target=" .
                (($_POST["target"] ?? "") === "shop" ? "shop" : "facebook"),
        );
    }
    $messages = [
        "order_payment" =>
            "Payment method updated. Your items and pickup date are unchanged.",
        "cart_add" => "Item added to your cart.",
        "cart_update" => "Cart updated.",
        "cart_remove" => "Item removed.",
        "product_save" => "Product saved.",
        "product_delete" => "Product removed from the catalog.",
        "category_add" => "Category added.",
        "order_status" =>
            ($_POST["status"] ?? "") === "cancelled"
                ? "Order cancelled. Reserved stock has been restored."
                : "Order updated.",
        "employee_add" => "Employee account created.",
        "user_status" => "Account updated.",
        "settings" => "Store details saved.",
        "password" => "Password changed.",
    ];
    flash($messages[$formAction] ?? "Changes saved.");
    go($formReturn);
}
