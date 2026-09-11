<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/config.php';

/**
 * Creates a new account with no usable password and emails a
 * set-password link.
 *
 * @return array{status: string, link: ?string} status is one of:
 *   'ok', 'exists' (email already registered), 'invalid' (bad
 *   email/role), or 'mail_failed' (account created, but send_mail()
 *   reported failure — 'link' is included so the caller can hand it to
 *   the inviter directly as a fallback). 'link' is also included
 *   when MAIL_DEV_MODE is on, since the email only went to the log.
 */
function invite_user(string $email, string $name, string $role): array
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if ($email === '' || $name === '' || !in_array($role, ['admin', 'teacher', 'parent'], true)) {
        return ['status' => 'invalid', 'link' => null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'invalid', 'link' => null];
    }

    $existing = db()->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetch()) {
        return ['status' => 'exists', 'link' => null];
    }

    // No one can log in with this hash — it's replaced the moment the
    // invite link is used to set a real password.
    $placeholder = hash_password(bin2hex(random_bytes(32)));

    $stmt = db()->prepare('INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $placeholder, $name, $role]);
    $userId = (int) db()->lastInsertId();

    $token = create_token($userId, 'invite');
    $link = SITE_BASE_URL . '/auth/reset-password.php?token=' . $token;
    $roleLabel = match ($role) {
        'admin' => 'administrátora',
        'teacher' => 'učitele',
        default => 'rodiče',
    };
    $body = "Dobrý den {$name},\n\n"
        . "byl/a vám vytvořen přístup do portálu webu INSPIRA jako {$roleLabel}.\n\n"
        . "Pro nastavení hesla a první přihlášení klikněte na odkaz níže. Odkaz je platný 7 dní:\n"
        . $link . "\n\n"
        . "INSPIRA";
    $sent = send_mail($email, $name, 'Pozvánka do portálu INSPIRA', $body);

    if (!$sent) {
        return ['status' => 'mail_failed', 'link' => $link];
    }
    return ['status' => 'ok', 'link' => MAIL_DEV_MODE ? $link : null];
}
