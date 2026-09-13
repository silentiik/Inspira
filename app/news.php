<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/children.php';

const NEWS_UPLOAD_DIR = DATA_DIR . '/news-uploads';
const NEWS_MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB per file
const NEWS_MAX_ATTACHMENTS = 6; // per post, images and documents combined
const NEWS_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const NEWS_DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
const NEWS_ALLOWED_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
];

// Keys match CHILD_PROGRAMS (app/children.php) so a parent's own
// children's programs can be checked directly against a post's category.
const NEWS_CATEGORIES = [
    'all' => 'Všichni',
    'inspirka' => 'Inspirka',
    'domskolaci' => 'Domškoláci',
];

function all_news(): array
{
    return db()->query(
        "SELECT news.*, trim(users.first_name || ' ' || users.last_name) AS author_name
         FROM news JOIN users ON users.id = news.author_id
         ORDER BY news.pinned DESC, news.created_at DESC"
    )->fetchAll();
}

/**
 * Filters $newsItems down to what $user is allowed to see: everything
 * for admin/teacher, and for a parent, only 'all'-category posts plus
 * any category matching one of their own children's programs (so a
 * parent with kids in both programs sees both categories).
 */
function visible_news_for(array $user, array $newsItems): array
{
    if (in_array($user['role'], ['admin', 'teacher'], true)) {
        return $newsItems;
    }
    $childPrograms = array_unique(array_column(children_for_parent((int) $user['id']), 'program'));
    return array_values(array_filter(
        $newsItems,
        fn ($item) => $item['category'] === 'all' || in_array($item['category'], $childPrograms, true)
    ));
}

/**
 * Renders a post's body for output: already-sanitized HTML as-is for
 * posts written through the formatting toolbar, or escaped + nl2br()
 * for older plain-text posts that predate it. Safe to echo directly in
 * either case — used both for display and to preload the edit form's
 * rich-text editor.
 */
function render_news_body(array $item): string
{
    if (($item['body_format'] ?? 'text') === 'html') {
        return $item['body'];
    }
    return nl2br(htmlspecialchars($item['body'], ENT_QUOTES, 'UTF-8'));
}

// The only font sizes the toolbar's dropdown can produce — kept as an
// explicit allowlist rather than accepting arbitrary px values, so a
// forged request can't smuggle in something like font-size:999999px.
const NEWS_BODY_FONT_SIZES = [12, 16, 20, 28];

/**
 * Strips a rich-text post body down to a small safe HTML allowlist
 * (b/strong, i/em, u, p with only a text-align style, span with only a
 * font-size from NEWS_BODY_FONT_SIZES) before it's ever stored. The
 * formatting toolbar only ever produces these tags — this exists so a
 * forged request (or a stray browser quirk) can't smuggle in a script
 * tag or an event-handler attribute.
 */
function sanitize_news_body_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'span'];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8"?><div>' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();

    $wrapper = $doc->getElementsByTagName('div')->item(0);
    if ($wrapper === null) {
        return '';
    }

    $sanitizeChildren = function (DOMNode $parent) use (&$sanitizeChildren, $allowedTags) {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!($child instanceof DOMElement)) {
                $parent->removeChild($child);
                continue;
            }
            $tag = strtolower($child->tagName);
            if (!in_array($tag, $allowedTags, true)) {
                // Not an allowed tag: clean its contents first, then
                // unwrap — keep the text, drop the tag around it.
                $sanitizeChildren($child);
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                if ($tag === 'p' && $attr->name === 'style'
                    && preg_match('/^text-align:\s*(left|center|right|justify)\s*;?$/i', trim($attr->value), $m)) {
                    $child->setAttribute('style', 'text-align: ' . strtolower($m[1]) . ';');
                    continue;
                }
                if ($tag === 'span' && $attr->name === 'style'
                    && preg_match('/^font-size:\s*(\d+)px\s*;?$/i', trim($attr->value), $m)
                    && in_array((int) $m[1], NEWS_BODY_FONT_SIZES, true)) {
                    $child->setAttribute('style', 'font-size: ' . (int) $m[1] . 'px;');
                    continue;
                }
                $child->removeAttribute($attr->name);
            }
            $sanitizeChildren($child);
        }
    };
    $sanitizeChildren($wrapper);

    $result = '';
    foreach (iterator_to_array($wrapper->childNodes) as $child) {
        $result .= $doc->saveHTML($child);
    }
    return trim($result);
}

/** Returns the new post's id, so attachments can be linked to it. */
function create_news(int $authorId, string $title, string $body, bool $pinned, string $category = 'all'): int
{
    $category = array_key_exists($category, NEWS_CATEGORIES) ? $category : 'all';
    $stmt = db()->prepare(
        "INSERT INTO news (author_id, title, body, body_format, pinned, category) VALUES (?, ?, ?, 'html', ?, ?)"
    );
    $stmt->execute([$authorId, $title, sanitize_news_body_html($body), $pinned ? 1 : 0, $category]);
    return (int) db()->lastInsertId();
}

/** Edits a post's title/text/category. Pinning is its own toggle_news_pin() action; attachments are untouched. */
function update_news(int $id, string $title, string $body, string $category = 'all'): void
{
    $category = array_key_exists($category, NEWS_CATEGORIES) ? $category : 'all';
    db()->prepare("UPDATE news SET title = ?, body = ?, body_format = 'html', category = ? WHERE id = ?")
        ->execute([$title, sanitize_news_body_html($body), $category, $id]);
}

