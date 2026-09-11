<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/content.php';

$user = require_role(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = (string) ($_POST['block_key'] ?? '');
    $value = (string) ($_POST['value'] ?? '');

    $stmt = db()->prepare('SELECT * FROM content_blocks WHERE block_key = ?');
    $stmt->execute([$key]);
    $block = $stmt->fetch();

    $allowed = $block && ($user['role'] === 'admin' || (int) $block['editable_by_teacher'] === 1);
    if ($allowed) {
        set_content($key, $value, (int) $user['id']);
        flash_set('success', 'Text byl uložen.');
    } else {
        flash_set('error', 'Nemáte oprávnění upravit tento text.');
    }

    header('Location: /admin/content.php');
    exit;
}

$blocks = all_content_blocks();
if ($user['role'] !== 'admin') {
    $blocks = array_values(array_filter($blocks, fn ($b) => (int) $b['editable_by_teacher'] === 1));
}

$labels = [
    'home.hero.title' => 'Domů — hlavní nadpis',
    'home.hero.lead' => 'Domů — úvodní text',
    'home.event.text' => 'Domů — štítek s akcí',
    'home.banner.title' => 'Domů — nadpis banneru s přihláškami',
    'home.banner.lead' => 'Domů — text banneru s přihláškami',
];

$pageTitle = 'Texty na webu | INSPIRA';
require __DIR__ . '/../includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <p class="breadcrumbs"><a href="/dashboard.php">Nástěnka</a> / Texty na webu</p>
      <h1>Texty na webu</h1>
      <p class="lead">Úpravy se projeví na webu okamžitě.</p>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <?php if (empty($blocks)): ?>
        <p class="hint-text">Momentálně pro vás nejsou k úpravě žádné texty.</p>
      <?php endif; ?>
      <?php foreach ($blocks as $block): ?>
        <div class="form-card" style="margin-bottom:20px;">
          <form method="post" action="/admin/content.php">
            <?= csrf_field() ?>
            <input type="hidden" name="block_key" value="<?= htmlspecialchars($block['block_key'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="field">
              <label><?= htmlspecialchars($labels[$block['block_key']] ?? $block['block_key'], ENT_QUOTES, 'UTF-8') ?></label>
              <textarea name="value" rows="3"><?= htmlspecialchars($block['value'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <button type="submit" class="btn btn--primary">Uložit</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
