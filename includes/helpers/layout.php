<?php
declare(strict_types=1);

function pageStart(string $title): void
{
    $pageTitle = $title;
    $currentUser = user(false);
    require ROOT . "/includes/header.php";
}

function pageEnd(?int $notificationOrderId = null): void
{
    if ($notificationOrderId && integrationReady(emailProvider()) && hasPendingOrderMail($notificationOrderId)) {
        echo '<span hidden data-order-email data-url="' . h(url('api.php?route=orders/' . $notificationOrderId . '/notifications')) . '" data-csrf="' . h($_SESSION['csrf']) . '"></span>';
    }
    require ROOT . "/includes/footer.php";
}
