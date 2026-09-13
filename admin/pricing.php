<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/pricing.php';

$user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // The lunch-price forms post distinct field names so they don't collide
    // with the per-row id+price pricing forms below. lunch_price_id is only
    // present on the edit form for an existing period, not the "add a new
    // price" form.
    if (isset($_POST['lunch_price']) && isset($_POST['valid_from'])) {
        $lunchPrice = (int) $_POST['lunch_price'];
        $validFrom = (string) $_POST['valid_from'];
        $lunchPriceId = (int) ($_POST['lunch_price_id'] ?? 0);
        if ($lunchPrice > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) {
            if ($lunchPriceId > 0) {
                update_lunch_price($lunchPriceId, $lunchPrice, $validFrom);
                flash_set('success', 'Cena obědu byla upravena.');
            } else {
                add_lunch_price($lunchPrice, $validFrom, (int) $user['id']);
                flash_set('success', 'Cena obědu byla nastavena.');
            }
        } else {
            flash_set('error', 'Zadejte prosím platnou cenu a datum.');
        }
        header('Location: /admin/pricing.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $price = (int) ($_POST['price'] ?? 0);
    $note = trim((string) ($_POST['note'] ?? ''));

    if ($id > 0 && $price > 0) {
        update_pricing_row($id, $price, $note !== '' ? $note : null);
        flash_set('success', 'Cena byla uložena.');
    } else {
        flash_set('error', 'Zadejte prosím platnou cenu.');
    }

    header('Location: /admin/pricing.php');
    exit;
}

$rows = all_pricing();
$lunchPrices = all_lunch_prices();
$todayDate = (new DateTimeImmutable('now'))->format('Y-m-d');

$pageTitle = 'Ceník — správa | INSPIRA';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Správa ceníku</h1>
      <p class="lead">Změny se ihned projeví v kalkulačce i ceníku na webu.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <h2>Obědy</h2>
      <p class="hint-text">Nová cena platí od zvoleného data dál — dřívější obědy zůstanou spočítané podle ceny, která platila v den, kdy byly objednané.</p>
      <div class="card-grid" style="margin-bottom:16px;">
        <form method="post" action="/admin/pricing.php" class="card">
          <?= csrf_field() ?>
          <h3 class="mt-0">Nová cena obědu</h3>
          <div class="field">
            <label for="lunch_price">Cena (Kč / oběd)</label>
            <input type="number" id="lunch_price" name="lunch_price" min="1" required>
          </div>
          <div class="field">
            <label for="valid_from">Platné od</label>
            <input type="date" id="valid_from" name="valid_from" value="<?= htmlspecialchars($todayDate, ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
        </form>
      </div>

      <?php if (!empty($lunchPrices)): ?>
        <table class="price-table" style="margin-bottom:32px;">
          <thead><tr><th>Od</th><th>Do</th><th>Cena</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($lunchPrices as $period): ?>
              <tr>
                <td><?= htmlspecialchars((new DateTimeImmutable($period['valid_from']))->format('j. n. Y'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $period['valid_until'] !== null ? htmlspecialchars((new DateTimeImmutable($period['valid_until']))->format('j. n. Y'), ENT_QUOTES, 'UTF-8') : 'nyní' ?></td>
                <td><?= number_format((int) $period['price'], 0, ',', ' ') ?> Kč</td>
                <td style="text-align:right;">
                  <button type="button" class="icon-btn icon-btn--edit" data-toggle-edit="edit-lunch-price-<?= (int) $period['id'] ?>" aria-label="Upravit cenu">✎</button>
                </td>
              </tr>
              <tr id="edit-lunch-price-<?= (int) $period['id'] ?>" hidden>
                <td colspan="4">
                  <form method="post" action="/admin/pricing.php" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="lunch_price_id" value="<?= (int) $period['id'] ?>">
                    <div class="field" style="margin-bottom:0;">
                      <label for="edit-lunch-price-price-<?= (int) $period['id'] ?>">Cena (Kč / oběd)</label>
                      <input type="number" id="edit-lunch-price-price-<?= (int) $period['id'] ?>" name="lunch_price" value="<?= (int) $period['price'] ?>" min="1" required>
                    </div>
                    <div class="field" style="margin-bottom:0;">
                      <label for="edit-lunch-price-date-<?= (int) $period['id'] ?>">Platné od</label>
                      <input type="date" id="edit-lunch-price-date-<?= (int) $period['id'] ?>" name="valid_from" value="<?= htmlspecialchars($period['valid_from'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php foreach (['inspirka' => 'INSPIRKA', 'domskolaci' => 'Domškolácká akademie'] as $programKey => $programLabel): ?>
        <h2><?= htmlspecialchars($programLabel, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="card-grid" style="margin-bottom:32px;">
          <?php foreach ($rows as $row): if ($row['program'] !== $programKey) continue; ?>
            <form method="post" action="/admin/pricing.php" class="card">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <h3 class="mt-0"><?= (int) $row['days'] ?> dny/dní týdně</h3>
              <div class="field">
                <label for="price-<?= (int) $row['id'] ?>">Cena (Kč / měsíc)</label>
                <input type="number" id="price-<?= (int) $row['id'] ?>" name="price" value="<?= (int) $row['price'] ?>" min="1" required>
              </div>
              <div class="field">
                <label for="note-<?= (int) $row['id'] ?>">Poznámka (nepovinné, např. "minimum")</label>
                <input type="text" id="note-<?= (int) $row['id'] ?>" name="note" value="<?= htmlspecialchars((string) $row['note'], ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
