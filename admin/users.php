<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/invites.php';
require_once __DIR__ . '/../app/children.php';

/** "Špringl Hynek" — surname first, for lists sorted by surname. */
function surname_first(array $person): string
{
    return trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? ''));
}

/**
 * Collapsible, search-filterable checkbox list of every account a
 * child can be linked to. Always starts collapsed — the selected
 * count in the summary shows what's picked without needing to open it.
 *
 * @param int[] $selectedIds
 */
function render_guardian_checkboxes(array $allUsers, array $selectedIds, string $namePrefix): string
{
    $count = count($selectedIds);
    $html = '<details class="guardian-picker">';
    $html .= '<summary>Rodiče' . ($count > 0 ? ' <span class="guardian-picker-count">(' . $count . ' vybráno)</span>' : '') . '</summary>';
    $html .= '<div class="guardian-picker-body">';
    $html .= '<input type="text" class="guardian-search" placeholder="Hledat rodiče podle jména…" data-guardian-search autocomplete="off">';
    $html .= '<div class="checkbox-list">';
    foreach ($allUsers as $u) {
        $id = (int) $u['id'];
        $inputId = $namePrefix . '-' . $id;
        $roleLabel = match ($u['role']) {
            'admin' => 'administrátor',
            'teacher' => 'učitel/ka',
            default => 'rodič',
        };
        $html .= '<label class="checkbox-list-item" for="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="checkbox" id="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '" name="child_guardian_ids[]" value="' . $id . '"'
            . (in_array($id, $selectedIds, true) ? ' checked' : '') . '>'
            . htmlspecialchars(full_name($u), ENT_QUOTES, 'UTF-8') . ' <span class="hint-text">(' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . ')</span>'
            . '</label>';
    }
    $html .= '</div></div></details>';
    return $html;
}

