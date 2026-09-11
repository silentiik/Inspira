<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Emits a hidden input for use inside <form> tags. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Call at the top of every POST handler. Exits with 400 on mismatch. */
function csrf_check(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(400);
        exit('Neplatný nebo vypršelý formulář. Načtěte prosím stránku znovu a zkuste to znovu.');
    }
}
