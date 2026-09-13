<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';
require_once __DIR__ . '/app/children.php';

$user = require_login();
$canEditMenu = in_array($user['role'], ['admin', 'teacher'], true);

/**
 * Normalizes a submitted/queried week into that week's Monday, falling
 * back to next week if missing or malformed. Accepts either a plain
 * 'Y-m-d' date (used internally by the prev/next links and the hidden
 * form field) or an ISO 'Y-Www' week number (what the <input
 * type="week"> picker submits).
 */
function resolve_viewed_week(string $requested): string
{
    if (preg_match('/^(\d{4})-W(\d{2})$/', $requested, $m)) {
        return (new DateTimeImmutable())->setISODate((int) $m[1], (int) $m[2], 1)->format('Y-m-d');
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) ? week_start($requested) : week_start('next monday');
}

/** One of the three admin/teacher tabs — anyone else always effectively gets 'vyber'. */
function resolve_view(string $requested): string
{
    return in_array($requested, ['vyber', 'nastaveni', 'prehled'], true) ? $requested : 'vyber';
}

const CZECH_MONTHS = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben', 5 => 'Květen', 6 => 'Červen',
    7 => 'Červenec', 8 => 'Srpen', 9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
];

/** "Září" for a week within one month, "Září – Říjen" when it crosses a month boundary. The year is shown in the week picker instead. */
function week_month_label(DateTimeImmutable $start, DateTimeImmutable $end): string
{
    $startLabel = CZECH_MONTHS[(int) $start->format('n')];
    if ($start->format('Y-n') === $end->format('Y-n')) {
        return $startLabel;
    }
    $endLabel = CZECH_MONTHS[(int) $end->format('n')];
    return "$startLabel – $endLabel";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $week = resolve_viewed_week((string) ($_POST['week'] ?? ''));
    $postView = resolve_view((string) ($_POST['view'] ?? ''));
    $weekDates = week_day_dates($week);
    $redirectTo = '/obedy.php?week=' . $week . '&view=' . $postView;

    // Ownership (child_belongs_to) is what actually gates this, not
    // role — an admin/teacher who happens to be a guardian of their own
    // child uses the exact same action a parent does.
    if ($action === 'save_lunch') {
        $childId = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to($childId, (int) $user['id'])) {
            foreach ($weekDates as $day => $date) {
                $menuText = menu_for_date($date);
                // No menu set for that day yet — nothing to opt into,
                // regardless of what was submitted.
                $wantsLunch = $menuText !== null && !empty($_POST['lunch_' . $day]);
                save_lunch_selection($childId, $week, $day, $wantsLunch, $menuText ?? 'Jídelníček zatím nebyl nastaven.');
            }
            flash_set('success', 'Výběr obědů byl uložen.');
        }
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($action === 'save_staff_lunch' && $canEditMenu) {
        foreach ($weekDates as $day => $date) {
            $menuText = menu_for_date($date);
            $wantsLunch = $menuText !== null && !empty($_POST['lunch_' . $day]);
            save_staff_lunch_selection((int) $user['id'], $week, $day, $wantsLunch, $menuText ?? 'Jídelníček zatím nebyl nastaven.');
        }
        flash_set('success', 'Výběr obědů byl uložen.');
        header('Location: ' . $redirectTo);
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
        header('Location: ' . $redirectTo);
        exit;
    }
}

$children = children_for_parent((int) $user['id']);
$viewedWeek = resolve_viewed_week((string) ($_GET['week'] ?? ''));
$view = $canEditMenu ? resolve_view((string) ($_GET['view'] ?? '')) : 'vyber';
$weekDates = week_day_dates($viewedWeek);
$weekMenus = menus_for_week($viewedWeek);
$weekStartDt = new DateTimeImmutable($viewedWeek);
$weekEndDt = $weekStartDt->modify('+4 days');
$weekPickerValue = $weekStartDt->format('o') . '-W' . $weekStartDt->format('W');
$monthLabel = week_month_label($weekStartDt, $weekEndDt);
$prevWeek = $weekStartDt->modify('-7 days')->format('Y-m-d');
$nextWeek = $weekStartDt->modify('+7 days')->format('Y-m-d');

