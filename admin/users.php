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

    if ($action === 'change_role') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newRole = (string) ($_POST['role'] ?? '');
        if ($targetId !== (int) $user['id'] && in_array($newRole, ['admin', 'teacher', 'parent'], true)) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
            flash_set('success', 'Role byla změněna.');
        } else {
            flash_set('error', 'Nemůžete změnit vlastní roli.');
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

      <div class="schedule-table-wrap">
        <table class="price-table" style="min-width:600px;">
          <thead><tr><th>Jméno</th><th>E-mail</th><th>Role</th><th>Stav</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($allUsers as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if ((int) $row['id'] === (int) $user['id']): ?>
                    <?= htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8') ?>
                  <?php else: ?>
                    <form method="post" action="/admin/users.php" style="display:flex; gap:6px; align-items:center;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="change_role">
                      <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                      <select name="role" onchange="this.form.submit()">
                        <option value="parent"<?= $row['role'] === 'parent' ? ' selected' : '' ?>>Rodič</option>
                        <option value="teacher"<?= $row['role'] === 'teacher' ? ' selected' : '' ?>>Učitel/ka</option>
                        <option value="admin"<?= $row['role'] === 'admin' ? ' selected' : '' ?>>Administrátor</option>
                      </select>
                    </form>
                  <?php endif; ?>
                </td>
                <td><?= $row['is_active'] ? 'Aktivní' : 'Deaktivovaný' ?></td>
                <td>
                  <?php if ((int) $row['id'] !== (int) $user['id']): ?>
                    <form method="post" action="/admin/users.php" onsubmit="return confirm('Opravdu změnit stav tohoto účtu?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                      <button type="submit" class="btn btn--outline btn--sm"><?= $row['is_active'] ? 'Deaktivovat' : 'Aktivovat' ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
