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
