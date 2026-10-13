<?php

declare(strict_types=1);

use ChurchCMS\Core\Smi2FeedValidator;
use ChurchCMS\Core\Smi2SyndicationRenderer;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\SyndicationFeed;

require dirname(__DIR__) . '/core.php';

$entry = new SyndicationEntry(
    id: 'publication:test-smi2',
    url: 'https://example.org/news/test-smi2',
    title: 'Проверочный материал СМИ2',
    description: 'Краткое описание материала.',
    contentHtml: '<p>Полный текст проверочного материала.</p>',
    publishedAt: new DateTimeImmutable('2026-10-13 12:00:00 UTC'),
    author: 'Редакция',
    categories: ['Новости'],
    imageUrl: 'https://example.org/media/test.webp',
    imageMime: 'image/webp',
    targets: ['smi2'],
);
$feed = new SyndicationFeed(
    title: 'Проверочный сайт',
    siteUrl: 'https://example.org/',
    description: 'Проверочная полнотекстовая лента',
    entries: [$entry],
);
$renderer = new Smi2SyndicationRenderer();
$validator = new Smi2FeedValidator();
$xml = $renderer->render($feed);
$issues = $validator->validate($xml, $renderer->contentType());
$errors = array_values(array_filter(
    $issues,
    static fn(array $issue): bool => ($issue['level'] ?? '') === 'error',
));

if ($errors !== []) {
    fwrite(STDERR, "Корректный фид СМИ2 не прошёл проверку:\n");
    foreach ($errors as $error) {
        fwrite(
            STDERR,
            '- ' . (string) ($error['code'] ?? 'unknown')
            . ': ' . (string) ($error['message'] ?? '') . "\n",
        );
    }
    exit(1);
}

$brokenXml = str_replace(
    '<content:encoded><![CDATA[<p>Полный текст проверочного материала.</p>]]></content:encoded>',
    '<content:encoded><![CDATA[]]></content:encoded>',
    $xml,
);
$brokenIssues = $validator->validate(
    $brokenXml,
    $renderer->contentType(),
);
$codes = array_column($brokenIssues, 'code');
if (!in_array('full_text_missing', $codes, true)) {
    fwrite(
        STDERR,
        "Валидатор не обнаружил отсутствие полного текста.\n",
    );
    exit(1);
}

if (!str_contains($xml, 'xmlns:content="http://purl.org/rss/1.0/modules/content/"')) {
    fwrite(STDERR, "В RSS отсутствует пространство имён content.\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Smoke-проверка полнотекстового фида СМИ2 успешно завершена.\n",
);
