<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';
require_once __DIR__ . '/app/news.php';
require_once __DIR__ . '/app/children.php';
require_once __DIR__ . '/app/invites.php';

$user = require_login();
$canPost = in_array($user['role'], ['admin', 'teacher'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'post_news' && $canPost) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $pinned = !empty($_POST['pinned']);
        if ($title !== '' && $body !== '') {
            create_news((int) $user['id'], $title, $body, $pinned);
            flash_set('success', 'Novinka byla zveřejněna.');
        }
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'delete_news' && $canPost) {
        delete_news((int) ($_POST['news_id'] ?? 0));
        flash_set('success', 'Novinka byla smazána.');
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'invite_parent' && $canPost) {
        $result = invite_user(
            (string) ($_POST['invite_email'] ?? ''),
            (string) ($_POST['invite_first_name'] ?? ''),
            (string) ($_POST['invite_last_name'] ?? ''),
            'parent'
        );
        $linkNote = $result['link'] ? ' Odkaz pro nastavení hesla: ' . $result['link'] : '';
        flash_set(
            $result['status'] === 'ok' ? 'success' : 'error',
            match ($result['status']) {
                'ok' => 'Pozvánka byla odeslána na e-mail rodiče.' . $linkNote,
                'exists' => 'Tento e-mail už má vytvořený účet.',
                'mail_failed' => 'Účet byl vytvořen, ale e-mail se nepodařilo odeslat.' . $linkNote,
                default => 'Zkontrolujte prosím jméno a e-mail.',
            }
        );
        header('Location: /dashboard.php');
        exit;
    }

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
        header('Location: /dashboard.php');
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
        header('Location: /dashboard.php');
        exit;
    }
}

$newsItems = all_news();
$children = $user['role'] === 'parent' ? children_for_parent((int) $user['id']) : [];
$nextWeek = week_start('next monday');

$pageTitle = 'Nástěnka | INSPIRA';
require_once __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Vítejte, <?= htmlspecialchars(full_name($user), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="lead">
        <span class="role-badge"><?= htmlspecialchars(match ($user['role']) {
          'admin' => 'Administrátor',
          'teacher' => 'Učitel/ka',
          default => 'Rodič',
        }, ENT_QUOTES, 'UTF-8') ?></span>
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">

      <?php if ($user['role'] === 'parent'): ?>
      <div class="portal-grid">
      <?php endif; ?>
        <div class="stack">
          <div class="form-card">
            <h3 class="mt-0">📋 Nástěnka — novinky z centra</h3>
            <?php if (empty($newsItems)): ?>
              <p class="hint-text">Zatím tu nejsou žádné novinky.</p>
            <?php endif; ?>
            <?php foreach ($newsItems as $item): ?>
              <div class="news-item<?= $item['pinned'] ? ' is-pinned' : '' ?>">
                <h4><?= $item['pinned'] ? '📌 ' : '' ?><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="news-meta"><?= htmlspecialchars($item['author_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('j. n. Y', strtotime($item['created_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                <p style="margin:0;"><?= nl2br(htmlspecialchars($item['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php if ($canPost): ?>
                  <form method="post" action="/dashboard.php" style="margin-top:8px;" data-confirm="Opravdu smazat tuto novinku?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_news">
                    <input type="hidden" name="news_id" value="<?= (int) $item['id'] ?>">
                    <button type="submit" class="btn btn--outline btn--sm">Smazat</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($canPost): ?>
            <div class="form-card">
              <h3 class="mt-0">Přidat novinku</h3>
              <form method="post" action="/dashboard.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="post_news">
                <div class="field">
                  <label for="title">Titulek</label>
                  <input type="text" id="title" name="title" required>
                </div>
                <div class="field">
                  <label for="body">Text</label>
                  <textarea id="body" name="body" required></textarea>
                </div>
                <div class="field checkbox-field">
                  <input type="checkbox" id="pinned" name="pinned">
                  <label for="pinned" style="margin:0;">Připnout nahoru</label>
                </div>
                <button type="submit" class="btn btn--accent">Zveřejnit novinku</button>
              </form>
            </div>

            <div class="form-card">
              <h3 class="mt-0">Pozvat rodiče do portálu</h3>
              <p class="hint-text">Vytvoří rodiči účet a pošle mu e-mail s odkazem pro nastavení hesla.</p>
              <form method="post" action="/dashboard.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="invite_parent">
                <div class="field-row">
                  <div class="field">
                    <label for="invite_first_name">Jméno rodiče</label>
                    <input type="text" id="invite_first_name" name="invite_first_name" required>
                  </div>
                  <div class="field">
                    <label for="invite_last_name">Příjmení rodiče</label>
                    <input type="text" id="invite_last_name" name="invite_last_name" required>
                  </div>
                </div>
                <div class="field">
                  <label for="invite_email">E-mail rodiče</label>
                  <input type="email" id="invite_email" name="invite_email" required>
                </div>
                <button type="submit" class="btn btn--primary">Odeslat pozvánku</button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($user['role'] === 'parent'): ?>
          <div class="form-card">
            <h3 class="mt-0">🍽️ Výběr obědů na týden od <?= htmlspecialchars(date('j. n. Y', strtotime($nextWeek)), ENT_QUOTES, 'UTF-8') ?></h3>

            <?php if (empty($children)): ?>
              <p class="hint-text">Nejprve přidejte dítě, abyste mohli vybírat obědy.</p>
            <?php endif; ?>

            <?php foreach ($children as $child): ?>
              <?php $selections = lunch_selections_for((int) $child['id'], $nextWeek); ?>
              <form method="post" action="/dashboard.php" style="margin-bottom:24px;">
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

            <h4>Přidat dítě</h4>
            <form method="post" action="/dashboard.php">
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
          </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
