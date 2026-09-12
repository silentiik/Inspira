<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../app/config.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($email !== '') {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            send_password_reset_email($user);
        }
        // Same outcome whether or not the email exists — never reveal
        // which addresses have an account.
        $submitted = true;
    }
}

$pageTitle = 'Zapomenuté heslo | INSPIRA';
require_once __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Zapomenuté heslo</h1>
      <p class="lead">Zadejte e-mail, na který jste dostali přístup, a pošleme vám odkaz pro nastavení nového hesla.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="portal-login is-active">
        <div class="form-card">
          <?php if ($submitted): ?>
            <div class="alert alert--success is-visible">Pokud tento e-mail v systému existuje, poslali jsme na něj odkaz pro obnovení hesla. Zkontrolujte prosím i složku Spam.</div>
          <?php else: ?>
            <form method="post" action="/auth/forgot-password.php">
              <?= csrf_field() ?>
              <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autofocus>
              </div>
              <button type="submit" class="btn btn--primary btn--block">Poslat odkaz pro obnovení hesla</button>
            </form>
          <?php endif; ?>
          <p class="hint-text" style="margin-top:16px;"><a href="/auth/login.php">← Zpět na přihlášení</a></p>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
