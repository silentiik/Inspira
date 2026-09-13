<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const LUNCH_DAYS = ['po' => 'Pondělí', 'ut' => 'Úterý', 'st' => 'Středa', 'ct' => 'Čtvrtek', 'pa' => 'Pátek'];

const CHILD_PROGRAMS = ['inspirka' => 'INSPIRKA', 'domskolaci' => 'Domškolák'];

/** All children linked to this account as any one of (possibly several) guardians. */
function children_for_parent(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT children.* FROM children
         JOIN child_guardians ON child_guardians.child_id = children.id
         WHERE child_guardians.user_id = ?
         ORDER BY children.first_name, children.last_name'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** All children with their guardians' names/ids joined in, for the admin list. */
function all_children_with_parent(): array
{
    $rows = db()->query(
        "SELECT children.*,
                child_guardians.user_id AS guardian_id,
                trim(users.first_name || ' ' || users.last_name) AS guardian_name
         FROM children
         LEFT JOIN child_guardians ON child_guardians.child_id = children.id
         LEFT JOIN users ON users.id = child_guardians.user_id
         ORDER BY children.last_name, children.first_name, guardian_name"
    )->fetchAll();

    $byChild = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if (!isset($byChild[$id])) {
            $byChild[$id] = $row;
            $byChild[$id]['guardian_ids'] = [];
            $byChild[$id]['guardian_names'] = [];
        }
        if ($row['guardian_id'] !== null) {
            $byChild[$id]['guardian_ids'][] = (int) $row['guardian_id'];
            $byChild[$id]['guardian_names'][] = $row['guardian_name'];
        }
    }
    return array_values($byChild);
}

/** The guardian accounts (user rows) linked to one child. */
function guardians_for_child(int $childId): array
{
    $stmt = db()->prepare(
        'SELECT users.* FROM users
         JOIN child_guardians ON child_guardians.user_id = users.id
         WHERE child_guardians.child_id = ?
         ORDER BY users.first_name, users.last_name'
    );
    $stmt->execute([$childId]);
    return $stmt->fetchAll();
}

function full_child_name(array $child): string
{
    return trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? ''));
}

/**
 * @param int[] $guardianIds Guardian accounts this child belongs to — may be empty; assign later via update_child().
 * @param ?string $gender 'male' or 'female', or null to leave unset.
 */
