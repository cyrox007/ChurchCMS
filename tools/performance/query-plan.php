#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;

$root = dirname(__DIR__, 2);
require $root . '/core.php';

$pdo = DatabaseManager::getInstance()->connection();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if (!in_array($driver, ['pgsql', 'mysql'], true)) {
    fwrite(STDERR, "Аудит query plan поддерживает PostgreSQL и MySQL.\n");
    exit(2);
}

$publicationCount = (int) $pdo
    ->query("SELECT COUNT(*) FROM publications WHERE site_key = 'benchmark'")
    ->fetchColumn();
$commentCount = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM publication_comments c
         INNER JOIN publications p ON p.id = c.publication_id
         WHERE p.site_key = 'benchmark'"
    )
    ->fetchColumn();

if ($publicationCount < 10000 || $commentCount < 30000) {
    fwrite(
        STDERR,
        "Для query-plan аудита нужен полный benchmark-набор: минимум 10000 публикаций и 30000 комментариев.\n",
    );
    exit(2);
}

if ($driver === 'pgsql') {
    $pdo->exec('ANALYZE publications');
    $pdo->exec('ANALYZE publication_comments');
} else {
    $pdo->query('ANALYZE TABLE publications, publication_comments')->fetchAll();
}

$publicationId = (int) $pdo
    ->query(
        "SELECT id FROM publications
         WHERE site_key = 'benchmark'
         ORDER BY id ASC
         LIMIT 1"
    )
    ->fetchColumn();

if ($publicationId < 1) {
    fwrite(STDERR, "Не найдена контрольная публикация benchmark-набора.\n");
    exit(1);
}

$queries = [
    'archive_first_page' => [
        'expected' => 'publications_public_list_idx',
        'sql' => <<<'SQL'
SELECT *
FROM publications
WHERE site_key = 'benchmark'
  AND status = 'published'
  AND published_at IS NOT NULL
  AND published_at <= '2030-01-01 00:00:00'
ORDER BY published_at DESC, id DESC
LIMIT 20 OFFSET 0
SQL,
    ],
    'archive_deep_page' => [
        'expected' => 'publications_public_list_idx',
        'sql' => <<<'SQL'
SELECT *
FROM publications
WHERE site_key = 'benchmark'
  AND status = 'published'
  AND published_at IS NOT NULL
  AND published_at <= '2030-01-01 00:00:00'
ORDER BY published_at DESC, id DESC
LIMIT 20 OFFSET 7500
SQL,
    ],
    'publication_detail' => [
        'expected' => 'publications_site_slug_unique',
        'sql' => <<<'SQL'
SELECT *
FROM publications
WHERE site_key = 'benchmark'
  AND slug = 'benchmark-000001'
  AND status = 'published'
  AND published_at IS NOT NULL
  AND published_at <= '2030-01-01 00:00:00'
LIMIT 1
SQL,
    ],
    'approved_comments' => [
        'expected' => 'publication_comments_public_idx',
        'sql' => sprintf(
            "SELECT c.*, p.title AS publication_title, p.slug AS publication_slug
             FROM publication_comments c
             INNER JOIN publications p ON p.id = c.publication_id
             WHERE c.publication_id = %d
               AND c.status = 'approved'
             ORDER BY c.created_at ASC, c.id ASC
             LIMIT 100",
            $publicationId,
        ),
    ],
    'moderation_queue' => [
        'expected' => 'publication_comments_queue_idx',
        'sql' => <<<'SQL'
SELECT c.*, p.title AS publication_title, p.slug AS publication_slug
FROM publication_comments c
INNER JOIN publications p ON p.id = c.publication_id
WHERE c.status = 'pending'
ORDER BY c.created_at ASC, c.id ASC
LIMIT 100
SQL,
    ],
];

$result = [
    'driver' => $driver,
    'publications' => $publicationCount,
    'comments' => $commentCount,
    'queries' => [],
];

$failed = false;

foreach ($queries as $name => $query) {
    $plan = explain($pdo, $driver, $query['sql']);
    $indexes = collectIndexes($plan, $driver);
    sort($indexes, SORT_STRING);
    $indexes = array_values(array_unique($indexes));
    $usesExpected = in_array($query['expected'], $indexes, true);

    $result['queries'][$name] = [
        'expected_index' => $query['expected'],
        'used_indexes' => $indexes,
        'uses_expected_index' => $usesExpected,
    ];

    if (!$usesExpected) {
        $failed = true;
    }
}

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;

if ($failed) {
    fwrite(STDERR, "Один или несколько критических запросов не используют ожидаемый индекс.\n");
    exit(1);
}

/**
 * @return array<string,mixed>|list<mixed>
 */
function explain(PDO $pdo, string $driver, string $sql): array
{
    $prefix = $driver === 'pgsql'
        ? 'EXPLAIN (FORMAT JSON) '
        : 'EXPLAIN FORMAT=JSON ';

    $raw = $pdo->query($prefix . $sql)->fetchColumn();
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException('База данных не вернула query plan.');
    }

    $decoded = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Query plan имеет неожиданный формат.');
    }

    return $decoded;
}

/**
 * @param array<string,mixed>|list<mixed> $node
 * @return list<string>
 */
function collectIndexes(array $node, string $driver): array
{
    $indexes = [];

    foreach ($node as $key => $value) {
        if (
            $driver === 'pgsql'
            && $key === 'Index Name'
            && is_string($value)
            && $value !== ''
        ) {
            $indexes[] = $value;
        }

        if (
            $driver === 'mysql'
            && $key === 'key'
            && is_string($value)
            && $value !== ''
        ) {
            $indexes[] = $value;
        }

        if (is_array($value)) {
            $indexes = array_merge($indexes, collectIndexes($value, $driver));
        }
    }

    return $indexes;
}
