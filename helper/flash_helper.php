<?php

declare(strict_types=1);

function setFlash(
    string $type,
    string $message
): void {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];

    unset($_SESSION['flash_messages']);

    return $messages;
}