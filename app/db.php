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
    // Optional — drives the avatar colour (unset/female keeps the
    // site's usual palette, male switches it to light blue). NULL is
    // fine on existing accounts: SQLite's CHECK passes on NULL, and the
    // app just treats NULL the same as 'female' when rendering.
    ensure_column($pdo, 'users', 'gender', "gender TEXT CHECK(gender IN ('male','female'))");
    ensure_column($pdo, 'users', 'phone', 'phone TEXT');
    // Optional alternate login handle — most accounts leave this NULL
    // and sign in with their email. A partial unique index (rather than
    // a UNIQUE column constraint, which SQLite's ALTER TABLE ADD COLUMN
    // doesn't support) enforces uniqueness only among the rows that
    // actually set one.
    ensure_column($pdo, 'users', 'username', 'username TEXT');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username) WHERE username IS NOT NULL');
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
    ensure_column($pdo, 'children', 'gender', "gender TEXT CHECK(gender IN ('male','female'))");
    $unmigratedChildren = $pdo->query("SELECT id, name FROM children WHERE first_name = '' AND name != ''")->fetchAll();
    foreach ($unmigratedChildren as $row) {
        $parts = explode(' ', trim((string) $row['name']), 2);
        $pdo->prepare('UPDATE children SET first_name = ?, last_name = ? WHERE id = ?')
            ->execute([$parts[0], $parts[1] ?? '', $row['id']]);
    }

    // Many-to-many: a child can have more than one guardian account
    // (mother and father, say) — or none at all yet, if it's created
    // before anyone decides who it belongs to.
    $pdo->exec("CREATE TABLE IF NOT EXISTS child_guardians (
        child_id INTEGER NOT NULL REFERENCES children(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        PRIMARY KEY (child_id, user_id)
    )");

    // `children.parent_id` predates child_guardians and required every
    // child to have exactly one (NOT NULL, foreign-keyed) owner. SQLite
    // can't just ALTER that constraint away, so on any install that
    // still has the column: backfill child_guardians from it (in case
    // this install never got the earlier migration that did the same),
    // then rebuild the table without it, following SQLite's documented
    // procedure for schema changes ALTER TABLE can't express directly.
    $childrenColumns = $pdo->query('PRAGMA table_info(children)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array('parent_id', $childrenColumns, true)) {
        $pdo->exec(
            'INSERT OR IGNORE INTO child_guardians (child_id, user_id)
             SELECT id, parent_id FROM children'
        );

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();
        try {
            $pdo->exec("CREATE TABLE children_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                program TEXT NOT NULL CHECK(program IN ('inspirka','domskolaci')),
                first_name TEXT NOT NULL DEFAULT '',
                last_name TEXT NOT NULL DEFAULT '',
                date_of_birth TEXT,
                gender TEXT CHECK(gender IN ('male','female')),
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )");
            $pdo->exec(
                'INSERT INTO children_new (id, name, program, first_name, last_name, date_of_birth, gender, created_at)
                 SELECT id, name, program, first_name, last_name, date_of_birth, gender, created_at FROM children'
            );
            $pdo->exec('DROP TABLE children');
            $pdo->exec('ALTER TABLE children_new RENAME TO children');
            $fkErrors = $pdo->query('PRAGMA foreign_key_check')->fetchAll();
            if ($fkErrors) {
                throw new RuntimeException('foreign_key_check failed after dropping children.parent_id');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $pdo->exec('PRAGMA foreign_keys = ON');
            throw $e;
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

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
    // 'text' = legacy plain-text body, rendered escaped with nl2br().
    // 'html' = sanitized rich-text HTML from the formatting toolbar,
    // rendered as-is. Every post written through the current editor is
    // 'html'; older rows stay 'text' until next edited.
    ensure_column($pdo, 'news', 'body_format', "body_format TEXT NOT NULL DEFAULT 'text'");

    // Files (photos or documents) attached to a news post. The actual
    // bytes live on disk under DATA_DIR/news-uploads, named randomly —
    // this row is what maps a safe random filename back to the
    // original name and lets attachment.php authorize + serve it.
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        news_id INTEGER NOT NULL REFERENCES news(id) ON DELETE CASCADE,
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        size_bytes INTEGER NOT NULL,
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
