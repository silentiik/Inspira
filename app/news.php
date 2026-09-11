<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function all_news(): array
{
    return db()->query(
        "SELECT news.*, users.name AS author_name
         FROM news JOIN users ON users.id = news.author_id
         ORDER BY news.pinned DESC, news.created_at DESC"
    )->fetchAll();
}

function create_news(int $authorId, string $title, string $body, bool $pinned): void
{
    $stmt = db()->prepare(
        'INSERT INTO news (author_id, title, body, pinned) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$authorId, $title, $body, $pinned ? 1 : 0]);
}

function delete_news(int $id): void
{
    db()->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
}
