<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';
require_once __DIR__ . '/app/children.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_child' && $user['role'] === 'parent') {
        $firstName = trim((string) ($_POST['child_first_name'] ?? ''));
        $lastName = trim((string) ($_POST['child_last_name'] ?? ''));
        $program = (string) ($_POST['child_program'] ?? '');
        $dateOfBirth = (string) ($_POST['child_date_of_birth'] ?? '');
        if ($firstName !== '' && $lastName !== '' && in_array($program, ['inspirka', 'domskolaci'], true)) {
            add_child([(int) $user['id']], $firstName, $lastName, $program, $dateOfBirth ?: null);
            flash_set('success', 'Dítě bylo přidáno.');
        } else {
            flash_set('error', 'Zadejte prosím jméno, příjmení a program dítěte.');
        }
        header('Location: /obedy.php');
        exit;
    }

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
            <p class="hint-text"><?= $user['role'] === 'parent' ? 'Nejprve přidejte dítě, abyste mohli vybírat obědy.' : 'Žádné děti k zobrazení.' ?></p>
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

          <?php if ($user['role'] === 'parent'): ?>
            <h4>Přidat dítě</h4>
            <form method="post" action="/obedy.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_child">
              <div class="field-row">
                <div class="field">
                  <label for="child_first_name">Jméno dítěte</label>
                  <input type="text" id="child_first_name" name="child_first_name" required>
                </div>
                <div class="field">
                  <label for="child_last_name">Příjmení dítěte</label>
                  <input type="text" id="child_last_name" name="child_last_name" required>
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="child_date_of_birth">Datum narození</label>
                  <input type="date" id="child_date_of_birth" name="child_date_of_birth">
                </div>
                <div class="field">
                  <label for="child_program">Program</label>
                  <select id="child_program" name="child_program" required>
                    <option value="inspirka">INSPIRKA</option>
                    <option value="domskolaci">Domškolácká akademie</option>
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn--outline">Přidat dítě</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
