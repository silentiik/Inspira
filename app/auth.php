<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 900; // 15 minutes

/** The logged-in user's row, or null. Cached per request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }
    $user = $row;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /auth/login.php');
        exit;
    }
    return $user;
}

/** @param string[] $roles */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Nemáte oprávnění zobrazit tuto stránku.');
    }
    return $user;
}

/**
 * Verifies credentials, applying a per-account lockout after repeated
 * failures. Returns the user row on success, or a string error code on
 * failure: 'invalid', 'locked', or 'inactive'.
 */
function attempt_login(string $email, string $password)
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user) {
        // Still hash something to keep timing similar whether or not the
        // account exists, without revealing which case occurred.
        password_verify($password, '$2y$10$invalidsaltinvalidsaltin');
        return 'invalid';
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return 'locked';
    }

    if (!$user['is_active']) {
        return 'inactive';
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int) $user['failed_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= MAX_FAILED_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_SECONDS);
            $attempts = 0;
        }
        $upd = db()->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
        $upd->execute([$attempts, $lockedUntil, $user['id']]);
        return $lockedUntil ? 'locked' : 'invalid';
    }

    db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
        ->execute([$user['id']]);

    return $user;
}

function log_in_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function log_out_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Creates a one-time token for password reset or first-time invite
 * links. Returns the RAW token (only ever exposed via the emailed URL —
 * the database stores only its hash).
 */
function create_token(int $userId, string $purpose): string
{
    $raw = bin2hex(random_bytes(32));
    $ttl = $purpose === 'invite' ? INVITE_TOKEN_TTL_SECONDS : RESET_TOKEN_TTL_SECONDS;
    $stmt = db()->prepare(
        'INSERT INTO password_resets (user_id, token_hash, purpose, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        hash('sha256', $raw),
        $purpose,
        // gmdate, not date: SQLite's datetime('now') (used to check
        // expiry) is always UTC, regardless of the server's PHP
        // timezone setting — this must match or tokens expire at the
        // wrong time.
        gmdate('Y-m-d H:i:s', time() + $ttl),
    ]);
    return $raw;
}

/**
 * Looks up an unused, unexpired token and returns its row (joined with
 * the user id) or null. Does not consume it — call consume_token()
 * after the password has actually been changed.
 */
function find_valid_token(string $rawToken, string $purpose): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM password_resets
         WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > datetime(\'now\')'
    );
    $stmt->execute([hash('sha256', $rawToken), $purpose]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function consume_token(int $tokenId): void
{
    db()->prepare('UPDATE password_resets SET used_at = datetime(\'now\') WHERE id = ?')
        ->execute([$tokenId]);
}
