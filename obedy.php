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

/** The month browsed on Prehled obedu ('Y-m'), independent of the viewed week — defaults to the current calendar month, not "next week"'s month. */
function resolve_viewed_month(string $requested): string
{
    return preg_match('/^\d{4}-\d{2}$/', $requested) ? $requested : (new DateTimeImmutable('now'))->format('Y-m');
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
    $weekAutoLocked = week_is_auto_locked($week);

    // Ownership (child_belongs_to) is what actually gates this, not
    // role — an admin/teacher who happens to be a guardian of their own
    // child uses the exact same action a parent does.
    if ($action === 'save_lunch') {
        $childId = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to($childId, (int) $user['id'])) {
            foreach ($weekDates as $day => $date) {
                if ($weekAutoLocked || is_day_locked($date)) {
                    continue; // frozen for billing — leave whatever was already saved
                }
                $menuText = menu_for_date($date);
                // No menu set for that day yet — nothing to opt into,
                // regardless of what was submitted.
                $wantsLunch = $menuText !== null && !empty($_POST['lunch_' . $day]);
                save_lunch_selection($childId, $week, $day, $wantsLunch, $menuText ?? 'Jídelníček není nastaven.');
            }
            flash_set('success', 'Výběr obědů byl uložen.');
        }
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($action === 'save_staff_lunch' && $canEditMenu) {
        foreach ($weekDates as $day => $date) {
            if ($weekAutoLocked || is_day_locked($date)) {
                continue;
            }
            $menuText = menu_for_date($date);
            $wantsLunch = $menuText !== null && !empty($_POST['lunch_' . $day]);
            save_staff_lunch_selection((int) $user['id'], $week, $day, $wantsLunch, $menuText ?? 'Jídelníček není nastaven.');
        }
        flash_set('success', 'Výběr obědů byl uložen.');
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($action === 'save_menu' && $canEditMenu) {
        // 'meal_change' means the admin/teacher confirmed this is a genuinely
        // different dish, not just a wording fix — the old choices no longer
        // apply. Decided per request in the browser (see data-menu-form in
        // main.js); re-verified here against the actual previous text rather
        // than trusting which days the client claims changed.
        $resetChoice = (string) ($_POST['reset_choice'] ?? '');
        foreach ($weekDates as $day => $date) {
            $text = trim((string) ($_POST['menu_' . $day] ?? ''));
            $previousText = menu_for_date($date);
            if ($text === '') {
                // The field was cleared — actually remove the day's menu
                // instead of silently keeping the old one in place.
                if ($previousText !== null) {
                    clear_day_menu($date);
                    if ($resetChoice === 'meal_change') {
                        reset_day_selections($week, $day);
                    }
                }
                continue;
            }
            save_menu_for_date($date, $text, (int) $user['id']);
            if ($resetChoice === 'meal_change' && $previousText !== null && $previousText !== $text) {
                reset_day_selections($week, $day);
            }
        }
        flash_set('success', 'Jídelníček byl uložen.');
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($action === 'clear_week_menu' && $canEditMenu && !$weekAutoLocked) {
        clear_week_menus($week);
        reset_week_selections($week);
        flash_set('success', 'Jídelníček pro tento týden byl vymazán.');
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($action === 'toggle_week_lock' && $canEditMenu && !$weekAutoLocked) {
        set_week_locked($week, !is_week_locked($week), (int) $user['id']);
        flash_set('success', 'Uzamčení týdne bylo změněno.');
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($action === 'toggle_day_lock' && $canEditMenu && !$weekAutoLocked) {
        $day = (string) ($_POST['day'] ?? '');
        if (isset($weekDates[$day])) {
            set_day_locked($weekDates[$day], !is_day_locked($weekDates[$day]), (int) $user['id']);
        }
        flash_set('success', 'Uzamčení dne bylo změněno.');
        header('Location: ' . $redirectTo);
        exit;
    }
}

$children = children_for_parent((int) $user['id']);
$viewedWeek = resolve_viewed_week((string) ($_GET['week'] ?? ''));
$view = $canEditMenu ? resolve_view((string) ($_GET['view'] ?? '')) : 'vyber';
$weekDates = week_day_dates($viewedWeek);
$weekMenus = menus_for_week($viewedWeek);
$lockedDays = locked_days_for_week($viewedWeek); // [date => bool] — used on Vyber obedu regardless of role
// Anything older than last week is frozen automatically, on top of any manual per-day/week lock.
$weekAutoLocked = week_is_auto_locked($viewedWeek);
$weekLocked = $canEditMenu && ($weekAutoLocked || is_week_locked($viewedWeek));
$weekStartDt = new DateTimeImmutable($viewedWeek);
$weekEndDt = $weekStartDt->modify('+4 days');
$weekPickerValue = $weekStartDt->format('o') . '-W' . $weekStartDt->format('W');
$monthLabel = week_month_label($weekStartDt, $weekEndDt);
$prevWeek = $weekStartDt->modify('-7 days')->format('Y-m-d');
$nextWeek = $weekStartDt->modify('+7 days')->format('Y-m-d');

if ($canEditMenu && $view === 'prehled') {
    $weekOverview = lunch_orders_overview($viewedWeek);

    $viewedMonth = resolve_viewed_month((string) ($_GET['month'] ?? ''));
    $monthStartDt = new DateTimeImmutable($viewedMonth . '-01');
    $prevMonth = $monthStartDt->modify('-1 month')->format('Y-m');
    $nextMonth = $monthStartDt->modify('+1 month')->format('Y-m');
    $overviewMonthLabel = CZECH_MONTHS[(int) $monthStartDt->format('n')] . ' ' . $monthStartDt->format('Y');
    $monthlyRoster = monthly_lunch_roster($viewedMonth . '-01');
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

        <?php if ($view === 'prehled' && $canEditMenu): ?>
          <div class="form-card">
            <h3 class="overview-banner">
              <span>Měsíční přehled</span>
              <span class="overview-banner-month-nav">
                <a href="/obedy.php?week=<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>&view=prehled&month=<?= htmlspecialchars($prevMonth, ENT_QUOTES, 'UTF-8') ?>" class="page-btn" aria-label="Předchozí měsíc">‹</a>
                <span><?= htmlspecialchars($overviewMonthLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <a href="/obedy.php?week=<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>&view=prehled&month=<?= htmlspecialchars($nextMonth, ENT_QUOTES, 'UTF-8') ?>" class="page-btn" aria-label="Další měsíc">›</a>
              </span>
            </h3>
            <div class="list-toolbar" data-overview-toolbar>
              <input type="text" class="list-search" placeholder="Hledat podle jména…" data-overview-search autocomplete="off">
              <div class="list-controls">
                <span class="list-count" data-overview-count></span>
                <div class="filter-chips" data-overview-filter>
                  <button type="button" class="filter-chip is-active" data-filter-value="">Vše</button>
                  <?php foreach (CHILD_PROGRAMS as $programKey => $programLabel): ?>
                    <button type="button" class="filter-chip" data-filter-value="<?= htmlspecialchars($programKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($programLabel, ENT_QUOTES, 'UTF-8') ?></button>
                  <?php endforeach; ?>
                  <button type="button" class="filter-chip" data-filter-value="teacher">Lektor/ka</button>
                </div>
              </div>
            </div>
            <table class="overview-table">
              <thead><tr><th>Jméno</th><th>Skupina</th><th>Počet obědů</th><th>Cena</th></tr></thead>
              <tbody>
                <?php $previousCategory = null; ?>
                <?php foreach ($monthlyRoster as $entry): ?>
                  <?php $isNewGroup = $previousCategory !== null && $entry['category'] !== $previousCategory; $previousCategory = $entry['category']; ?>
                  <tr<?= $isNewGroup ? ' class="overview-table-group-start"' : '' ?> data-overview-row data-name="<?= htmlspecialchars(mb_strtolower($entry['name']), ENT_QUOTES, 'UTF-8') ?>" data-display-name="<?= htmlspecialchars($entry['name'], ENT_QUOTES, 'UTF-8') ?>" data-filter="<?= htmlspecialchars($entry['group_key'], ENT_QUOTES, 'UTF-8') ?>" data-count="<?= (int) $entry['count'] ?>" data-amount="<?= (int) $entry['amount'] ?>" data-orders="<?= htmlspecialchars(json_encode($entry['orders'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($entry['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($entry['category'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $entry['count'] ?></td>
                    <td><?= number_format($entry['amount'], 0, ',', ' ') ?> Kč</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="overview-table-sum">
                  <td colspan="2">Celkem</td>
                  <td data-overview-sum><?= array_sum(array_column($monthlyRoster, 'count')) ?></td>
                  <td data-overview-sum-price><?= number_format(array_sum(array_column($monthlyRoster, 'amount')), 0, ',', ' ') ?> Kč</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="form-card">
            <h3 class="mt-0">Detail měsíce</h3>
            <div data-month-detail-body>
              <p class="hint-text">Není vybrán uživatel</p>
            </div>
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
            <?php foreach (LUNCH_DAYS as $code => $label): ?>
              <form method="post" action="/obedy.php" id="lock-day-<?= $code ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_day_lock">
                <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="view" value="nastaveni">
                <input type="hidden" name="day" value="<?= $code ?>">
              </form>
            <?php endforeach; ?>

            <div class="obedy-menu-header">
              <h4 class="lunch-child-name lunch-week-spacer" aria-hidden="true">&nbsp;</h4>
              <div class="obedy-menu-toolbar">
                <form method="post" action="/obedy.php" data-confirm="Opravdu vymazat celý jídelníček pro tento týden? Vybrané obědy dětí a zaměstnanců budou vyresetovány.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="clear_week_menu">
                  <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="view" value="nastaveni">
                  <button type="submit" class="icon-btn icon-btn--danger" aria-label="Vymazat jídelníček pro tento týden" title="<?= $weekAutoLocked ? 'Starší týdny jsou automaticky uzamčené a nelze je vymazat' : 'Vymazat jídelníček pro tento týden' ?>"<?= $weekAutoLocked ? ' disabled' : '' ?>>🗑️</button>
                </form>
                <form method="post" action="/obedy.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_week_lock">
                  <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="view" value="nastaveni">
                  <button type="submit" class="icon-btn icon-btn--lock<?= $weekLocked ? ' is-locked' : '' ?>" aria-label="<?= $weekLocked ? 'Odemknout celý týden' : 'Uzamknout celý týden' ?>" title="<?= $weekAutoLocked ? 'Starší týdny se uzamykají automaticky' : ($weekLocked ? 'Odemknout celý týden' : 'Uzamknout celý týden') ?>"<?= $weekAutoLocked ? ' disabled' : '' ?>><?= $weekLocked ? '🔒' : '🔓' ?></button>
                </form>
              </div>
            </div>

            <form method="post" action="/obedy.php" data-menu-form>
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_menu">
              <input type="hidden" name="week" value="<?= htmlspecialchars($viewedWeek, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="view" value="nastaveni">
              <div class="lunch-week">
                <?php foreach (LUNCH_DAYS as $code => $label): ?>
                  <?php $isLocked = $weekAutoLocked || ($lockedDays[$weekDates[$code]] ?? false); ?>
                  <div class="lunch-day lunch-day--edit has-menu<?= $isLocked ? ' is-locked' : '' ?>">
                    <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($weekDates[$code]))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                    <input type="text" name="menu_<?= $code ?>" class="lunch-day-meal-input" aria-label="Jídlo na <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($weekMenus[$weekDates[$code]] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Zadejte jídlo pro daný den">
                    <button type="submit" form="lock-day-<?= $code ?>" class="icon-btn icon-btn--lock icon-btn--sm<?= $isLocked ? ' is-locked' : '' ?>" aria-label="<?= $isLocked ? 'Odemknout ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : 'Uzamknout ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" title="<?= $weekAutoLocked ? 'Starší týdny se uzamykají automaticky' : ($isLocked ? 'Odemknout tento den' : 'Uzamknout tento den') ?>"<?= $weekAutoLocked ? ' disabled' : '' ?>><?= $isLocked ? '🔒' : '🔓' ?></button>
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
                      $isLocked = $weekAutoLocked || ($lockedDays[$date] ?? false);
                      $canChoose = $hasMenu && !$isLocked;
                      $mealText = $weekMenus[$date] ?? 'Jídelníček není nastaven.';
                      $inputId = 'staff_lunch_' . $code;
                    ?>
                    <label class="lunch-day<?= isset($staffSelections[$code]) ? ' is-saved' : '' ?><?= $canChoose ? ' has-menu' : ' is-disabled' ?><?= $isLocked ? ' is-locked' : '' ?>" for="<?= $inputId ?>">
                      <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($date))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                      <span class="lunch-day-meal"><?= htmlspecialchars($mealText, ENT_QUOTES, 'UTF-8') ?><?= $isLocked ? ' 🔒' : '' ?></span>
                      <input type="checkbox" id="<?= $inputId ?>" name="lunch_<?= $code ?>" class="lunch-checkbox"<?= isset($staffSelections[$code]) ? ' checked' : '' ?><?= $canChoose ? '' : ' disabled' ?>>
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
                      $isLocked = $weekAutoLocked || ($lockedDays[$date] ?? false);
                      $canChoose = $hasMenu && !$isLocked;
                      $mealText = $weekMenus[$date] ?? 'Jídelníček není nastaven.';
                      $inputId = 'lunch_' . $code . '_' . (int) $child['id'];
                    ?>
                    <label class="lunch-day<?= isset($selections[$code]) ? ' is-saved' : '' ?><?= $canChoose ? ' has-menu' : ' is-disabled' ?><?= $isLocked ? ' is-locked' : '' ?>" for="<?= $inputId ?>">
                      <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><span class="lunch-day-date"><?= htmlspecialchars((new DateTimeImmutable($date))->format('j. n.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                      <span class="lunch-day-meal"><?= htmlspecialchars($mealText, ENT_QUOTES, 'UTF-8') ?><?= $isLocked ? ' 🔒' : '' ?></span>
                      <input type="checkbox" id="<?= $inputId ?>" name="lunch_<?= $code ?>" class="lunch-checkbox"<?= isset($selections[$code]) ? ' checked' : '' ?><?= $canChoose ? '' : ' disabled' ?>>
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
