<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';
require_once __DIR__ . '/app/children.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_lunch' && $user['role'] === 'parent') {
        $childId = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to($childId, (int) $user['id'])) {
            $week = week_start('next monday');
            foreach (array_keys(LUNCH_DAYS) as $day) {
                $value = (string) ($_POST['lunch_' . $day] ?? '');
                if ($value !== '' && in_array($value, LUNCH_OPTIONS, true)) {
                    save_lunch_selection($childId, $week, $day, $value);
                }
            }
            flash_set('success', 'Výběr obědů byl uložen.');
        }
        header('Location: /obedy.php');
        exit;
    }
}

$children = $user['role'] === 'parent' ? children_for_parent((int) $user['id']) : [];
$nextWeek = week_start('next monday');

$pageTitle = 'Obědy | INSPIRA';
require_once __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Obědy</h1>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="stack">
        <div class="form-card">
          <h3 class="mt-0">🍽️ Výběr obědů na týden od <?= htmlspecialchars(date('j. n. Y', strtotime($nextWeek)), ENT_QUOTES, 'UTF-8') ?></h3>

          <?php if (empty($children)): ?>
            <p class="hint-text"><?= $user['role'] === 'parent' ? 'Zatím k vám není přiřazené žádné dítě. Kontaktujte prosím centrum.' : 'Žádné děti k zobrazení.' ?></p>
          <?php endif; ?>

          <?php foreach ($children as $child): ?>
            <?php $selections = lunch_selections_for((int) $child['id'], $nextWeek); ?>
            <form method="post" action="/obedy.php" style="margin-bottom:24px;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_lunch">
              <input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>">
              <h4><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></h4>
              <div class="lunch-week">
                <?php foreach (LUNCH_DAYS as $code => $label): ?>
                  <div class="lunch-day<?= isset($selections[$code]) ? ' is-saved' : '' ?>">
                    <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                    <select name="lunch_<?= $code ?>">
                      <option value="">Nevybráno</option>
                      <?php foreach (LUNCH_OPTIONS as $option): ?>
                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"<?= ($selections[$code] ?? '') === $option ? ' selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="submit" class="btn btn--primary btn--block" style="margin-top:14px;">Uložit výběr pro <?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