function add_child(array $guardianIds, string $firstName, string $lastName, string $program, ?string $dateOfBirth = null, ?string $gender = null): int
{
    $guardianIds = array_values(array_unique(array_filter(array_map('intval', $guardianIds))));
    $gender = in_array($gender, ['male', 'female'], true) ? $gender : null;
    $stmt = db()->prepare(
        'INSERT INTO children (name, first_name, last_name, program, date_of_birth, gender) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([trim("$firstName $lastName"), $firstName, $lastName, $program, $dateOfBirth ?: null, $gender]);
    $childId = (int) db()->lastInsertId();
    set_child_guardians($childId, $guardianIds);
    return $childId;
}

/**
 * @param int[] $guardianIds Replaces the full guardian set for this child — may be empty.
 * @param ?string $gender 'male' or 'female', or null to leave unset.
 */
function update_child(int $childId, array $guardianIds, string $firstName, string $lastName, string $program, ?string $dateOfBirth, ?string $gender = null): void
{
    $guardianIds = array_values(array_unique(array_filter(array_map('intval', $guardianIds))));
    $gender = in_array($gender, ['male', 'female'], true) ? $gender : null;
    $stmt = db()->prepare(
        'UPDATE children SET name = ?, first_name = ?, last_name = ?, program = ?, date_of_birth = ?, gender = ? WHERE id = ?'
    );
    $stmt->execute([trim("$firstName $lastName"), $firstName, $lastName, $program, $dateOfBirth ?: null, $gender, $childId]);
    set_child_guardians($childId, $guardianIds);
}

/** @param int[] $guardianIds */
function set_child_guardians(int $childId, array $guardianIds): void
{
    db()->prepare('DELETE FROM child_guardians WHERE child_id = ?')->execute([$childId]);
    $stmt = db()->prepare('INSERT OR IGNORE INTO child_guardians (child_id, user_id) VALUES (?, ?)');
    foreach ($guardianIds as $userId) {
        $stmt->execute([$childId, $userId]);
    }
}

function delete_child(int $childId): void
{
    db()->prepare('DELETE FROM children WHERE id = ?')->execute([$childId]);
}

/** Verifies the child belongs to this account as one of its guardians before any lunch write. */
function child_belongs_to(int $childId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM child_guardians WHERE child_id = ? AND user_id = ?');
    $stmt->execute([$childId, $userId]);
    return (bool) $stmt->fetchColumn();
}

/** Monday (ISO) of the given date's week. */
function week_start(string $date = 'now'): string
{
    $dt = new DateTimeImmutable($date);
    $offset = ((int) $dt->format('N')) - 1; // 1=Mon..7=Sun
    return $dt->modify("-{$offset} days")->format('Y-m-d');
}

/** [day_code => 'Y-m-d'] for the five weekdays of $weekStart (a Monday). */
function week_day_dates(string $weekStart): array
{
    $start = new DateTimeImmutable($weekStart);
    $dates = [];
    $offset = 0;
    foreach (array_keys(LUNCH_DAYS) as $code) {
        $dates[$code] = $start->modify("+{$offset} days")->format('Y-m-d');
        $offset++;
    }
    return $dates;
}

/** The one shared meal set for $date, or null if nobody has set it yet. */
function menu_for_date(string $date): ?string
{
    $stmt = db()->prepare('SELECT meal_text FROM daily_menus WHERE date = ?');
    $stmt->execute([$date]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : null;
}

/** [date => meal_text] for every date that has a menu set within [$weekStart, +4 days]. */
function menus_for_week(string $weekStart): array
{
    $end = (new DateTimeImmutable($weekStart))->modify('+4 days')->format('Y-m-d');
    $stmt = db()->prepare('SELECT date, meal_text FROM daily_menus WHERE date BETWEEN ? AND ? ORDER BY date');
    $stmt->execute([$weekStart, $end]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['date']] = $row['meal_text'];
    }
    return $result;
}

function save_menu_for_date(string $date, string $mealText, int $updatedBy): void
{
    $stmt = db()->prepare(
        'INSERT INTO daily_menus (date, meal_text, updated_by, updated_at)
         VALUES (?, ?, ?, datetime(\'now\'))
         ON CONFLICT(date) DO UPDATE SET meal_text = excluded.meal_text, updated_by = excluded.updated_by, updated_at = excluded.updated_at'
    );
    $stmt->execute([$date, $mealText, $updatedBy]);
}

/** [day_code => true] for the days this child is opted in for lunch, this week. */
function lunch_selections_for(int $childId, string $weekStart): array
{
    $stmt = db()->prepare('SELECT day FROM lunch_selections WHERE child_id = ? AND week_start = ? AND wants_lunch = 1');
    $stmt->execute([$childId, $weekStart]);
    return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

/** $mealSnapshot records what was actually on offer when the choice was made, for the record. */
function save_lunch_selection(int $childId, string $weekStart, string $day, bool $wantsLunch, string $mealSnapshot): void
{
    $stmt = db()->prepare(
        'INSERT INTO lunch_selections (child_id, week_start, day, wants_lunch, meal_option, updated_at)
         VALUES (?, ?, ?, ?, ?, datetime(\'now\'))
         ON CONFLICT(child_id, week_start, day) DO UPDATE SET wants_lunch = excluded.wants_lunch, meal_option = excluded.meal_option, updated_at = excluded.updated_at'
    );
    $stmt->execute([$childId, $weekStart, $day, $wantsLunch ? 1 : 0, $mealSnapshot]);
}

/** Same as lunch_selections_for(), but for an admin/teacher ordering lunch for themselves. */
function staff_lunch_selections_for(int $userId, string $weekStart): array
{
    $stmt = db()->prepare('SELECT day FROM staff_lunch_selections WHERE user_id = ? AND week_start = ? AND wants_lunch = 1');
    $stmt->execute([$userId, $weekStart]);
    return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

/** Same as save_lunch_selection(), but for an admin/teacher ordering lunch for themselves. */
function save_staff_lunch_selection(int $userId, string $weekStart, string $day, bool $wantsLunch, string $mealSnapshot): void
{
    $stmt = db()->prepare(
        'INSERT INTO staff_lunch_selections (user_id, week_start, day, wants_lunch, meal_option, updated_at)
         VALUES (?, ?, ?, ?, ?, datetime(\'now\'))
         ON CONFLICT(user_id, week_start, day) DO UPDATE SET wants_lunch = excluded.wants_lunch, meal_option = excluded.meal_option, updated_at = excluded.updated_at'
    );
    $stmt->execute([$userId, $weekStart, $day, $wantsLunch ? 1 : 0, $mealSnapshot]);
}

/**
 * For one week: per day, that day's menu and the list of names (children
 * and staff) who are opted in for lunch. Used by the admin/teacher
 * "Přehled obědů" overview.
 */
function lunch_orders_overview(string $weekStart): array
{
    $dates = week_day_dates($weekStart);
    $menus = menus_for_week($weekStart);

    $overview = [];
    foreach (LUNCH_DAYS as $code => $label) {
        $overview[$code] = [
            'label' => $label,
            'date' => $dates[$code],
            'menu' => $menus[$dates[$code]] ?? null,
            'orders' => [],
        ];
    }

    $stmt = db()->prepare(
        'SELECT ls.day, c.name AS person_name
         FROM lunch_selections ls
         JOIN children c ON c.id = ls.child_id
         WHERE ls.week_start = ? AND ls.wants_lunch = 1
         ORDER BY c.first_name, c.last_name'
    );
    $stmt->execute([$weekStart]);
    foreach ($stmt->fetchAll() as $row) {
        $overview[$row['day']]['orders'][] = $row['person_name'];
    }

    $stmt = db()->prepare(
        "SELECT sls.day, trim(u.first_name || ' ' || u.last_name) AS person_name
         FROM staff_lunch_selections sls
         JOIN users u ON u.id = sls.user_id
         WHERE sls.week_start = ? AND sls.wants_lunch = 1
         ORDER BY u.first_name, u.last_name"
    );
    $stmt->execute([$weekStart]);
    foreach ($stmt->fetchAll() as $row) {
        $overview[$row['day']]['orders'][] = $row['person_name'] . ' (lektor/ka)';
    }

    return $overview;
}

/**
 * Total ordered lunches per person (children and staff) within the
 * calendar month containing $monthDate, keyed by display name — for an
 * admin/teacher to use as a billing reference. A lunch_selections row
 * only records a week_start + day code, not the exact date, so this
 * resolves each row's actual date in PHP and filters by month there
 * rather than in SQL.
 */
function monthly_lunch_totals(string $monthDate): array
{
    $monthStart = (new DateTimeImmutable($monthDate))->modify('first day of this month')->format('Y-m-d');
    $monthEnd = (new DateTimeImmutable($monthDate))->modify('last day of this month')->format('Y-m-d');

    $totals = [];
    $tally = function (array $rows) use (&$totals, $monthStart, $monthEnd) {
        foreach ($rows as $row) {
            $date = week_day_dates($row['week_start'])[$row['day']];
            if ($date >= $monthStart && $date <= $monthEnd) {
                $totals[$row['person_name']] = ($totals[$row['person_name']] ?? 0) + 1;
            }
        }
    };

    $tally(db()->query(
        'SELECT ls.week_start, ls.day, c.name AS person_name
         FROM lunch_selections ls
         JOIN children c ON c.id = ls.child_id
         WHERE ls.wants_lunch = 1'
    )->fetchAll());

    $tally(db()->query(
        "SELECT sls.week_start, sls.day, trim(u.first_name || ' ' || u.last_name) || ' (lektor/ka)' AS person_name
         FROM staff_lunch_selections sls
         JOIN users u ON u.id = sls.user_id
         WHERE sls.wants_lunch = 1"
    )->fetchAll());

    ksort($totals, SORT_NATURAL | SORT_FLAG_CASE);
    return $totals;
}
