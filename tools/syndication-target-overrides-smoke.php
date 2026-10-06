<?php

declare(strict_types=1);

require dirname(__DIR__) . '/core.php';

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Publications\PublicationSyndicationOverrideRepository;
use ChurchCMS\Modules\Publications\PublicationSyndicationProvider;
use InvalidArgumentException;

$pdo = DatabaseManager::getInstance()->connection();
$publicId = '12345678-1234-4abc-8def-1234567890ab';
$now = gmdate('Y-m-d H:i:s');

$statement = $pdo->prepare(
    'INSERT INTO publications '
    . '(public_id, site_key, type, status, slug, title, excerpt, body_html, author_name, '
    . 'published_at, created_at, updated_at, syndication_targets, syndication_title, syndication_excerpt) '
    . 'VALUES (:public_id, :site_key, :type, :status, :slug, :title, :excerpt, :body_html, NULL, '
    . ':published_at, :created_at, :updated_at, :targets, :syndication_title, :syndication_excerpt)'
);
$statement->execute([
    ':public_id' => $publicId,
    ':site_key' => 'default',
    ':type' => 'news',
    ':status' => 'published',
    ':slug' => 'target-overrides-smoke',
    ':title' => 'Исходный заголовок',
    ':excerpt' => 'Исходное описание',
    ':body_html' => '<p>Тест</p>',
    ':published_at' => $now,
    ':created_at' => $now,
    ':updated_at' => $now,
    ':targets' => json_encode(['rambler', 'rss'], JSON_THROW_ON_ERROR),
    ':syndication_title' => 'Общий заголовок синдикации',
    ':syndication_excerpt' => 'Общее описание синдикации',
]);

$publicationId = (int) $pdo->query(
    "SELECT id FROM publications WHERE public_id = '{$publicId}'"
)->fetchColumn();
if ($publicationId <= 0) {
    throw new RuntimeException('Не удалось создать тестовую публикацию.');
}

$repository = PublicationSyndicationOverrideRepository::fromDatabase();
$repository->save(
    $publicationId,
    'rss',
    'Заголовок RSS',
    'Описание RSS',
    'https://example.test/rss.jpg',
    'image/jpeg',
);
$repository->save(
    $publicationId,
    'rambler',
    'Заголовок Rambler',
    'Описание Rambler',
    'https://example.test/rambler.webp',
    'image/webp',
);

$rssOverride = $repository->forTarget([$publicationId], 'rss')[$publicationId] ?? null;
$ramblerOverride = $repository->forTarget([$publicationId], 'rambler')[$publicationId] ?? null;
if (
    $rssOverride === null
    || $rssOverride['title'] !== 'Заголовок RSS'
    || $ramblerOverride === null
    || $ramblerOverride['title'] !== 'Заголовок Rambler'
) {
    throw new RuntimeException('Переопределения разных каналов смешались.');
}

$provider = new PublicationSyndicationProvider();
$rssEntries = iterator_to_array($provider->entriesForTarget('rss'));
$ramblerEntries = iterator_to_array($provider->entriesForTarget('rambler'));
$rssEntry = array_values(array_filter(
    $rssEntries,
    static fn($entry): bool => $entry->id === $publicId,
))[0] ?? null;
$ramblerEntry = array_values(array_filter(
    $ramblerEntries,
    static fn($entry): bool => $entry->id === $publicId,
))[0] ?? null;

if (
    $rssEntry === null
    || $rssEntry->title !== 'Заголовок RSS'
    || $rssEntry->description !== 'Описание RSS'
    || $rssEntry->imageUrl !== 'https://example.test/rss.jpg'
    || $rssEntry->imageMime !== 'image/jpeg'
) {
    throw new RuntimeException('RSS не получил собственную проекцию публикации.');
}
if (
    $ramblerEntry === null
    || $ramblerEntry->title !== 'Заголовок Rambler'
    || $ramblerEntry->description !== 'Описание Rambler'
    || $ramblerEntry->imageUrl !== 'https://example.test/rambler.webp'
    || $ramblerEntry->imageMime !== 'image/webp'
) {
    throw new RuntimeException('Rambler не получил собственную проекцию публикации.');
}

$repository->save($publicationId, 'rss', '', '', '', '');
if ($repository->forTarget([$publicationId], 'rss') !== []) {
    throw new RuntimeException('Пустое переопределение должно удаляться.');
}

$failed = false;
try {
    $repository->save(
        $publicationId,
        'rss',
        null,
        null,
        'http://example.test/image.jpg',
        'image/jpeg',
    );
} catch (InvalidArgumentException) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('HTTP-изображение не должно приниматься.');
}

$failed = false;
try {
    $repository->save(
        $publicationId,
        'rss',
        null,
        null,
        'https://example.test/image.jpg',
        null,
    );
} catch (InvalidArgumentException) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Изображение без MIME-типа не должно приниматься.');
}

$failed = false;
try {
    $repository->save($publicationId, '../bad', 'x', null, null, null);
} catch (InvalidArgumentException) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Некорректный идентификатор канала не должен приниматься.');
}

fwrite(STDOUT, "Переопределения публикаций по каналам работают корректно.\n");
