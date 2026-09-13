<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';
require_once __DIR__ . '/app/children.php';

$user = require_login();
$canEditMenu = in_array($user['role'], ['admin', 'teacher'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $week = week_start('next monday');
    $weekDates = week_day_dates($week);

    if ($action === 'save_lunch' && $user['role'] === 'parent') {
        $childId = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to($childId, (int) $user['id'])) {
            foreach ($weekDates as $day => $date) {
                $wantsLunch = !empty($_POST['lunch_' . $day]);
                $mealSnapshot = menu_for_date($date) ?? 'Jídelníček zatím nebyl nastaven.';
                save_lunch_selection($childId, $week, $day, $wantsLunch, $mealSnapshot);
            }
            flash_set('success', 'Výběr obědů byl uložen.');
        }
        header('Location: /obedy.php');
        exit;
    }

    if ($action === 'save_menu' && $canEditMenu) {
        foreach ($weekDates as $day => $date) {
            $text = trim((string) ($_POST['menu_' . $day] ?? ''));
            if ($text !== '') {
                save_menu_for_date($date, $text, (int) $user['id']);
            }
        }
        flash_set('success', 'Jídelníček byl uložen.');
        header('Location: /obedy.php');
        exit;
    }
}

$children = $user['role'] === 'parent' ? children_for_parent((int) $user['id']) : [];
$nextWeek = week_start('next monday');
$weekDates = week_day_dates($nextWeek);
$weekMenus = menus_for_week($nextWeek);
$weekStartDt = new DateTimeImmutable($nextWeek);
$weekEndDt = $weekStartDt->modify('+4 days');
$weekNumber = (int) $weekStartDt->format('W');

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
        <div class="form-card news-board-header">
          <h3 class="mt-0 text-center news-board-title">🍽️ Výběr obědů — týden č. <?= $weekNumber ?> (<?= htmlspecialchars($weekStartDt->format('j. n.'), ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($weekEndDt->format('j. n. Y'), ENT_QUOTES, 'UTF-8') ?>)</h3>
        </div>

        <?php if ($canEditMenu): ?>
          <div class="form-card">
            <h3 class="mt-0">Jídelníček na tento týden</h3>
            <p class="hint-text">Sem zadejte, co se bude v jednotlivé dny vařit — rodiče pak jen zaškrtnou, jestli oběd chtějí.</p>
            <form method="post" action="/obedy.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_menu">
              <?php foreach (LUNCH_DAYS as $code => $label): ?>
                <div class="field">
                  <label for="menu_<?= $code ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((new DateTimeImmutable($weekDates[$code]))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?>)</label>
                  <input type="text" id="menu_<?= $code ?>" name="menu_<?= $code ?>" value="<?= htmlspecialchars($weekMenus[$weekDates[$code]] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Např. Polévka + kuřecí řízek s bramborovou kaší">
                </div>
              <?php endforeach; ?>
              <button type="submit" class="btn btn--primary">Uložit jídelníček</button>
            </form>
          </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'parent'): ?>
          <div class="form-card">
            <?php if (empty($children)): ?>
              <p class="hint-text">Zatím k vám není přiřazené žádné dítě. Kontaktujte prosím centrum.</p>
            <?php endif; ?>

            <?php foreach ($children as $child): ?>
              <?php $selections = lunch_selections_for((int) $child['id'], $nextWeek); ?>
              <form method="post" action="/obedy.php" style="margin-bottom:24px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_lunch">
                <input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>">
                <h4 class="lunch-child-name"><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="lunch-week">
                  <?php foreach (LUNCH_DAYS as $code => $label): ?>
                    <?php
                      $date = $weekDates[$code];
                      $mealText = $weekMenus[$date] ?? 'Jídelníček zatím nebyl nastaven.';
                      $inputId = 'lunch_' . $code . '_' . (int) $child['id'];
                    ?>
                    <label class="lunch-day<?= isset($selections[$code]) ? ' is-saved' : '' ?>" for="<?= $inputId ?>">
                      <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($date))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                      <span class="lunch-day-meal"><?= htmlspecialchars($mealText, ENT_QUOTES, 'UTF-8') ?></span>
                      <input type="checkbox" id="<?= $inputId ?>" name="lunch_<?= $code ?>" class="lunch-checkbox"<?= isset($selections[$code]) ? ' checked' : '' ?>>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn--primary btn--block" style="margin-top:14px;">Uložit výběr pro <?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
