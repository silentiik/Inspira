<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/invites.php';

$user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'invite') {
        $result = invite_user(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['role'] ?? '')
        );
        $linkNote = $result['link'] ? ' Odkaz pro nastavení hesla: ' . $result['link'] : '';
        flash_set(
            $result['status'] === 'ok' ? 'success' : 'error',
            match ($result['status']) {
                'ok' => 'Pozvánka byla odeslána.' . $linkNote,
                'exists' => 'Tento e-mail už má vytvořený účet.',
                'mail_failed' => 'Účet byl vytvořen, ale e-mail se nepodařilo odeslat.' . $linkNote,
                default => 'Zkontrolujte prosím zadané údaje.',
            }
        );
    }

    if ($action === 'update_user') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newName = trim((string) ($_POST['name'] ?? ''));
        $newEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $newRole = (string) ($_POST['role'] ?? '');
        $isSelf = $targetId === (int) $user['id'];

        if ($newName === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Zadejte prosím platné jméno a e-mail.');
        } elseif (!$isSelf && !in_array($newRole, ['admin', 'teacher', 'parent'], true)) {
            flash_set('error', 'Neplatná role.');
        } else {
            $existing = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $existing->execute([$newEmail, $targetId]);
            if ($existing->fetch()) {
                flash_set('error', 'Tento e-mail už používá jiný účet.');
            } else {
                if ($isSelf) {
                    // Role is intentionally left out here — changing your
                    // own role could lock you out of this very page.
                    db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                        ->execute([$newName, $newEmail, $targetId]);
                } else {
                    db()->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?')
                        ->execute([$newName, $newEmail, $newRole, $targetId]);
                }
                flash_set('success', 'Účet byl uložen.');
            }
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

    header('Location: /admin/users.php');
    exit;
}

$allUsers = db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();

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
      <div class="form-card" style="margin-bottom:28px;">
        <h3 class="mt-0">Pozvat nový účet</h3>
        <form method="post" action="/admin/users.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="invite">
          <div class="field-row">
            <div class="field">
              <label for="name">Jméno</label>
              <input type="text" id="name" name="name" required>
            </div>
            <div class="field">
              <label for="email">E-mail</label>
              <input type="email" id="email" name="email" required>
            </div>
          </div>
          <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
              <option value="parent">Rodič</option>
              <option value="teacher">Učitel/ka</option>
              <option value="admin">Administrátor</option>
            </select>
          </div>
          <button type="submit" class="btn btn--primary">Odeslat pozvánku</button>
        </form>
      </div>

      <h2>Všechny účty</h2>
      <div class="stack">
        <?php foreach ($allUsers as $row): $isSelf = (int) $row['id'] === (int) $user['id']; ?>
          <div class="form-card">
            <div class="portal-topbar" style="margin-bottom:16px;">
              <div class="portal-user">
                <div class="portal-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($row['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                <div>
                  <span class="role-badge"><?= htmlspecialchars(match ($row['role']) {
                    'admin' => 'Administrátor',
                    'teacher' => 'Učitel/ka',
                    default => 'Rodič',
                  }, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="hint-text"><?= $row['is_active'] ? 'Aktivní' : 'Deaktivovaný' ?><?= $isSelf ? ' · toto jste vy' : '' ?></span>
                </div>
              </div>
              <?php if (!$isSelf): ?>
                <form method="post" action="/admin/users.php" onsubmit="return confirm('Opravdu změnit stav tohoto účtu?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                  <button type="submit" class="btn btn--outline btn--sm"><?= $row['is_active'] ? 'Deaktivovat' : 'Aktivovat' ?></button>
                </form>
              <?php endif; ?>
            </div>

            <form method="post" action="/admin/users.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_user">
              <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
              <div class="field-row">
                <div class="field">
                  <label for="name-<?= (int) $row['id'] ?>">Jméno</label>
                  <input type="text" id="name-<?= (int) $row['id'] ?>" name="name" value="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
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
              <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
