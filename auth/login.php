<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $errors[] = 'Vyplňte prosím e-mail (nebo uživatelské jméno) i heslo.';
    } else {
        $result = attempt_login($identifier, $password);
        if (is_array($result)) {
            log_in_user((int) $result['id']);
            header('Location: /dashboard.php');
            exit;
        }
        $errors[] = match ($result) {
            'locked' => 'Účet je dočasně uzamčen po několika neúspěšných pokusech. Zkuste to prosím za 15 minut, nebo si obnovte heslo.',
            'inactive' => 'Tento účet je deaktivovaný. Kontaktujte prosím centrum.',
            default => 'Nesprávný e-mail, uživatelské jméno nebo heslo.',
        };
    }
}

$pageTitle = 'Přihlášení | INSPIRA';
$activeNav = 'login';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Přihlášení</h1>
      <p class="lead">Přihlaste se do portálu pro rodiče a lektory.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="portal-login is-active">
        <div class="form-card">
          <?php foreach ($errors as $error): ?>
            <div class="alert alert--error is-visible"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endforeach; ?>

          <form method="post" action="/auth/login.php">
            <?= csrf_field() ?>
            <div class="field">
              <label for="identifier">E-mail nebo uživatelské jméno</label>
              <input type="text" id="identifier" name="identifier" required autofocus>
            </div>
            <div class="field">
              <label for="password">Heslo</label>
              <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Přihlásit se</button>
          </form>

          <p class="hint-text" style="margin-top:16px;"><a href="/auth/forgot-password.php">Zapomněli jste heslo?</a></p>
          <p class="hint-text">Nemáte ještě účet? Rodiče a lektoři dostávají pozvánku od centra — ozvěte se nám na <a href="/kontakt.php">kontaktním formuláři</a>.</p>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
