<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/flash.php';
require_once __DIR__ . '/app/news.php';
require_once __DIR__ . '/app/children.php';

$user = require_login();
$canPost = in_array($user['role'], ['admin', 'teacher'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'post_news' && $canPost) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $pinned = !empty($_POST['pinned']);
        $hasFiles = !empty(array_filter(array_merge($_FILES['images']['name'] ?? [], $_FILES['attachments']['name'] ?? [])));

        if ($title === '' || ($body === '' && !$hasFiles)) {
            flash_set('error', 'Zadejte prosím titulek a text novinky, nebo k ní alespoň přiložte obrázek či soubor.');
        } else {
            $newsId = create_news((int) $user['id'], $title, $body, $pinned);
            $uploadErrors = process_news_file_uploads($newsId);

            flash_set(
                empty($uploadErrors) ? 'success' : 'error',
                empty($uploadErrors)
                    ? 'Novinka byla zveřejněna.'
                    : 'Novinka byla zveřejněna, ale: ' . implode(' ', $uploadErrors)
            );
        }
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'delete_news' && $canPost) {
        delete_news((int) ($_POST['news_id'] ?? 0));
        flash_set('success', 'Novinka byla smazána.');
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'edit_news' && $canPost) {
        $newsId = (int) ($_POST['news_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '') {
            flash_set('error', 'Zadejte prosím titulek novinky.');
        } else {
            update_news($newsId, $title, $body);

            foreach ((array) ($_POST['remove_attachments'] ?? []) as $attachmentId) {
                remove_news_attachment((int) $attachmentId, $newsId);
            }
            $uploadErrors = process_news_file_uploads($newsId);

            flash_set(
                empty($uploadErrors) ? 'success' : 'error',
                empty($uploadErrors)
                    ? 'Novinka byla upravena.'
                    : 'Novinka byla upravena, ale: ' . implode(' ', $uploadErrors)
            );
        }
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'toggle_pin' && $canPost) {
        $newsId = (int) ($_POST['news_id'] ?? 0);
        toggle_news_pin($newsId);
        // Jump back to the same post instead of the top of the page —
        // pinning can also reorder the list (pinned posts sort first).
        header('Location: /dashboard.php#news-' . $newsId);
        exit;
    }

    if ($action === 'add_child' && $user['role'] === 'parent') {
        $firstName = trim((string) ($_POST['child_first_name'] ?? ''));
        $lastName = trim((string) ($_POST['child_last_name'] ?? ''));
        $program = (string) ($_POST['child_program'] ?? '');
        $dateOfBirth = (string) ($_POST['child_date_of_birth'] ?? '');
        if ($firstName !== '' && $lastName !== '' && in_array($program, ['inspirka', 'domskolaci'], true)) {
            add_child([(int) $user['id']], $firstName, $lastName, $program, $dateOfBirth ?: null);
            flash_set('success', 'Dítě bylo přidáno.');
        } else {
            flash_set('error', 'Zadejte prosím jméno, příjmení a program dítěte.');
        }
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'save_lunch' && $user['role'] === 'parent') {
        $childId = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to($childId, (int) $user['id'])) {
            $week = week_start('next monday');
            foreach (array_keys(LUNCH_DAYS) as $day) {
                $value = (string) ($_POST['lunch_' . $day] ?? '');
                if ($value !== '' && in_array($value, LUNCH_OPTIONS, true)) {
                    save_lunch_selection($childId, $week, $day, $value);
                }
            }
            flash_set('success', 'Výběr obědů byl uložen.');
        }
        header('Location: /dashboard.php');
        exit;
    }
}

$newsItems = all_news();
$children = $user['role'] === 'parent' ? children_for_parent((int) $user['id']) : [];
$nextWeek = week_start('next monday');

