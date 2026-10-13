<?php

declare(strict_types=1);

define('CHURCHCMS_ROOT', dirname(__DIR__));
require CHURCHCMS_ROOT . '/core/RuntimeAutoloader.php';

ChurchCMS\Core\RuntimeAutoloader::register(CHURCHCMS_ROOT);

use ChurchCMS\Core\Smi2FeedValidator;
use ChurchCMS\Core\Smi2SyndicationRenderer;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\SyndicationFeed;

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
    throw new RuntimeException(
        'Корректный фид СМИ2 не прошёл проверку: '
        . json_encode(
            $errors,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ),
    );
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
if (!in_array(
    'full_text_missing',
    array_column($brokenIssues, 'code'),
    true,
)) {
    throw new RuntimeException(
        'Валидатор не обнаружил отсутствие полного текста.',
    );
}

if (!str_contains(
    $xml,
    'xmlns:content="http://purl.org/rss/1.0/modules/content/"',
)) {
    throw new RuntimeException(
        'В RSS отсутствует пространство имён content.',
    );
}

fwrite(
    STDOUT,
    "Smoke-проверка полнотекстового фида СМИ2 успешно завершена.\n",
);
