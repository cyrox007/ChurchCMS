#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;

$root = dirname(__DIR__, 2);
require $root . '/core.php';

$options = getopt('', [
    'site::',
    'publications::',
    'comments::',
    'reset',
    'confirm:',
]);

$siteKey = trim((string) ($options['site'] ?? 'benchmark'));
$publicationCount = max(1, min(100000, (int) ($options['publications'] ?? 10000)));
$commentCount = max(0, min(500000, (int) ($options['comments'] ?? 30000)));
$reset = array_key_exists('reset', $options);
$confirmation = (string) ($options['confirm'] ?? '');

if ($confirmation !== 'benchmark-fixture') {
    fwrite(STDERR, "Добавьте --confirm=benchmark-fixture для явного подтверждения тестового заполнения.\n");
    exit(2);
}

if ($siteKey !== 'benchmark') {
    fwrite(STDERR, "Генератор использует только изолированный site_key benchmark.\n");
    exit(2);
}

$pdo = DatabaseManager::getInstance()->connection();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$existingStatement = $pdo->prepare('SELECT COUNT(*) FROM publications WHERE site_key = :site_key');
$existingStatement->execute(['site_key' => $siteKey]);
$existing = (int) $existingStatement->fetchColumn();

if ($existing > 0 && !$reset) {
    fwrite(
        STDERR,
        "Для этого benchmark site_key уже есть данные. Используйте --reset вместе с подтверждением.\n",
    );
    exit(2);
}

$started = hrtime(true);
$pdo->beginTransaction();

try {
    if ($reset && $existing > 0) {
        $delete = $pdo->prepare('DELETE FROM publications WHERE site_key = :site_key');
        $delete->execute(['site_key' => $siteKey]);
    }

    $publicationSql = 'INSERT INTO publications (
        public_id, site_key, type, status, slug, title, excerpt, body_html,
        author_name, published_at, created_at, updated_at, syndication_targets,
        syndication_title, syndication_excerpt, comments_enabled
     ) VALUES (
        :public_id, :site_key, :type, :status, :slug, :title, :excerpt, :body_html,
        :author_name, :published_at, :created_at, :updated_at, :syndication_targets,
        NULL, NULL, :comments_enabled
     )';

    if ($driver === 'pgsql') {
        $publicationSql .= ' RETURNING id';
    }

    $publicationInsert = $pdo->prepare($publicationSql);

    $publicationIds = [];
    $baseTimestamp = strtotime('2025-01-01 12:00:00 UTC');
    $body = '<p>' . str_repeat('Тестовый текст материала для проверки производительности ChurchCMS. ', 24) . '</p>';
    $excerpt = str_repeat('Краткое описание тестового материала. ', 6);

    for ($i = 1; $i <= $publicationCount; $i++) {
        $publishedAt = gmdate('Y-m-d H:i:s', $baseTimestamp + $i);
        $publicId = sprintf('00000000-0000-4000-8000-%012x', $i);

        $publicationInsert->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'type' => $i % 7 === 0 ? 'article' : 'news',
            'status' => 'published',
            'slug' => sprintf('benchmark-%06d', $i),
            'title' => sprintf('Тестовый материал %06d', $i),
            'excerpt' => $excerpt,
            'body_html' => $body,
            'author_name' => 'Нагрузочный профиль',
            'published_at' => $publishedAt,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
            'syndication_targets' => $i % 10 === 0 ? '["rss"]' : '[]',
            'comments_enabled' => 1,
        ]);

        $id = $driver === 'pgsql'
            ? $publicationInsert->fetchColumn()
            : $pdo->lastInsertId();

        if ($id === false || (int) $id < 1) {
            throw new RuntimeException('Не удалось получить ID тестовой публикации.');
        }

        $publicationIds[] = (int) $id;
    }

    if ($publicationIds === []) {
        throw new RuntimeException('Не удалось создать тестовые публикации.');
    }

    $commentInsert = $pdo->prepare(
        'INSERT INTO publication_comments (
            public_id, publication_id, status, display_name, email, body_text,
            created_at, moderated_at, moderator_user_id
         ) VALUES (
            :public_id, :publication_id, :status, :display_name, NULL, :body_text,
            :created_at, :moderated_at, NULL
         )'
    );

    $publicationTotal = count($publicationIds);
    for ($i = 1; $i <= $commentCount; $i++) {
        $approved = $i % 5 !== 0;
        $createdAt = gmdate('Y-m-d H:i:s', $baseTimestamp + $publicationCount + $i);

        $commentInsert->execute([
            'public_id' => sprintf('10000000-0000-4000-8000-%012x', $i),
            'publication_id' => $publicationIds[($i - 1) % $publicationTotal],
            'status' => $approved ? 'approved' : 'pending',
            'display_name' => sprintf('Посетитель %05d', (($i - 1) % 1000) + 1),
            'body_text' => 'Тестовый комментарий для репрезентативного набора данных.',
            'created_at' => $createdAt,
            'moderated_at' => $approved ? $createdAt : null,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Не удалось подготовить benchmark-набор: {$e->getMessage()}\n");
    exit(1);
}

$elapsedMs = (hrtime(true) - $started) / 1_000_000;

echo json_encode([
    'site_key' => $siteKey,
    'publications' => $publicationCount,
    'comments' => $commentCount,
    'elapsed_ms' => round($elapsedMs, 2),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
