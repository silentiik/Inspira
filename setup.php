<?php
/**
 * One-time initial setup: creates the first admin account. Refuses to
 * run once any user already exists, so it's safe to leave this file in
 * place — it self-disables after first use.
 */
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';

$userCount = (int) db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];

$errors = [];
$done = false;

if ($userCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadejte prosím jméno a platný e-mail.';
    } elseif (mb_strlen($password) < 8) {
        $errors[] = 'Heslo musí mít alespoň 8 znaků.';
    } else {
        $stmt = db()->prepare('INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, hash_password($password), $name, 'admin']);
        log_in_user((int) db()->lastInsertId());
        $done = true;
    }
}

$pageTitle = 'Počáteční nastavení | INSPIRA';
require __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Počáteční nastavení</h1>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="portal-login is-active">
        <div class="form-card">
          <?php if ($userCount > 0 && !$done): ?>
            <div class="alert alert--error is-visible">Počáteční nastavení už bylo dokončeno. Přihlaste se prosím normálně.</div>
            <a href="/auth/login.php" class="btn btn--primary btn--block" style="margin-top:12px;">Přihlásit se</a>
          <?php elseif ($done): ?>
            <div class="alert alert--success is-visible">Administrátorský účet byl vytvořen a jste přihlášeni.</div>
            <a href="/dashboard.php" class="btn btn--primary btn--block" style="margin-top:12px;">Pokračovat do portálu</a>
          <?php else: ?>
            <p class="hint-text">Tento krok proběhne jen jednou a vytvoří první administrátorský účet.</p>
            <?php foreach ($errors as $error): ?>
              <div class="alert alert--error is-visible"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
            <form method="post" action="/setup.php">
              <?= csrf_field() ?>
              <div class="field">
                <label for="name">Vaše jméno</label>
                <input type="text" id="name" name="name" required autofocus>
              </div>
              <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required>
              </div>
              <div class="field">
                <label for="password">Heslo</label>
                <input type="password" id="password" name="password" minlength="8" required>
              </div>
              <button type="submit" class="btn btn--primary btn--block">Vytvořit administrátorský účet</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
