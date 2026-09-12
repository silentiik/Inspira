<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO connection, creating the SQLite file and schema
 * on first use. Safe to call on every request — every statement is
 * CREATE-IF-NOT-EXISTS, so this is a cheap no-op once the schema exists.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    migrate($pdo);

    return $pdo;
}

/** Adds $column to $table if it isn't there yet — safe to call every request. */
function ensure_column(PDO $pdo, string $table, string $column, string $columnDdl): void
{
    $existing = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array($column, $existing, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $columnDdl");
    }
}

function migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin','teacher','parent')),
        is_active INTEGER NOT NULL DEFAULT 1,
        failed_attempts INTEGER NOT NULL DEFAULT 0,
        locked_until TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // first_name/last_name replaced the single `name` column — added via
    // ALTER TABLE so existing installs (with data already in `name`)
    // upgrade in place instead of losing their one admin account.
    ensure_column($pdo, 'users', 'first_name', "first_name TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'last_name', "last_name TEXT NOT NULL DEFAULT ''");
    $unmigrated = $pdo->query("SELECT id, name FROM users WHERE first_name = '' AND name != ''")->fetchAll();
    foreach ($unmigrated as $row) {
        $parts = explode(' ', trim((string) $row['name']), 2);
        $pdo->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?')
            ->execute([$parts[0], $parts[1] ?? '', $row['id']]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL,
        purpose TEXT NOT NULL CHECK(purpose IN ('reset','invite')),
        expires_at TEXT NOT NULL,
        used_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token_hash)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS children (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        program TEXT NOT NULL CHECK(program IN ('inspirka','domskolaci')),
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Same first_name/last_name split as users, plus date of birth —
    // added via ALTER TABLE so any child rows created before this
    // upgrade in place instead of disappearing.
    ensure_column($pdo, 'children', 'first_name', "first_name TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'children', 'last_name', "last_name TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'children', 'date_of_birth', 'date_of_birth TEXT');
    $unmigratedChildren = $pdo->query("SELECT id, name FROM children WHERE first_name = '' AND name != ''")->fetchAll();
    foreach ($unmigratedChildren as $row) {
        $parts = explode(' ', trim((string) $row['name']), 2);
        $pdo->prepare('UPDATE children SET first_name = ?, last_name = ? WHERE id = ?')
            ->execute([$parts[0], $parts[1] ?? '', $row['id']]);
    }

    // Many-to-many: a child can have more than one guardian account
    // (mother and father, say). `children.parent_id` predates this and
    // is kept populated (first guardian) only for any old code reading
    // it directly — new code always goes through this join table.
    $pdo->exec("CREATE TABLE IF NOT EXISTS child_guardians (
        child_id INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        PRIMARY KEY (child_id, user_id)
    )");
    $pdo->exec(
        'INSERT OR IGNORE INTO child_guardians (child_id, user_id)
         SELECT id, parent_id FROM children'
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS lunch_selections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        child_id INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
        week_start TEXT NOT NULL,
        day TEXT NOT NULL CHECK(day IN ('po','ut','st','ct','pa')),
        meal_option TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(child_id, week_start, day)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        pinned INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS content_blocks (
        block_key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        editable_by_teacher INTEGER NOT NULL DEFAULT 0,
        updated_by INTEGER REFERENCES users(id),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        program TEXT NOT NULL CHECK(program IN ('inspirka','domskolaci')),
        days INTEGER NOT NULL,
        price INTEGER NOT NULL,
        note TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        UNIQUE(program, days)
    )");

    seed_defaults($pdo);
}

/**
 * Seeds default rows if they don't already exist yet — per-row, not
 * per-table, so adding a new default here later still takes effect on
 * existing installs instead of only on a brand new empty database.
 */
function seed_defaults(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO pricing (program, days, price, note, sort_order) VALUES (?, ?, ?, ?, ?)'
    );
    $rows = [
        ['inspirka', 5, 7990, null, 1],
        ['inspirka', 4, 6790, null, 2],
        ['inspirka', 3, 5590, null, 3],
        ['inspirka', 2, 3890, 'minimum', 4],
        ['domskolaci', 3, 5390, null, 1],
        ['domskolaci', 2, 3690, 'minimum', 2],
    ];
    foreach ($rows as $row) {
        $stmt->execute($row);
    }

    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO content_blocks (block_key, value, editable_by_teacher) VALUES (?, ?, ?)'
    );
    $defaults = [
        ['home.hero.title', "Podporujeme růst dítěte\nvlastním tempem", 0],
        ['home.hero.lead', 'Propojujeme moderní pedagogiku s respektujícím přístupem. Rozvíjíme dovednosti, kreativitu i samostatné myšlení — a necháváme se přitom inspirovat pohledem dětí na svět.', 1],
        ['home.event.text', '🎉 Rodinný den v přírodě — 24. 9. 2026, 15–18 h, zdarma', 1],
        ['home.banner.title', 'Aktuálně přijímáme přihlášky na září 2026', 1],
        ['home.banner.lead', 'Právě probíhá přihlašování na školní rok 2026/2027. Domluvte si s námi schůzku, přijďte se podívat, jak to u nás funguje, a zeptejte se na cokoliv, co vás zajímá.', 1],
    ];
    foreach ($defaults as $row) {
        $stmt->execute($row);
    }
}
