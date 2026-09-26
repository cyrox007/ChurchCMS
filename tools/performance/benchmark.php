#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Comments\CommentRepository;
use ChurchCMS\Modules\Publications\PublicationRepository;

$root = dirname(__DIR__, 2);
require $root . '/core.php';

$options = getopt('', [
    'site::',
    'iterations::',
]);

$siteKey = trim((string) ($options['site'] ?? 'benchmark'));
$iterations = max(5, min(5000, (int) ($options['iterations'] ?? 200)));

if ($siteKey !== 'benchmark') {
    fwrite(STDERR, "Benchmark работает только с изолированным site_key benchmark.\n");
    exit(2);
}

$pdo = DatabaseManager::getInstance()->connection();
$publications = new PublicationRepository($pdo);
$comments = new CommentRepository($pdo);
$total = $publications->countPublished($siteKey);

if ($total < 1) {
    fwrite(STDERR, "Сначала подготовьте тестовые данные через tools/performance/seed.php.\n");
    exit(2);
}

$detail = $publications->findPublishedBySlug('benchmark-000001', $siteKey);
if ($detail === null) {
    fwrite(STDERR, "Контрольная публикация benchmark-000001 не найдена.\n");
    exit(1);
}

/**
 * @return array{p50_ms:float,p95_ms:float,p99_ms:float,max_ms:float,mean_ms:float}
 */
function measure(int $iterations, callable $operation): array
{
    $samples = [];

    for ($i = 0; $i < $iterations; $i++) {
        $started = hrtime(true);
        $operation($i);
        $samples[] = (hrtime(true) - $started) / 1_000_000;
    }

    sort($samples, SORT_NUMERIC);
    $count = count($samples);
    $percentile = static function (float $ratio) use ($samples, $count): float {
        $index = (int) ceil($count * $ratio) - 1;
        return $samples[max(0, min($count - 1, $index))];
    };

    return [
        'p50_ms' => round($percentile(0.50), 3),
        'p95_ms' => round($percentile(0.95), 3),
        'p99_ms' => round($percentile(0.99), 3),
        'max_ms' => round(max($samples), 3),
        'mean_ms' => round(array_sum($samples) / $count, 3),
    ];
}

$deepOffset = max(0, min($total - 1, (int) floor($total * 0.75)));

$results = [
    'site_key' => $siteKey,
    'publications' => $total,
    'iterations' => $iterations,
    'driver' => (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
    'php' => PHP_VERSION,
    'measurements' => [
        'archive_first_page' => measure(
            $iterations,
            static fn(): array => $publications->published($siteKey, 20, 0),
        ),
        'archive_deep_page' => measure(
            $iterations,
            static fn(): array => $publications->published($siteKey, 20, $deepOffset),
        ),
        'publication_detail' => measure(
            $iterations,
            static fn(): mixed => $publications->findPublishedBySlug('benchmark-000001', $siteKey),
        ),
        'approved_comments' => measure(
            $iterations,
            static fn(): array => $comments->approvedForPublication($detail->id, 100),
        ),
    ],
];

echo json_encode(
    $results,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
) . PHP_EOL;