if ($canEditMenu && $view === 'prehled') {
    $weekOverview = lunch_orders_overview($viewedWeek);
    $monthlyTotals = monthly_lunch_totals($viewedWeek);
    $overviewMonthLabel = CZECH_MONTHS[(int) $weekStartDt->format('n')] . ' ' . $weekStartDt->format('Y');
}

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
        <?php if ($canEditMenu): ?>
          <div class="obedy-tabs">
            <a href="/obedy.php?week=<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>&view=vyber" class="obedy-tab<?= $view === 'vyber' ? ' is-active' : '' ?>">Výběr obědů</a>
            <a href="/obedy.php?week=<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>&view=nastaveni" class="obedy-tab<?= $view === 'nastaveni' ? ' is-active' : '' ?>">Nastavení jídelníčku</a>
            <a href="/obedy.php?week=<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>&view=prehled" class="obedy-tab<?= $view === 'prehled' ? ' is-active' : '' ?>">Přehled obědů</a>
          </div>
        <?php endif; ?>

        <div class="week-nav">
          <a href="/obedy.php?week=<?= htmlspecialchars($prevWeek, ENT_QUOTES, 'UTF-8') ?>&view=<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>" class="page-btn" aria-label="Předchozí týden">‹</a>
          <div class="week-nav-center">
            <span class="week-nav-label"><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <form method="get" action="/obedy.php" class="week-nav-picker">
              <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">
              <input type="week" name="week" value="<?= htmlspecialchars($weekPickerValue, ENT_QUOTES, 'UTF-8') ?>" aria-label="Přejít na týden" data-week-picker>
            </form>
          </div>
          <a href="/obedy.php?week=<?= htmlspecialchars($nextWeek, ENT_QUOTES, 'UTF-8') ?>&view=<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>" class="page-btn" aria-label="Další týden">›</a>
        </div>

        <?php
          $bannerLabels = [
              'vyber' => '🍽️ Výběr obědů',
              'nastaveni' => '📋 Nastavení jídelníčku',
              'prehled' => '📊 Přehled obědů',
          ];
        ?>
        <div class="form-card news-board-header">
          <h3 class="mt-0 text-center news-board-title"><?= htmlspecialchars($bannerLabels[$view], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($weekStartDt->format('j. n.'), ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($weekEndDt->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></h3>
        </div>

        <?php if ($view === 'nastaveni' && $canEditMenu): ?>
          <div class="form-card">
            <form method="post" action="/obedy.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_menu">
              <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="view" value="nastaveni">
              <h4 class="lunch-child-name lunch-week-spacer" aria-hidden="true">&nbsp;</h4>
              <div class="lunch-week">
                <?php foreach (LUNCH_DAYS as $code => $label): ?>
                  <div class="lunch-day lunch-day--edit">
                    <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($weekDates[$code]))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                    <input type="text" name="menu_<?= $code ?>" class="lunch-day-meal-input" aria-label="Jídlo na <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($weekMenus[$weekDates[$code]] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Např. Polévka + kuřecí řízek s bramborovou kaší">
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="text-align:center; margin-top:14px;">
                <button type="submit" class="btn btn--primary">Uložit jídelníček</button>
              </div>
            </form>
          </div>

        <?php elseif ($view === 'prehled' && $canEditMenu): ?>
          <div class="form-card">
            <h3 class="mt-0">Přehled objednávek — tento týden</h3>
            <?php foreach ($weekOverview as $day): ?>
              <div class="overview-day">
                <h4>
                  <?= htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((new DateTimeImmutable($day['date']))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?>
                  <span class="overview-day-count"><?= count($day['orders']) ?> objednávek</span>
                </h4>
                <p class="hint-text"><?= $day['menu'] !== null ? htmlspecialchars($day['menu'], ENT_QUOTES, 'UTF-8') : 'Jídelníček nenastaven' ?></p>
                <?php if ($day['orders']): ?>
                  <p><?= htmlspecialchars(implode(', ', $day['orders']), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                  <p class="hint-text">Zatím nikdo neobjednal.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="form-card">
            <h3 class="mt-0">Měsíční přehled — <?= htmlspecialchars($overviewMonthLabel, ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="hint-text">Počet objednaných obědů za měsíc, ve kterém tento týden začíná — pro snazší vyúčtování rodičům.</p>
            <?php if (empty($monthlyTotals)): ?>
              <p class="hint-text">Zatím žádné objednávky.</p>
            <?php else: ?>
              <table class="overview-table">
                <thead><tr><th>Jméno</th><th>Počet obědů</th></tr></thead>
                <tbody>
                  <?php foreach ($monthlyTotals as $name => $count): ?>
                    <tr><td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $count ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

        <?php else: ?>
          <?php if ($canEditMenu): ?>
            <?php $staffSelections = staff_lunch_selections_for((int) $user['id'], $viewedWeek); ?>
            <div class="form-card">
              <form method="post" action="/obedy.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_staff_lunch">
                <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="view" value="vyber">
                <h4 class="lunch-child-name"><?= htmlspecialchars(full_name($user), ENT_QUOTES, 'UTF-8') ?> (vy)</h4>
                <div class="lunch-week">
                  <?php foreach (LUNCH_DAYS as $code => $label): ?>
                    <?php
                      $date = $weekDates[$code];
                      $hasMenu = isset($weekMenus[$date]);
                      $mealText = $weekMenus[$date] ?? 'Jídelníček zatím nebyl nastaven.';
                      $inputId = 'staff_lunch_' . $code;
                    ?>
                    <label class="lunch-day<?= isset($staffSelections[$code]) ? ' is-saved' : '' ?><?= $hasMenu ? '' : ' is-disabled' ?>" for="<?= $inputId ?>">
                      <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($date))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                      <span class="lunch-day-meal"><?= htmlspecialchars($mealText, ENT_QUOTES, 'UTF-8') ?></span>
                      <input type="checkbox" id="<?= $inputId ?>" name="lunch_<?= $code ?>" class="lunch-checkbox"<?= isset($staffSelections[$code]) ? ' checked' : '' ?><?= $hasMenu ? '' : ' disabled' ?>>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div style="text-align:center; margin-top:14px;">
                  <button type="submit" class="btn btn--primary">Uložit výběr</button>
                </div>
              </form>
            </div>
          <?php endif; ?>

          <?php if (empty($children) && !$canEditMenu): ?>
            <div class="form-card">
              <p class="hint-text">Zatím k vám není přiřazené žádné dítě. Kontaktujte prosím centrum.</p>
            </div>
          <?php endif; ?>

          <?php foreach ($children as $child): ?>
            <?php $selections = lunch_selections_for((int) $child['id'], $viewedWeek); ?>
            <div class="form-card">
              <form method="post" action="/obedy.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_lunch">
                <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="view" value="vyber">
                <input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>">
                <h4 class="lunch-child-name"><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="lunch-week">
                  <?php foreach (LUNCH_DAYS as $code => $label): ?>
                    <?php
                      $date = $weekDates[$code];
                      $hasMenu = isset($weekMenus[$date]);
                      $mealText = $weekMenus[$date] ?? 'Jídelníček zatím nebyl nastaven.';
                      $inputId = 'lunch_' . $code . '_' . (int) $child['id'];
                    ?>
                    <label class="lunch-day<?= isset($selections[$code]) ? ' is-saved' : '' ?><?= $hasMenu ? '' : ' is-disabled' ?>" for="<?= $inputId ?>">
                      <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($date))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                      <span class="lunch-day-meal"><?= htmlspecialchars($mealText, ENT_QUOTES, 'UTF-8') ?></span>
                      <input type="checkbox" id="<?= $inputId ?>" name="lunch_<?= $code ?>" class="lunch-checkbox"<?= isset($selections[$code]) ? ' checked' : '' ?><?= $hasMenu ? '' : ' disabled' ?>>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div style="text-align:center; margin-top:14px;">
                  <button type="submit" class="btn btn--primary">Uložit výběr</button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