$pageTitle = 'Nástěnka | INSPIRA';
require_once __DIR__ . '/includes/header.php';
?>

  <section class="page-header">
    <div class="container">
      <h1>Vítejte, <?= htmlspecialchars(full_name($user), ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="lead">
        <span class="role-badge"><?= htmlspecialchars(match ($user['role']) {
          'admin' => 'Administrátor',
          'teacher' => 'Učitel/ka',
          default => 'Rodič',
        }, ENT_QUOTES, 'UTF-8') ?></span>
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container">

      <?php if ($user['role'] === 'parent'): ?>
      <div class="portal-grid">
      <?php endif; ?>
        <div class="stack">
          <?php if ($canPost): ?>
            <div class="form-card">
              <h3 class="mt-0">Přidat novinku</h3>
              <form method="post" action="/dashboard.php" enctype="multipart/form-data" class="news-create-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="post_news">
                <div class="field">
                  <label for="title">Titulek</label>
                  <input type="text" id="title" name="title" required>
                </div>
                <div class="field">
                  <label for="body">Text</label>
                  <textarea id="body" name="body"></textarea>
                </div>
                <div class="field-row">
                  <div class="field">
                    <div class="file-picker">
                      <input type="file" id="images" name="images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" class="visually-hidden" data-file-picker>
                      <label for="images" class="btn btn--outline btn--sm">🖼️ Přidat obrázky</label>
                      <span class="file-picker-status" data-file-picker-status>Nevybrán žádný soubor</span>
                    </div>
                  </div>
                  <div class="field">
                    <div class="file-picker">
                      <input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" class="visually-hidden" data-file-picker>
                      <label for="attachments" class="btn btn--outline btn--sm">📎 Přidat přílohy</label>
                      <span class="file-picker-status" data-file-picker-status>Nevybrán žádný soubor</span>
                    </div>
                  </div>
                </div>
                <span class="hint-text">Max. <?= NEWS_MAX_ATTACHMENTS ?> souborů celkem, každý do <?= (int) (NEWS_MAX_FILE_SIZE / 1024 / 1024) ?> MB. Obrázky se zobrazí přímo v novince, přílohy jako odkaz ke stažení.</span>
                <div class="news-submit-row">
                  <button type="submit" class="btn btn--accent">Zveřejnit novinku</button>
                  <div class="field checkbox-field">
                    <input type="checkbox" id="pinned" name="pinned">
                    <label for="pinned" style="margin:0;">Připnout nahoru</label>
                  </div>
                </div>
              </form>
            </div>
          <?php endif; ?>

          <div class="form-card news-board-header">
            <h3 class="mt-0 text-center news-board-title">📋 Nástěnka — novinky a informace pro rodiče</h3>
            <?php if (empty($newsItems)): ?>
              <p class="hint-text">Zatím tu nejsou žádné novinky.</p>
            <?php endif; ?>
          </div>
          <?php foreach ($newsItems as $item): ?>
            <?php
              $attachments = attachments_for_news((int) $item['id']);
              $images = array_filter($attachments, fn ($a) => str_starts_with($a['mime_type'], 'image/'));
              $files = array_filter($attachments, fn ($a) => !str_starts_with($a['mime_type'], 'image/'));
              // created_at is stored as UTC (SQLite's datetime('now'));
              // convert explicitly rather than treating the naive
              // string as local time, or the displayed clock time
              // would be off by the timezone offset.
              $createdAt = (new DateTimeImmutable($item['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Prague'));
              $metaText = htmlspecialchars($item['author_name'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($createdAt->format('j. n. Y H:i'), ENT_QUOTES, 'UTF-8') . ($item['pinned'] ? ' · <strong>Připnuto</strong>' : '');
            ?>
            <div class="form-card news-item<?= $item['pinned'] ? ' is-pinned' : '' ?>" id="news-<?= (int) $item['id'] ?>">
                <div class="news-item-header">
                  <h4><?= $item['pinned'] ? '📌 ' : '' ?><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                  <?php if ($canPost): ?>
                    <div class="news-item-actions">
                      <span class="news-meta-inline"><?= $metaText ?></span>
                      <form method="post" action="/dashboard.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_pin">
                        <input type="hidden" name="news_id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn--pin" aria-label="<?= $item['pinned'] ? 'Odepnout novinku' : 'Připnout novinku nahoru' ?>">📌</button>
                      </form>
                      <button type="button" class="icon-btn icon-btn--edit" data-toggle-edit="edit-news-<?= (int) $item['id'] ?>" aria-label="Upravit novinku">✎</button>
                      <form method="post" action="/dashboard.php" data-confirm="Opravdu smazat tuto novinku?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_news">
                        <input type="hidden" name="news_id" value="<?= (int) $item['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn--delete" aria-label="Smazat novinku">✕</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </div>
                <?php if (!$canPost): ?>
                  <div class="news-meta"><?= $metaText ?></div>
                <?php endif; ?>
                <?php $hasBody = $item['body'] !== ''; ?>
                <?php if ($hasBody && $images): ?>
                  <div class="news-content-split">
                    <div class="news-text">
                      <p style="margin:0;"><?= nl2br(htmlspecialchars($item['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </div>
                    <div class="news-images-side">
                      <?php foreach ($images as $image): ?>
                        <a href="/attachment.php?id=<?= (int) $image['id'] ?>" class="news-image-link" data-lightbox>
                          <img class="news-image" src="/attachment.php?id=<?= (int) $image['id'] ?>" alt="<?= htmlspecialchars($image['original_name'], ENT_QUOTES, 'UTF-8') ?>">
                        </a>
                      <?php endforeach; ?>
                      <?php if ($files): ?>
                        <div class="news-attachments-block">
                          <div class="news-attachments-heading">Přílohy ke stažení</div>
                          <ul class="news-attachments">
                            <?php foreach ($files as $file): ?>
                              <li><a href="/attachment.php?id=<?= (int) $file['id'] ?>">📎 <?= htmlspecialchars($file['original_name'], ENT_QUOTES, 'UTF-8') ?></a></li>
                            <?php endforeach; ?>
                          </ul>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <?php if ($hasBody): ?>
                    <p style="margin:0;"><?= nl2br(htmlspecialchars($item['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                  <?php endif; ?>
                  <?php foreach ($images as $image): ?>
                    <a href="/attachment.php?id=<?= (int) $image['id'] ?>" class="news-image-link" data-lightbox>
                      <img class="news-image" src="/attachment.php?id=<?= (int) $image['id'] ?>" alt="<?= htmlspecialchars($image['original_name'], ENT_QUOTES, 'UTF-8') ?>">
                    </a>
                  <?php endforeach; ?>
                  <?php if ($files): ?>
                    <div class="news-attachments-block">
                      <div class="news-attachments-heading">Přílohy ke stažení</div>
                      <ul class="news-attachments">
                        <?php foreach ($files as $file): ?>
                          <li><a href="/attachment.php?id=<?= (int) $file['id'] ?>">📎 <?= htmlspecialchars($file['original_name'], ENT_QUOTES, 'UTF-8') ?></a></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($canPost): ?>
                  <form method="post" action="/dashboard.php" enctype="multipart/form-data" class="news-edit-form" id="edit-news-<?= (int) $item['id'] ?>" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="edit_news">
                    <input type="hidden" name="news_id" value="<?= (int) $item['id'] ?>">
                    <div class="field">
                      <label for="edit_title_<?= (int) $item['id'] ?>">Titulek</label>
                      <input type="text" id="edit_title_<?= (int) $item['id'] ?>" name="title" value="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="field">
                      <label for="edit_body_<?= (int) $item['id'] ?>">Text</label>
                      <textarea id="edit_body_<?= (int) $item['id'] ?>" name="body"><?= htmlspecialchars($item['body'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <?php if ($attachments): ?>
                      <div class="field">
                        <label>Odstranit stávající přílohy</label>
                        <div class="checkbox-list remove-attachments-list">
                          <?php foreach ($attachments as $att): ?>
                            <label class="checkbox-list-item" for="remove_att_<?= (int) $att['id'] ?>">
                              <input type="checkbox" id="remove_att_<?= (int) $att['id'] ?>" name="remove_attachments[]" value="<?= (int) $att['id'] ?>">
                              <?= str_starts_with($att['mime_type'], 'image/') ? '🖼️' : '📎' ?> <?= htmlspecialchars($att['original_name'], ENT_QUOTES, 'UTF-8') ?>
                            </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                    <div class="field-row">
                      <div class="field">
                        <div class="file-picker">
                          <input type="file" id="edit_images_<?= (int) $item['id'] ?>" name="images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" class="visually-hidden" data-file-picker>
                          <label for="edit_images_<?= (int) $item['id'] ?>" class="btn btn--outline btn--sm">🖼️ Přidat obrázky</label>
                          <span class="file-picker-status" data-file-picker-status>Nevybrán žádný soubor</span>
                        </div>
                      </div>
                      <div class="field">
                        <div class="file-picker">
                          <input type="file" id="edit_attachments_<?= (int) $item['id'] ?>" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" class="visually-hidden" data-file-picker>
                          <label for="edit_attachments_<?= (int) $item['id'] ?>" class="btn btn--outline btn--sm">📎 Přidat přílohy</label>
                          <span class="file-picker-status" data-file-picker-status>Nevybrán žádný soubor</span>
                        </div>
                      </div>
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm">Uložit</button>
                  </form>
                <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($user['role'] === 'parent'): ?>
          <div class="form-card">
            <h3 class="mt-0">🍽️ Výběr obědů na týden od <?= htmlspecialchars(date('j. n. Y', strtotime($nextWeek)), ENT_QUOTES, 'UTF-8') ?></h3>

            <?php if (empty($children)): ?>
              <p class="hint-text">Nejprve přidejte dítě, abyste mohli vybírat obědy.</p>
            <?php endif; ?>

            <?php foreach ($children as $child): ?>
              <?php $selections = lunch_selections_for((int) $child['id'], $nextWeek); ?>
              <form method="post" action="/dashboard.php" style="margin-bottom:24px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_lunch">
                <input type="hidden" name="child_id" value="<?= (int) $child['id'] ?>">
                <h4><?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="lunch-week">
                  <?php foreach (LUNCH_DAYS as $code => $label): ?>
                    <div class="lunch-day<?= isset($selections[$code]) ? ' is-saved' : '' ?>">
                      <span class="lunch-day-name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                      <select name="lunch_<?= $code ?>">
                        <option value="">Nevybráno</option>
                        <?php foreach (LUNCH_OPTIONS as $option): ?>
                          <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"<?= ($selections[$code] ?? '') === $option ? ' selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn--primary btn--block" style="margin-top:14px;">Uložit výběr pro <?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?></button>
              </form>
            <?php endforeach; ?>

            <h4>Přidat dítě</h4>
            <form method="post" action="/dashboard.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_child">
              <div class="field-row">
                <div class="field">
                  <label for="child_first_name">Jméno dítěte</label>
                  <input type="text" id="child_first_name" name="child_first_name" required>
                </div>
                <div class="field">
                  <label for="child_last_name">Příjmení dítěte</label>
                  <input type="text" id="child_last_name" name="child_last_name" required>
                </div>
              </div>
              <div class="field-row">
                <div class="field">
                  <label for="child_date_of_birth">Datum narození</label>
                  <input type="date" id="child_date_of_birth" name="child_date_of_birth">
                </div>
                <div class="field">
                  <label for="child_program">Program</label>
                  <select id="child_program" name="child_program" required>
                    <option value="inspirka">INSPIRKA</option>
                    <option value="domskolaci">Domškolácká akademie</option>
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn--outline">Přidat dítě</button>
            </form>
          </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
