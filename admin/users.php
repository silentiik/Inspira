<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/invites.php';
require_once __DIR__ . '/../app/children.php';

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
            $directPassword !== '' ? $directPassword : null
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
                    db()->prepare('UPDATE users SET name = ?, first_name = ?, last_name = ?, email = ? WHERE id = ?')
                        ->execute([$fullName, $firstName, $lastName, $newEmail, $targetId]);
                } else {
                    db()->prepare('UPDATE users SET name = ?, first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?')
                        ->execute([$fullName, $firstName, $lastName, $newEmail, $newRole, $targetId]);
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
            // Cascades (see app/db.php schema) also remove this
            // account's news posts, children, and lunch selections.
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
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
        $childParentId = (int) ($_POST['child_parent_id'] ?? 0);

        $parentCheck = db()->prepare('SELECT 1 FROM users WHERE id = ?');
        $parentCheck->execute([$childParentId]);
        $parentExists = (bool) $parentCheck->fetchColumn();

        if ($childFirstName === '' || $childLastName === '' || !in_array($childProgram, ['inspirka', 'domskolaci'], true)) {
            flash_set('error', 'Zadejte prosím jméno, příjmení a program dítěte.');
        } elseif ($childDob !== '' && !DateTime::createFromFormat('Y-m-d', $childDob)) {
            flash_set('error', 'Zadejte prosím platné datum narození.');
        } elseif (!$parentExists) {
            flash_set('error', 'Vyberte prosím účet, ke kterému dítě patří.');
        } elseif ($action === 'add_child') {
            add_child($childParentId, $childFirstName, $childLastName, $childProgram, $childDob ?: null);
            flash_set('success', 'Dítě bylo přidáno.');
        } else {
            $childId = (int) ($_POST['child_id'] ?? 0);
            update_child($childId, $childParentId, $childFirstName, $childLastName, $childProgram, $childDob ?: null);
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

$allUsers = db()->query('SELECT * FROM users ORDER BY role, first_name, last_name')->fetchAll();
$allChildren = all_children_with_parent();

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
                <label for="child_parent_id">Patří k účtu</label>
                <select id="child_parent_id" name="child_parent_id" required>
                  <option value="">Vyberte</option>
                  <?php foreach ($allUsers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars(full_name($u), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(match ($u['role']) {
                      'admin' => 'administrátor',
                      'teacher' => 'učitel/ka',
                      default => 'rodič',
                    }, ENT_QUOTES, 'UTF-8') ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn--primary">Přidat dítě</button>
            </form>
          </div>
        </details>
      </div>

      <h2>Všechny účty</h2>
      <div class="user-list">
        <?php foreach ($allUsers as $row): $isSelf = (int) $row['id'] === (int) $user['id']; ?>
          <details class="user-row" name="user-edit">
            <summary class="user-summary">
              <div class="portal-avatar"><?= htmlspecialchars(initials($row), ENT_QUOTES, 'UTF-8') ?></div>
              <span class="user-summary-name"><?= htmlspecialchars(full_name($row), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="role-badge"><?= htmlspecialchars(match ($row['role']) {
                'admin' => 'Administrátor',
                'teacher' => 'Učitel/ka',
                default => 'Rodič',
              }, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="user-summary-meta"><?= $row['is_active'] ? 'Aktivní' : 'Deaktivovaný' ?><?= $isSelf ? ' · toto jste vy' : '' ?></span>
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
      <div class="user-list">
        <?php if (empty($allChildren)): ?>
          <p class="hint-text">Zatím nejsou přidané žádné děti.</p>
        <?php endif; ?>
        <?php foreach ($allChildren as $child): ?>
          <details class="user-row" name="child-edit">
            <summary class="user-summary">
              <div class="portal-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($child['first_name'], 0, 1) . mb_substr($child['last_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
              <span class="user-summary-name"><?= htmlspecialchars(full_child_name($child), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="role-badge"><?= htmlspecialchars(CHILD_PROGRAMS[$child['program']] ?? $child['program'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="user-summary-meta">
                <?= $child['date_of_birth'] ? htmlspecialchars(date('j. n. Y', strtotime($child['date_of_birth'])), ENT_QUOTES, 'UTF-8') . ' · ' : '' ?>
                <?= htmlspecialchars($child['parent_name'] ?: '(bez účtu)', ENT_QUOTES, 'UTF-8') ?>
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
                  <label for="child_parent_id-<?= (int) $child['id'] ?>">Patří k účtu</label>
                  <select id="child_parent_id-<?= (int) $child['id'] ?>" name="child_parent_id" required>
                    <?php foreach ($allUsers as $u): ?>
                      <option value="<?= (int) $u['id'] ?>"<?= (int) $child['parent_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= htmlspecialchars(full_name($u), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(match ($u['role']) {
                        'admin' => 'administrátor',
                        'teacher' => 'učitel/ka',
                        default => 'rodič',
                      }, ENT_QUOTES, 'UTF-8') ?>)</option>
                    <?php endforeach; ?>
                  </select>
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
