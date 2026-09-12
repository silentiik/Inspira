<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const NEWS_UPLOAD_DIR = DATA_DIR . '/news-uploads';
const NEWS_MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB per file
const NEWS_MAX_ATTACHMENTS = 6; // per post
const NEWS_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const NEWS_ALLOWED_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
];

function all_news(): array
{
    return db()->query(
        "SELECT news.*, trim(users.first_name || ' ' || users.last_name) AS author_name
         FROM news JOIN users ON users.id = news.author_id
         ORDER BY news.pinned DESC, news.created_at DESC"
    )->fetchAll();
}

/** Returns the new post's id, so attachments can be linked to it. */
function create_news(int $authorId, string $title, string $body, bool $pinned): int
{
    $stmt = db()->prepare(
        'INSERT INTO news (author_id, title, body, pinned) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$authorId, $title, $body, $pinned ? 1 : 0]);
    return (int) db()->lastInsertId();
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
 * Validates one uploaded file (from a $_FILES['x']['tmp_name'][$i]
 * style entry) and, if it passes, moves it into permanent storage and
 * records it against $newsId. Returns a user-facing error message on
 * failure, or null on success — callers collect these into a flash
 * message instead of failing the whole post.
 */
function add_news_attachment(int $newsId, string $tmpName, string $originalName, int $reportedSize): ?string
{
    $originalName = trim($originalName);
    if ($originalName === '') {
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, NEWS_ALLOWED_EXTENSIONS, true)) {
        return "Nepodporovaný typ souboru: $originalName";
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
