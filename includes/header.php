<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/flash.php';

/**
 * Expected optional variables from the including page:
 *   $pageTitle       <title> text (falls back to a default)
 *   $pageDescription meta description
 *   $activeNav       one of: home, sluzby, rozvrh, cenik, o-nas, kontakt
 */
$pageTitle = $pageTitle ?? 'INSPIRA — Vzdělávací centrum Mladá Boleslav';
$pageDescription = $pageDescription ?? 'Vzdělávací centrum INSPIRA v Mladé Boleslavi.';
$activeNav = $activeNav ?? '';
$user = current_user();

function nav_class(string $key, string $active): string
{
    return $key === $active ? ' class="is-active"' : '';
}

/** "Petra" + "Nováková" -> "PN". */
function initials(array $user): string
{
    $letters = mb_strtoupper(mb_substr($user['first_name'] ?? '', 0, 1)) . mb_strtoupper(mb_substr($user['last_name'] ?? '', 0, 1));
    return $letters !== '' ? $letters : '?';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="container nav">
    <a href="/index.php" class="brand">
      <span class="brand-mark">✦</span>
      <span>INSPIRA<span class="brand-sub">Vzdělávací centrum</span></span>
    </a>
    <button class="nav-toggle" aria-label="Otevřít menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-links">
      <li><a href="/index.php"<?= nav_class('home', $activeNav) ?>>Domů</a></li>
      <li class="has-dropdown">
        <a href="#sluzby">Služby ▾</a>
        <ul class="dropdown">
          <li><a href="/sluzby/inspirka.php">Dětský klub INSPIRKA</a></li>
          <li><a href="/sluzby/domskolacka-akademie.php">Domškolácká akademie</a></li>
          <li><a href="/sluzby/volnocasove-aktivity.php">Volnočasové aktivity</a></li>
        </ul>
      </li>
      <li><a href="/rozvrh.php"<?= nav_class('rozvrh', $activeNav) ?>>Rozvrh</a></li>
      <li><a href="/cenik.php"<?= nav_class('cenik', $activeNav) ?>>Ceník</a></li>
      <li><a href="/o-nas.php"<?= nav_class('o-nas', $activeNav) ?>>O nás</a></li>
      <li><a href="/kontakt.php"<?= nav_class('kontakt', $activeNav) ?>>Kontakt</a></li>
      <?php if ($user): ?>
        <li class="nav-divider" aria-hidden="true"></li>
        <li class="has-dropdown nav-account">
          <a href="#account" class="nav-account-trigger">
            <span class="nav-avatar"><?= htmlspecialchars(initials($user), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="nav-account-name"><?= htmlspecialchars(full_name($user), ENT_QUOTES, 'UTF-8') ?></span>
            <span aria-hidden="true">▾</span>
          </a>
          <ul class="dropdown">
            <li><a href="/dashboard.php"<?= nav_class('dashboard', $activeNav) ?>>Nástěnka</a></li>
            <li><a href="/auth/logout.php">Odhlásit</a></li>
          </ul>
        </li>
      <?php else: ?>
        <li><a href="/auth/login.php"<?= nav_class('login', $activeNav) ?>>Přihlásit se</a></li>
      <?php endif; ?>
    </ul>
  </div>
</header>

<main>
<?= render_flash() ?>
