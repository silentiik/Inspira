<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_contact') {
        // Name, surname and gender are deliberately not read from
        // $_POST here at all — this form only ever shows them as plain
        // text, never as editable inputs, so there is nothing for a
        // tampered request to overwrite them with.
        $newEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
        $newPhone = trim((string) ($_POST['phone'] ?? ''));
        $newUsername = trim((string) ($_POST['username'] ?? ''));
        $confirmPassword = (string) ($_POST['password'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Zadejte prosím platný e-mail.');
        } elseif ($newPhone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $newPhone)) {
            flash_set('error', 'Zadejte prosím platné telefonní číslo.');
        } elseif ($newUsername !== '' && !preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $newUsername)) {
            flash_set('error', 'Uživatelské jméno smí mít 3–32 znaků: písmena, čísla, tečku, pomlčku nebo podtržítko.');
        } elseif (!password_verify($confirmPassword, $user['password_hash'])) {
            flash_set('error', 'Heslo pro potvrzení změn není správné.');
        } else {
            $emailTaken = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $emailTaken->execute([$newEmail, $user['id']]);
            // Usernames can never collide with an email anyway — the
            // format check above already forbids '@' — so this only
            // needs to check against other usernames.
            $usernameTaken = false;
            if ($newUsername !== '') {
                $stmt = db()->prepare('SELECT id FROM users WHERE id != ? AND LOWER(username) = LOWER(?)');
                $stmt->execute([$user['id'], $newUsername]);
                $usernameTaken = (bool) $stmt->fetch();
            }

            if ($emailTaken->fetch()) {
                flash_set('error', 'Tento e-mail už používá jiný účet.');
            } elseif ($usernameTaken) {
                flash_set('error', 'Toto uživatelské jméno už je obsazené.');
            } else {
                db()->prepare('UPDATE users SET email = ?, phone = ?, username = ? WHERE id = ?')
                    ->execute([$newEmail, $newPhone !== '' ? $newPhone : null, $newUsername !== '' ? $newUsername : null, $user['id']]);
                flash_set('success', 'Údaje byly uloženy.');
            }
        }
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (mb_strlen($newPassword) < 8) {
            flash_set('error', 'Nové heslo musí mít alespoň 8 znaků.');
        } elseif ($newPassword !== $confirmPassword) {
            flash_set('error', 'Nová hesla se neshodují.');
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            flash_set('error', 'Současné heslo není správné.');
        } else {
            set_password((int) $user['id'], $newPassword);
            flash_set('success', 'Heslo bylo změněno.');
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
      <h1>Nastavení účtu</h1>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="form-card" style="max-width:800px; margin:0 auto;">
        <h3 class="mt-0">Údaje</h3>
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

        <form method="post" action="/account-settings.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_contact">
          <div class="field">
            <label for="username">Uživatelské jméno pro přihlášení</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nenastaveno" maxlength="32">
            <span class="hint-text" style="display:block; margin-top:6px;">Nepovinný údaj. Pokud si ho nastavíte, půjde se do portálu přihlásit jím i heslem, místo e-mailem.</span>
          </div>
          <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="field">
            <label for="phone">Telefon</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8') ?>" placeholder="+420 123 456 789">
          </div>
          <div class="field">
            <label for="password">Heslo pro potvrzení změn</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="btn btn--primary">Uložit</button>
        </form>
      </div>

      <div class="form-card" style="max-width:800px; margin:24px auto 0;">
        <h3 class="mt-0">Změna hesla</h3>
        <form method="post" action="/account-settings.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="field">
            <label for="current_password">Současné heslo</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="new_password">Nové heslo</label>
              <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="field">
              <label for="confirm_password">Nové heslo znovu</label>
              <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            </div>
          </div>
          <button type="submit" class="btn btn--primary">Změnit heslo</button>
        </form>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
