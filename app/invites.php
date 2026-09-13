<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/config.php';

/**
 * Creates a new account. With $password omitted, it gets no usable
 * password and an emailed set-password link (the classic invite flow).
 * With $password given, the account is created ready to use immediately
 * with that password and no email is sent — useful for handing
 * credentials to someone directly, or when mail isn't confirmed working
 * yet.
 *
 * @return array{status: string, link: ?string} status is one of:
 *   'ok', 'exists' (email already registered), 'invalid' (bad
 *   email/role/names), or 'mail_failed' (account created, but
 *   send_mail() reported failure — 'link' is included so the caller can
 *   hand it to the inviter directly as a fallback). 'link' is also
 *   included when MAIL_DEV_MODE is on, since the email only went to the
 *   log. 'link' is always null when $password was set directly.
 *
 * @param ?string $gender 'male' or 'female', or null to leave unset.
 * @param ?string $phone Free-form phone number, or null to leave unset.
 */
function invite_user(string $email, string $firstName, string $lastName, string $role, ?string $password = null, ?string $gender = null, ?string $phone = null): array
{
    $email = strtolower(trim($email));
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    if ($email === '' || $firstName === '' || $lastName === '' || !in_array($role, ['admin', 'teacher', 'parent'], true)) {
        return ['status' => 'invalid', 'link' => null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'invalid', 'link' => null];
    }
    if ($password !== null && !password_meets_policy($password)) {
        return ['status' => 'invalid', 'link' => null];
    }
    $gender = in_array($gender, ['male', 'female'], true) ? $gender : null;
    $phone = trim((string) $phone);
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
        return ['status' => 'invalid', 'link' => null];
    }
    $phone = $phone !== '' ? $phone : null;

    $existing = db()->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetch()) {
        return ['status' => 'exists', 'link' => null];
    }

    // A random, never-shared placeholder when no direct password was
    // given — it's replaced the moment the invite link is used.
    $passwordHash = hash_password($password ?? bin2hex(random_bytes(32)));

    $stmt = db()->prepare(
        'INSERT INTO users (email, password_hash, name, first_name, last_name, role, gender, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$email, $passwordHash, trim("$firstName $lastName"), $firstName, $lastName, $role, $gender, $phone]);
    $userId = (int) db()->lastInsertId();

    if ($password !== null) {
        return ['status' => 'ok', 'link' => null];
    }

    $token = create_token($userId, 'invite');
    $link = SITE_BASE_URL . '/auth/reset-password.php?token=' . $token;
    $roleLabel = match ($role) {
        'admin' => 'administrátora',
        'teacher' => 'lektora',
        default => 'rodiče',
    };
    $body = "Dobrý den {$firstName},\n\n"
        . "byl/a vám vytvořen přístup do portálu webu INSPIRA jako {$roleLabel}.\n\n"
        . "Pro nastavení hesla a první přihlášení klikněte na odkaz níže. Odkaz je platný 7 dní:\n"
        . $link . "\n\n"
        . "INSPIRA";
    $sent = send_mail($email, "$firstName $lastName", 'Pozvánka do portálu INSPIRA', $body);

    if (!$sent) {
        return ['status' => 'mail_failed', 'link' => $link];
    }
    return ['status' => 'ok', 'link' => MAIL_DEV_MODE ? $link : null];
}
