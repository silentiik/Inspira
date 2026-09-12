<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';

$rawToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenRow = null;
if ($rawToken !== '') {
    $tokenRow = find_valid_token($rawToken, 'reset') ?? find_valid_token($rawToken, 'invite');
}

$errors = [];
$done = false;

if ($tokenRow && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($password) < 8) {
        $errors[] = 'Heslo musí mít alespoň 8 znaků.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Hesla se neshodují.';
    } else {
        set_password((int) $tokenRow['user_id'], $password);
        consume_token((int) $tokenRow['id']);
        log_in_user((int) $tokenRow['user_id']);
        $done = true;
    }
}

$pageTitle = 'Nastavit heslo | INSPIRA';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Nastavit nové heslo</h1>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="portal-login is-active">
        <div class="form-card">
          <?php if (!$tokenRow && !$done): ?>
            <div class="alert alert--error is-visible">Odkaz je neplatný nebo už vypršel. Vyžádejte si prosím nový.</div>
            <p class="hint-text" style="margin-top:16px;"><a href="/auth/forgot-password.php">Vyžádat nový odkaz</a></p>
          <?php elseif ($done): ?>
            <div class="alert alert--success is-visible">Heslo bylo úspěšně nastaveno a jste přihlášeni.</div>
            <a href="/dashboard.php" class="btn btn--primary btn--block" style="margin-top:12px;">Pokračovat do portálu</a>
          <?php else: ?>
            <?php foreach ($errors as $error): ?>
              <div class="alert alert--error is-visible"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
            <form method="post" action="/auth/reset-password.php">
              <?= csrf_field() ?>
              <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') ?>">
              <div class="field">
                <label for="password">Nové heslo</label>
                <input type="password" id="password" name="password" minlength="8" required autofocus>
              </div>
              <div class="field">
                <label for="password_confirm">Nové heslo znovu</label>
                <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
              </div>
              <button type="submit" class="btn btn--primary btn--block">Nastavit heslo</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