function toggle_news_pin(int $id): void
{
    db()->prepare('UPDATE news SET pinned = 1 - pinned WHERE id = ?')->execute([$id]);
}

/** Attachment rows also get deleted via ON DELETE CASCADE, but their files on disk need an explicit unlink. */
function delete_news(int $id): void
{
    foreach (attachments_for_news($id) as $attachment) {
        @unlink(NEWS_UPLOAD_DIR . '/' . $attachment['stored_name']);
    }
    db()->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
}

function attachments_for_news(int $newsId): array
{
    $stmt = db()->prepare('SELECT * FROM news_attachments WHERE news_id = ? ORDER BY id');
    $stmt->execute([$newsId]);
    return $stmt->fetchAll();
}

function find_attachment(int $attachmentId): ?array
{
    $stmt = db()->prepare('SELECT * FROM news_attachments WHERE id = ?');
    $stmt->execute([$attachmentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Deletes one attachment's file and row — but only if it actually
 * belongs to $newsId, so a submitted id for a different post's
 * attachment can't be used to delete someone else's file.
 */
function remove_news_attachment(int $attachmentId, int $newsId): void
{
    $attachment = find_attachment($attachmentId);
    if ($attachment === null || (int) $attachment['news_id'] !== $newsId) {
        return;
    }
    @unlink(NEWS_UPLOAD_DIR . '/' . $attachment['stored_name']);
    db()->prepare('DELETE FROM news_attachments WHERE id = ?')->execute([$attachmentId]);
}

/**
 * Validates one uploaded file (from a $_FILES['x']['tmp_name'][$i]
 * style entry) and, if it passes, moves it into permanent storage and
 * records it against $newsId. Returns a user-facing error message on
 * failure, or null on success — callers collect these into a flash
 * message instead of failing the whole post.
 *
 * @param string $kind 'image' or 'document' — restricts which
 *   extensions are accepted to match the upload field the file came
 *   from (the two are shown as separate fields in the form).
 */
function add_news_attachment(int $newsId, string $tmpName, string $originalName, int $reportedSize, string $kind): ?string
{
    $originalName = trim($originalName);
    if ($originalName === '') {
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = $kind === 'image' ? NEWS_IMAGE_EXTENSIONS : NEWS_DOCUMENT_EXTENSIONS;
    if (!in_array($ext, $allowedExtensions, true)) {
        return $kind === 'image'
            ? "Soubor $originalName není podporovaný typ obrázku."
            : "Soubor $originalName není podporovaný typ přílohy.";
    }
    if ($reportedSize <= 0 || $reportedSize > NEWS_MAX_FILE_SIZE) {
        return "Soubor $originalName je příliš velký (max " . (int) (NEWS_MAX_FILE_SIZE / 1024 / 1024) . " MB).";
    }
    // For anything with an image extension, confirm it's genuinely
    // decodable image data — not just a renamed file — before it ever
    // gets embedded as an <img> on the page.
    if (in_array($ext, NEWS_IMAGE_EXTENSIONS, true) && @getimagesize($tmpName) === false) {
        return "Soubor $originalName není platný obrázek.";
    }

    if (!is_dir(NEWS_UPLOAD_DIR)) {
        if (!mkdir(NEWS_UPLOAD_DIR, 0755, true) && !is_dir(NEWS_UPLOAD_DIR)) {
            return "Soubor $originalName se nepodařilo uložit (chyba úložiště).";
        }
        // Belt-and-braces alongside DATA_DIR's own .htaccess: files here
        // are only ever meant to be read by attachment.php (which checks
        // require_login()), never served directly by the webserver.
        file_put_contents(NEWS_UPLOAD_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = ($finfo !== false ? finfo_file($finfo, $tmpName) : false) ?: 'application/octet-stream';
    if ($finfo !== false) {
        finfo_close($finfo);
    }

    if (!move_uploaded_file($tmpName, NEWS_UPLOAD_DIR . '/' . $storedName)) {
        return "Soubor $originalName se nepodařilo nahrát.";
    }

    db()->prepare(
        'INSERT INTO news_attachments (news_id, original_name, stored_name, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?)'
    )->execute([$newsId, $originalName, $storedName, $mimeType, $reportedSize]);

    return null;
}

/**
 * Processes any images[]/attachments[] fields present in the current
 * request against $newsId — shared by both creating and editing a
 * post. Honors the combined NEWS_MAX_ATTACHMENTS cap, counting
 * whatever the post already has (relevant on edit, always 0 on
 * create). Returns a user-facing error string per file that failed.
 */
function process_news_file_uploads(int $newsId): array
{
    $errors = [];
    $remainingSlots = NEWS_MAX_ATTACHMENTS - count(attachments_for_news($newsId));
    foreach (['images' => 'image', 'attachments' => 'document'] as $fieldName => $kind) {
        $uploaded = $_FILES[$fieldName] ?? null;
        if (!$uploaded || !is_array($uploaded['name'])) {
            continue;
        }
        for ($i = 0; $i < count($uploaded['name']) && $remainingSlots > 0; $i++) {
            if ($uploaded['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($uploaded['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = 'Soubor ' . $uploaded['name'][$i] . ' se nepodařilo nahrát.';
                continue;
            }
            $error = add_news_attachment($newsId, $uploaded['tmp_name'][$i], $uploaded['name'][$i], (int) $uploaded['size'][$i], $kind);
            if ($error !== null) {
                $errors[] = $error;
            } else {
                $remainingSlots--;
            }
        }
    }
    return $errors;
}
