<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const PROGRAM_LABELS = [
    'inspirka' => ['label' => 'INSPIRKA — dětský klub (3–6 let)', 'schedule' => 'Po–Pá, 8:00–16:00'],
    'domskolaci' => ['label' => 'Domškolácká akademie (1.–9. ročník)', 'schedule' => 'Po–St, 9:00–14:00'],
];

/** All pricing rows, ordered for display. */
function all_pricing(): array
{
    return db()->query('SELECT * FROM pricing ORDER BY program, sort_order, days')->fetchAll();
}

/** Pricing grouped by program, shaped for the calculator.js PRICING object. */
function pricing_for_calculator(): array
{
    $rows = all_pricing();
    $result = [];
    foreach (PROGRAM_LABELS as $key => $meta) {
        $result[$key] = [
            'label' => $meta['label'],
            'schedule' => $meta['schedule'],
            'days' => [],
        ];
    }
    foreach ($rows as $row) {
        if (!isset($result[$row['program']])) {
            continue;
        }
        $entry = ['value' => (int) $row['days'], 'price' => (int) $row['price']];
        if (!empty($row['note'])) {
            $entry['note'] = $row['note'];
        }
        $result[$row['program']]['days'][] = $entry;
    }
    return $result;
}

function update_pricing_row(int $id, int $price, ?string $note): void
{
    $stmt = db()->prepare('UPDATE pricing SET price = ?, note = ? WHERE id = ?');
    $stmt->execute([$price, $note ?: null, $id]);
}

/**
 * The lunch price in effect on $date — the most recently started price
 * period that had already begun by then. Returns 0 if no price has ever
 * been set (rather than guessing), so a missing price is visibly "0 Kč"
 * instead of silently wrong.
 */
function lunch_price_on(string $date): int
{
    $stmt = db()->prepare('SELECT price FROM lunch_prices WHERE valid_from <= ? ORDER BY valid_from DESC, id DESC LIMIT 1');
    $stmt->execute([$date]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (int) $value : 0;
}

/** Schedules a new lunch price starting from $validFrom. Past periods are never edited — this only adds a new one that supersedes them going forward. */
function add_lunch_price(int $price, string $validFrom, int $updatedBy): void
{
    $stmt = db()->prepare('INSERT INTO lunch_prices (price, valid_from, updated_by, updated_at) VALUES (?, ?, ?, datetime(\'now\'))');
    $stmt->execute([$price, $validFrom, $updatedBy]);
}

/** Corrects an existing price period in place (fixing a typo'd amount or date) — unlike add_lunch_price(), this changes history rather than adding to it. */
function update_lunch_price(int $id, int $price, string $validFrom): void
{
    $stmt = db()->prepare('UPDATE lunch_prices SET price = ?, valid_from = ? WHERE id = ?');
    $stmt->execute([$price, $validFrom, $id]);
}

/**
 * Every lunch price period, most recent first, each with an explicit
 * 'valid_until' computed from the next (older) row's start date — null
 * for the currently active period. For the admin history view.
 */
function all_lunch_prices(): array
{
    $rows = db()->query('SELECT * FROM lunch_prices ORDER BY valid_from DESC, id DESC')->fetchAll();
    foreach ($rows as $i => &$row) {
        $row['valid_until'] = $i > 0
            ? (new DateTimeImmutable($rows[$i - 1]['valid_from']))->modify('-1 day')->format('Y-m-d')
            : null;
    }
    unset($row);
    return $rows;
}
