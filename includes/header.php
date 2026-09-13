<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/csrf.php';

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

/**
 * Appends the file's last-modified time as a query string, so browsers
 * fetch a fresh copy whenever the file actually changes instead of
 * serving a stale cached version after a deploy.
 */
function asset_url(string $publicPath): string
{
    $diskPath = __DIR__ . '/..' . $publicPath;
    $version = @filemtime($diskPath);
    return $publicPath . ($version ? '?v=' . $version : '');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
<link rel="icon" type="image/png" href="<?= htmlspecialchars(asset_url('/assets/img/favicon.png'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>

<?php if (is_impersonating()): ?>
<div class="impersonation-banner">
  <div class="container impersonation-banner-inner">
    <span>👁️ Prohlížíte portál jako <strong><?= htmlspecialchars(full_name($user), ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars(match ($user['role']) {
      'teacher' => 'lektor/ka',
      default => 'rodič',
    }, ENT_QUOTES, 'UTF-8') ?>)</span>
    <form method="post" action="/auth/stop-impersonate.php">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--sm impersonation-banner-exit">Ukončit náhled</button>
    </form>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container nav">
    <a href="/index.php" class="brand">
      <img src="<?= htmlspecialchars(asset_url('/assets/img/cropped-Vzdelavaci-centrum-Inspira-logo-pruhledne-768x279.png'), ENT_QUOTES, 'UTF-8') ?>" alt="INSPIRA — Vzdělávací centrum" class="brand-logo">
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
            <span class="nav-avatar<?= $user['gender'] === 'male' ? ' nav-avatar--male' : '' ?>"><?= htmlspecialchars(initials($user), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="nav-account-name"><?= htmlspecialchars(full_name($user), ENT_QUOTES, 'UTF-8') ?></span>
            <span aria-hidden="true">▾</span>
          </a>
          <ul class="dropdown">
            <li><a href="/account-settings.php"<?= nav_class('account-settings', $activeNav) ?>>Nastavení účtu</a></li>
            <li><a href="/dashboard.php"<?= nav_class('dashboard', $activeNav) ?>>Nástěnka</a></li>
            <li><a href="/obedy.php"<?= nav_class('obedy', $activeNav) ?>>Obědy</a></li>
            <?php if ($user['role'] === 'admin'): ?>
              <li><a href="/admin/users.php"<?= nav_class('admin-users', $activeNav) ?>>Uživatelé a role</a></li>
              <li><a href="/admin/content.php"<?= nav_class('admin-content', $activeNav) ?>>Texty na webu</a></li>
              <li><a href="/admin/pricing.php"<?= nav_class('admin-pricing', $activeNav) ?>>Správa ceníku</a></li>
            <?php endif; ?>
            <li><a href="/auth/logout.php">Odhlásit</a></li>
          </ul>
        </li>
      <?php else: ?>
        <li class="nav-divider" aria-hidden="true"></li>
        <li><a href="/auth/login.php" class="btn btn--primary btn--sm">Portál pro rodiče</a></li>
      <?php endif; ?>
    </ul>
  </div>
</header>

<main>
<?= render_flash() ?>