$user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'invite') {
        $directPassword = trim((string) ($_POST['password'] ?? ''));
        $result = invite_user(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['first_name'] ?? ''),
            (string) ($_POST['last_name'] ?? ''),
            (string) ($_POST['role'] ?? ''),
            $directPassword !== '' ? $directPassword : null,
            (string) ($_POST['gender'] ?? '')
        );
        $linkNote = $result['link'] ? ' Odkaz pro nastavení hesla: ' . $result['link'] : '';
        flash_set(
            $result['status'] === 'ok' ? 'success' : 'error',
            match ($result['status']) {
                'ok' => $directPassword !== '' ? 'Účet byl vytvořen se zadaným heslem.' : 'Pozvánka byla odeslána.' . $linkNote,
                'exists' => 'Tento e-mail už má vytvořený účet.',
                'mail_failed' => 'Účet byl vytvořen, ale e-mail se nepodařilo odeslat.' . $linkNote,
                default => 'Zkontrolujte prosím zadané údaje (heslo musí mít alespoň 8 znaků).',
            }
        );
    }

    if ($action === 'update_user') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $newEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $newRole = (string) ($_POST['role'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newGender = (string) ($_POST['gender'] ?? '');
        $newGender = in_array($newGender, ['male', 'female'], true) ? $newGender : null;
        $isSelf = $targetId === (int) $user['id'];

        if ($firstName === '' || $lastName === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Zadejte prosím platné jméno, příjmení a e-mail.');
        } elseif (!$isSelf && !in_array($newRole, ['admin', 'teacher', 'parent'], true)) {
            flash_set('error', 'Neplatná role.');
        } elseif ($newPassword !== '' && mb_strlen($newPassword) < 8) {
            flash_set('error', 'Nové heslo musí mít alespoň 8 znaků.');
        } else {
            $existing = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $existing->execute([$newEmail, $targetId]);
            if ($existing->fetch()) {
                flash_set('error', 'Tento e-mail už používá jiný účet.');
            } else {
                $fullName = trim("$firstName $lastName");
                if ($isSelf) {
                    // Role is intentionally left out here — changing your
                    // own role could lock you out of this very page.
                    db()->prepare('UPDATE users SET name = ?, first_name = ?, last_name = ?, email = ?, gender = ? WHERE id = ?')
                        ->execute([$fullName, $firstName, $lastName, $newEmail, $newGender, $targetId]);
                } else {
                    db()->prepare('UPDATE users SET name = ?, first_name = ?, last_name = ?, email = ?, role = ?, gender = ? WHERE id = ?')
                        ->execute([$fullName, $firstName, $lastName, $newEmail, $newRole, $newGender, $targetId]);
                }
                if ($newPassword !== '') {
                    set_password($targetId, $newPassword);
                }
                flash_set('success', 'Účet byl uložen.' . ($newPassword !== '' ? ' Nové heslo bylo nastaveno.' : ''));
            }
        }
    }

    if ($action === 'send_reset') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if ($target) {
            $sent = send_password_reset_email($target);
            flash_set($sent ? 'success' : 'error', $sent
                ? 'Odkaz pro obnovení hesla byl odeslán na ' . $target['email'] . '.'
                : 'E-mail se nepodařilo odeslat.');
        }
    }

    if ($action === 'toggle_active') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId !== (int) $user['id']) {
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$targetId]);
            flash_set('success', 'Stav účtu byl změněn.');
        } else {
            flash_set('error', 'Nemůžete deaktivovat vlastní účet.');
        }
    }

    if ($action === 'delete_user') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId !== (int) $user['id']) {
            // A child left with no OTHER guardian becomes orphaned once
            // this account is gone — children have no direct link to
            // users any more (only via child_guardians), so nothing
            // cascades that automatically. Find those children first,
            // while the about-to-be-deleted guardian link still exists.
            $orphaned = db()->prepare(
                'SELECT child_id FROM child_guardians
                 WHERE user_id = ?
                 AND child_id NOT IN (SELECT child_id FROM child_guardians WHERE user_id != ?)'
            );
            $orphaned->execute([$targetId, $targetId]);
            $orphanedChildIds = $orphaned->fetchAll(PDO::FETCH_COLUMN);

            // Cascades (see app/db.php schema) also remove this
            // account's news posts and guardian links.
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);

            foreach ($orphanedChildIds as $childId) {
                delete_child((int) $childId);
            }

            flash_set('success', 'Účet byl trvale smazán.');
        } else {
            flash_set('error', 'Nemůžete smazat vlastní účet.');
        }
    }

    if ($action === 'add_child' || $action === 'update_child') {
        $childFirstName = trim((string) ($_POST['child_first_name'] ?? ''));
        $childLastName = trim((string) ($_POST['child_last_name'] ?? ''));
        $childProgram = (string) ($_POST['child_program'] ?? '');
        $childDob = trim((string) ($_POST['child_date_of_birth'] ?? ''));
        $childGender = (string) ($_POST['child_gender'] ?? '');
        $childGender = in_array($childGender, ['male', 'female'], true) ? $childGender : null;
        $guardianIds = array_map('intval', (array) ($_POST['child_guardian_ids'] ?? []));

        $validGuardianIds = [];
        if (!empty($guardianIds)) {
            $placeholders = implode(',', array_fill(0, count($guardianIds), '?'));
            $stmt = db()->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
            $stmt->execute($guardianIds);
            $validGuardianIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($childFirstName === '' || $childLastName === '' || !in_array($childProgram, ['inspirka', 'domskolaci'], true)) {
            flash_set('error', 'Zadejte prosím jméno, příjmení a program dítěte.');
        } elseif ($childDob !== '' && !DateTime::createFromFormat('Y-m-d', $childDob)) {
            flash_set('error', 'Zadejte prosím platné datum narození.');
        } elseif ($action === 'add_child') {
            add_child($validGuardianIds, $childFirstName, $childLastName, $childProgram, $childDob ?: null, $childGender);
            flash_set('success', 'Dítě bylo přidáno.' . (empty($validGuardianIds) ? ' Účet k němu můžete přiřadit později v jeho úpravě.' : ''));
        } else {
            $childId = (int) ($_POST['child_id'] ?? 0);
            update_child($childId, $validGuardianIds, $childFirstName, $childLastName, $childProgram, $childDob ?: null, $childGender);
            flash_set('success', 'Dítě bylo uloženo.');
        }
    }

    if ($action === 'delete_child') {
        delete_child((int) ($_POST['child_id'] ?? 0));
        flash_set('success', 'Dítě bylo smazáno.');
    }

    header('Location: /admin/users.php');
    exit;
}

$allUsers = db()->query('SELECT * FROM users ORDER BY last_name, first_name')->fetchAll();
$allChildren = all_children_with_parent();

