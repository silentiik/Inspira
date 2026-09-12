<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_account') {
        // Name, surname and gender are deliberately not read from
        // $_POST here at all — this form only ever shows them as plain
        // text, never as editable inputs, so there is nothing for a
        // tampered request to overwrite them with.
        $newEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $newPhone = trim((string) ($_POST['phone'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $wantsPasswordChange = $newPassword !== '' || $confirmPassword !== '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Zadejte prosím platný e-mail.');
        } elseif ($newPhone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $newPhone)) {
            flash_set('error', 'Zadejte prosím platné telefonní číslo.');
        } elseif ($wantsPasswordChange && mb_strlen($newPassword) < 8) {
            flash_set('error', 'Nové heslo musí mít alespoň 8 znaků.');
        } elseif ($wantsPasswordChange && $newPassword !== $confirmPassword) {
            flash_set('error', 'Nová hesla se neshodují.');
        } elseif ($wantsPasswordChange && !password_verify($currentPassword, $user['password_hash'])) {
            flash_set('error', 'Současné heslo není správné.');
        } else {
            $existing = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $existing->execute([$newEmail, $user['id']]);
            if ($existing->fetch()) {
                flash_set('error', 'Tento e-mail už používá jiný účet.');
            } else {
                db()->prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?')
                    ->execute([$newEmail, $newPhone !== '' ? $newPhone : null, $user['id']]);
                if ($wantsPasswordChange) {
                    set_password((int) $user['id'], $newPassword);
                }
                flash_set('success', 'Údaje byly uloženy.' . ($wantsPasswordChange ? ' Heslo bylo změněno.' : ''));
            }
        }
    }

    header('Location: /account-settings.php');
    exit;
}

$pageTitle = 'Nastavení účtu | INSPIRA';
require_once __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/dashboard.php">Nástěnka</a> / Nastavení účtu</p>
      <h1>Nastavení účtu</h1>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="form-card" style="max-width:640px;">
        <div class="field-row">
          <div class="field">
            <label>Jméno</label>
            <p><?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <div class="field">
            <label>Příjmení</label>
            <p><?= htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
        <div class="field">
          <label>Pohlaví</label>
          <p><?= htmlspecialchars(match ($user['gender']) {
            'male' => 'Muž',
            'female' => 'Žena',
            default => 'Nevyplněno',
          }, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <span class="hint-text">Jméno, příjmení a pohlaví může změnit jen administrátor.</span>

        <form method="post" action="/account-settings.php" style="margin-top:24px;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_account">
          <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="field">
            <label for="phone">Telefon (nepovinné)</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8') ?>" placeholder="+420 123 456 789">
          </div>

          <h3>Změna hesla</h3>
          <p class="hint-text">Vyplňte pouze v případě, že chcete heslo změnit.</p>
          <div class="field">
            <label for="current_password">Současné heslo</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password">
          </div>
          <div class="field-row">
            <div class="field">
              <label for="new_password">Nové heslo</label>
              <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
              <label for="confirm_password">Nové heslo znovu</label>
              <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password">
            </div>
          </div>

          <button type="submit" class="btn btn--primary">Uložit</button>
        </form>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
