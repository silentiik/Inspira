<?php
declare(strict_types=1);

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Reads and clears the flash message (read-once, survives one redirect). */
function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function render_flash(): string
{
    $flash = flash_get();
    if (!$flash) {
        return '';
    }
    $class = $flash['type'] === 'error' ? 'alert--error' : 'alert--success';
    $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    return '<div class="alert ' . $class . ' is-visible">' . $message . '</div>';
}
