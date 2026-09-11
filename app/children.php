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

function children_for_parent(int $parentId): array
{
    $stmt = db()->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY name');
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

function add_child(int $parentId, string $name, string $program): int
{
    $stmt = db()->prepare('INSERT INTO children (parent_id, name, program) VALUES (?, ?, ?)');
    $stmt->execute([$parentId, $name, $program]);
    return (int) db()->lastInsertId();
}

/** Verifies the child belongs to this parent before any lunch write. */
function child_belongs_to(int $childId, int $parentId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM children WHERE id = ? AND parent_id = ?');
    $stmt->execute([$childId, $parentId]);
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
