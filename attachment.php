<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/news.php';

// Same visibility as the news feed itself: any logged-in account can
// open an attachment on a post, no extra role check needed.
require_login();

$attachment = find_attachment((int) ($_GET['id'] ?? 0));
$path = $attachment ? NEWS_UPLOAD_DIR . '/' . $attachment['stored_name'] : null;

if (!$attachment || !$path || !is_file($path)) {
    http_response_code(404);
    exit('Soubor nenalezen.');
}

$isImage = str_starts_with($attachment['mime_type'], 'image/');
$safeFallbackName = preg_replace('/[\r\n"\\\\]/', '_', $attachment['original_name']);

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) $attachment['size_bytes']);
header('X-Content-Type-Options: nosniff');
header(
    'Content-Disposition: ' . ($isImage ? 'inline' : 'attachment')
    . '; filename="' . $safeFallbackName . '"'
    . "; filename*=UTF-8''" . rawurlencode($attachment['original_name'])
);
readfile($path);
