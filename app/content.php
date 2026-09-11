<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Reads an editable content block, falling back to $default if the key
 * doesn't exist yet (e.g. before any admin has touched it) or the row
 * is missing entirely. Output is escaped for HTML by the caller — this
 * returns raw text.
 */
function get_content(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = db()->prepare('SELECT value FROM content_blocks WHERE block_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? $row['value'] : $default;
    return $cache[$key];
}

/** Same as get_content(), but HTML-escaped and with newlines kept as <br>. */
function content_html(string $key, string $default = ''): string
{
    return nl2br(htmlspecialchars(get_content($key, $default), ENT_QUOTES, 'UTF-8'));
}

function set_content(string $key, string $value, int $updatedBy): void
{
    $stmt = db()->prepare(
        'INSERT INTO content_blocks (block_key, value, updated_by, updated_at)
         VALUES (?, ?, ?, datetime(\'now\'))
         ON CONFLICT(block_key) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by, updated_at = excluded.updated_at'
    );
    $stmt->execute([$key, $value, $updatedBy]);
}

/** All content blocks, for the admin/teacher editor list. */
function all_content_blocks(): array
{
    return db()->query('SELECT * FROM content_blocks ORDER BY block_key')->fetchAll();
}
