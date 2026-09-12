<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const LUNCH_DAYS = ['po' => 'Pondělí', 'ut' => 'Úterý', 'st' => 'Středa', 'ct' => 'Čtvrtek', 'pa' => 'Pátek'];
const LUNCH_OPTIONS = [
    'Polévka + hlavní jídlo (masité)',
    'Polévka + hlavní jídlo (bezmasé)',
    'Polévka + hlavní jídlo (bez lepku)',
    'Bez oběda',
];

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

/** [day_code => meal_option] for one child's given week. */
function lunch_selections_for(int $childId, string $weekStart): array
{
    $stmt = db()->prepare('SELECT day, meal_option FROM lunch_selections WHERE child_id = ? AND week_start = ?');
    $stmt->execute([$childId, $weekStart]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['day']] = $row['meal_option'];
    }
    return $result;
}

function save_lunch_selection(int $childId, string $weekStart, string $day, string $mealOption): void
{
    $stmt = db()->prepare(
        'INSERT INTO lunch_selections (child_id, week_start, day, meal_option, updated_at)
         VALUES (?, ?, ?, ?, datetime(\'now\'))
         ON CONFLICT(child_id, week_start, day) DO UPDATE SET meal_option = excluded.meal_option, updated_at = excluded.updated_at'
    );
    $stmt->execute([$childId, $weekStart, $day, $mealOption]);
}
