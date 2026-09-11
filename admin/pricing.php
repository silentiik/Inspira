<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/pricing.php';

require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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

$pageTitle = 'Ceník — správa | INSPIRA';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/dashboard.php">Nástěnka</a> / Ceník</p>
      <h1>Správa ceníku</h1>
      <p class="lead">Změny se ihned projeví v kalkulačce i ceníku na webu.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
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