// For the accounts list: which children (by name) are linked to each
// guardian account, built from $allChildren so it's one pass instead
// of a query per account.
$childrenByGuardian = [];
foreach ($allChildren as $child) {
    foreach ($child['guardian_ids'] as $guardianId) {
        $childrenByGuardian[$guardianId][] = full_child_name($child);
    }
}

$pageTitle = 'Uživatelé | INSPIRA';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/dashboard.php">Nástěnka</a> / Uživatelé</p>
      <h1>Uživatelé a role</h1>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="toggle-row">
        <details class="section-toggle">
          <summary><span class="toggle-icon" aria-hidden="true">+</span> Přidat nový účet</summary>
          <div class="form-card">
            <form method="post" action="/admin/users.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="invite">
              <div class="field-row">
                <div class="field">
                  <label for="first_name">Jméno</label>
                  <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="field">
                  <label for="last_name">Příjmení</label>
                  <input type="text" id="last_name" name="last_name" required>
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="email">E-mail</label>
                  <input type="email" id="email" name="email" required>
                </div>
                <div class="field">
                  <label for="role">Role</label>
                  <select id="role" name="role" required>
                    <option value="parent">Rodič</option>
                    <option value="teacher">Učitel/ka</option>
                    <option value="admin">Administrátor</option>
                  </select>
                </div>
              </div>
              <div class="field">
                <label for="gender">Pohlaví</label>
                <select id="gender" name="gender">
                  <option value="female">Žena</option>
                  <option value="male">Muž</option>
                </select>
              </div>
              <div class="field">
                <label for="password">Heslo (nepovinné)</label>
                <input type="password" id="password" name="password" minlength="8" placeholder="Ponechte prázdné pro pozvánku e-mailem">
                <span class="hint-text">Necháte-li pole prázdné, účet dostane e-mail s odkazem pro nastavení vlastního hesla. Vyplníte-li heslo, účet se vytvoří rovnou s ním a e-mail se neposílá.</span>
              </div>
              <button type="submit" class="btn btn--primary">Vytvořit účet</button>
            </form>
          </div>
        </details>

        <details class="section-toggle">
          <summary><span class="toggle-icon" aria-hidden="true">+</span> Přidat dítě</summary>
          <div class="form-card">
            <form method="post" action="/admin/users.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_child">
              <div class="field-row">
                <div class="field">
                  <label for="child_first_name">Jméno</label>
                  <input type="text" id="child_first_name" name="child_first_name" required>
                </div>
                <div class="field">
                  <label for="child_last_name">Příjmení</label>
                  <input type="text" id="child_last_name" name="child_last_name" required>
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="child_date_of_birth">Datum narození</label>
                  <input type="date" id="child_date_of_birth" name="child_date_of_birth">
                </div>
                <div class="field">
                  <label for="child_program">Kategorie</label>
                  <select id="child_program" name="child_program" required>
                    <?php foreach (CHILD_PROGRAMS as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="field">
                <label for="child_gender">Pohlaví</label>
                <select id="child_gender" name="child_gender">
                  <option value="female">Dívka</option>
                  <option value="male">Chlapec</option>
                </select>
              </div>
              <div class="field">
                <?= render_guardian_checkboxes($allUsers, [], 'new-child-guardian') ?>
              </div>
              <button type="submit" class="btn btn--primary">Přidat dítě</button>
            </form>
          </div>
        </details>
      </div>

      <h2>Všechny účty</h2>
      <div class="list-toolbar">
        <input type="text" class="list-search" placeholder="Hledat podle jména…" data-list-search autocomplete="off">
        <div class="list-controls">
          <span class="list-count" data-list-count></span>
          <div class="filter-chips" data-list-filter>
            <button type="button" class="filter-chip is-active" data-filter-value="">Vše</button>
            <button type="button" class="filter-chip" data-filter-value="parent">Rodič</button>
            <button type="button" class="filter-chip" data-filter-value="admin">Administrátor</button>
            <button type="button" class="filter-chip" data-filter-value="teacher">Učitel/ka</button>
          </div>
        </div>
      </div>
      <div class="user-list">
        <?php foreach ($allUsers as $row): $isSelf = (int) $row['id'] === (int) $user['id']; ?>
          <details class="user-row" name="user-edit" data-name="<?= htmlspecialchars(mb_strtolower(surname_first($row) . ' ' . full_name($row)), ENT_QUOTES, 'UTF-8') ?>" data-filter="<?= htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8') ?>">
            <summary class="user-summary">
              <div class="portal-avatar<?= $row['gender'] === 'male' ? ' portal-avatar--male' : '' ?>"><?= htmlspecialchars(initials($row), ENT_QUOTES, 'UTF-8') ?></div>
              <span class="user-summary-name"><?= htmlspecialchars(surname_first($row), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="role-badge"><?= htmlspecialchars(match ($row['role']) {
                'admin' => 'Administrátor',
                'teacher' => 'Učitel/ka',
                default => 'Rodič',
              }, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="user-summary-meta">
                <?= $row['is_active'] ? 'Aktivní' : 'Deaktivovaný' ?><?= $isSelf ? ' · toto jste vy' : '' ?>
                <?php if (!empty($childrenByGuardian[(int) $row['id']])): ?>
                  · <?= htmlspecialchars(implode(', ', $childrenByGuardian[(int) $row['id']]), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </span>
              <span class="user-summary-chevron" aria-hidden="true">▾</span>
            </summary>

            <div class="user-edit-body">
              <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;">
                <form method="post" action="/admin/users.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="send_reset">
                  <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                  <button type="submit" class="btn btn--outline btn--sm">Poslat odkaz pro obnovení hesla</button>
                </form>
                <?php if (!$isSelf): ?>
                  <form method="post" action="/admin/users.php" onsubmit="return confirm('Opravdu změnit stav tohoto účtu?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" class="btn btn--outline btn--sm"><?= $row['is_active'] ? 'Deaktivovat' : 'Aktivovat' ?></button>
                  </form>
                  <form method="post" action="/admin/users.php" onsubmit="return confirm('Opravdu trvale smazat účet <?= htmlspecialchars(addslashes(full_name($row)), ENT_QUOTES, 'UTF-8') ?>? Smažou se i všechny související záznamy (novinky, děti, výběry obědů). Tuto akci nelze vrátit zpět.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" class="btn btn--danger btn--sm">Smazat účet</button>
                  </form>
                <?php endif; ?>
              </div>

              <form method="post" action="/admin/users.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                <div class="field-row">
                  <div class="field">
                    <label for="first_name-<?= (int) $row['id'] ?>">Jméno</label>
                    <input type="text" id="first_name-<?= (int) $row['id'] ?>" name="first_name" value="<?= htmlspecialchars($row['first_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                  <div class="field">
                    <label for="last_name-<?= (int) $row['id'] ?>">Příjmení</label>
                    <input type="text" id="last_name-<?= (int) $row['id'] ?>" name="last_name" value="<?= htmlspecialchars($row['last_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                </div>
                <div class="field-row">
                  <div class="field">
                    <label for="email-<?= (int) $row['id'] ?>">E-mail</label>
                    <input type="email" id="email-<?= (int) $row['id'] ?>" name="email" value="<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                  <?php if (!$isSelf): ?>
                    <div class="field">
                      <label for="role-<?= (int) $row['id'] ?>">Role</label>
                      <select id="role-<?= (int) $row['id'] ?>" name="role">
                        <option value="parent"<?= $row['role'] === 'parent' ? ' selected' : '' ?>>Rodič</option>
                        <option value="teacher"<?= $row['role'] === 'teacher' ? ' selected' : '' ?>>Učitel/ka</option>
                        <option value="admin"<?= $row['role'] === 'admin' ? ' selected' : '' ?>>Administrátor</option>
                      </select>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="field">
                  <label for="gender-<?= (int) $row['id'] ?>">Pohlaví</label>
                  <select id="gender-<?= (int) $row['id'] ?>" name="gender">
                    <option value="female"<?= $row['gender'] !== 'male' ? ' selected' : '' ?>>Žena</option>
                    <option value="male"<?= $row['gender'] === 'male' ? ' selected' : '' ?>>Muž</option>
                  </select>
                </div>
                <div class="field">
                  <label for="new_password-<?= (int) $row['id'] ?>">Nastavit nové heslo (nepovinné)</label>
                  <input type="password" id="new_password-<?= (int) $row['id'] ?>" name="new_password" minlength="8" placeholder="Ponechte prázdné, pokud heslo neměnit">
                </div>
                <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>

      <h2>Děti</h2>
      <div class="list-toolbar">
        <input type="text" class="list-search" placeholder="Hledat podle jména…" data-list-search autocomplete="off">
        <div class="list-controls">
          <span class="list-count" data-list-count></span>
          <div class="filter-chips" data-list-filter>
            <button type="button" class="filter-chip is-active" data-filter-value="">Vše</button>
            <?php foreach (CHILD_PROGRAMS as $value => $label): ?>
              <button type="button" class="filter-chip" data-filter-value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="user-list">
        <?php if (empty($allChildren)): ?>
          <p class="hint-text">Zatím nejsou přidané žádné děti.</p>
        <?php endif; ?>
        <?php foreach ($allChildren as $child): ?>
          <details class="user-row" name="child-edit" data-name="<?= htmlspecialchars(mb_strtolower(surname_first($child) . ' ' . full_child_name($child)), ENT_QUOTES, 'UTF-8') ?>" data-filter="<?= htmlspecialchars($child['program'], ENT_QUOTES, 'UTF-8') ?>">
            <summary class="user-summary">
              <div class="portal-avatar<?= $child['gender'] === 'male' ? ' portal-avatar--male' : '' ?>"><?= htmlspecialchars(mb_strtoupper(mb_substr($child['first_name'], 0, 1) . mb_substr($child['last_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
              <span class="user-summary-name"><?= htmlspecialchars(surname_first($child), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="role-badge"><?= htmlspecialchars(CHILD_PROGRAMS[$child['program']] ?? $child['program'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="user-summary-meta">
                <?= $child['date_of_birth'] ? htmlspecialchars(date('j. n. Y', strtotime($child['date_of_birth'])), ENT_QUOTES, 'UTF-8') . ' · ' : '' ?>
                <?= htmlspecialchars(!empty($child['guardian_names']) ? implode(', ', $child['guardian_names']) : '(bez účtu)', ENT_QUOTES, 'UTF-8') ?>
              </span>
              <span class="user-summary-chevron" aria-hidden="true">▾</span>
            </summary>

            <div class="user-edit-body">
              <form method="post" action="/admin/users.php" style="margin-bottom:20px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_child">
                <input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>">
                <div class="field-row">
                  <div class="field">
                    <label for="child_first_name-<?= (int) $child['id'] ?>">Jméno</label>
                    <input type="text" id="child_first_name-<?= (int) $child['id'] ?>" name="child_first_name" value="<?= htmlspecialchars($child['first_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                  <div class="field">
                    <label for="child_last_name-<?= (int) $child['id'] ?>">Příjmení</label>
                    <input type="text" id="child_last_name-<?= (int) $child['id'] ?>" name="child_last_name" value="<?= htmlspecialchars($child['last_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                </div>
                <div class="field-row">
                  <div class="field">
                    <label for="child_date_of_birth-<?= (int) $child['id'] ?>">Datum narození</label>
                    <input type="date" id="child_date_of_birth-<?= (int) $child['id'] ?>" name="child_date_of_birth" value="<?= htmlspecialchars((string) $child['date_of_birth'], ENT_QUOTES, 'UTF-8') ?>">
                  </div>
                  <div class="field">
                    <label for="child_program-<?= (int) $child['id'] ?>">Kategorie</label>
                    <select id="child_program-<?= (int) $child['id'] ?>" name="child_program" required>
                      <?php foreach (CHILD_PROGRAMS as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $child['program'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="field">
                  <label for="child_gender-<?= (int) $child['id'] ?>">Pohlaví</label>
                  <select id="child_gender-<?= (int) $child['id'] ?>" name="child_gender">
                    <option value="female"<?= $child['gender'] !== 'male' ? ' selected' : '' ?>>Dívka</option>
                    <option value="male"<?= $child['gender'] === 'male' ? ' selected' : '' ?>>Chlapec</option>
                  </select>
                </div>
                <div class="field">
                  <?= render_guardian_checkboxes($allUsers, $child['guardian_ids'] ?? [], 'child-' . (int) $child['id'] . '-guardian') ?>
                </div>
                <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
              </form>

              <form method="post" action="/admin/users.php" onsubmit="return confirm('Opravdu trvale smazat dítě <?= htmlspecialchars(addslashes(full_child_name($child)), ENT_QUOTES, 'UTF-8') ?>? Smažou se i jeho výběry obědů. Tuto akci nelze vrátit zpět.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_child">
                <input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>">
                <button type="submit" class="btn btn--danger btn--sm">Smazat dítě</button>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
