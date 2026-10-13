<?php

declare(strict_types=1);

define('CHURCHCMS_ROOT', dirname(__DIR__));
require CHURCHCMS_ROOT . '/core/RuntimeAutoloader.php';
ChurchCMS\Core\RuntimeAutoloader::register(CHURCHCMS_ROOT);

use ChurchCMS\Core\NewsMailFeedValidator;
use ChurchCMS\Core\NewsMailSyndicationRenderer;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\SyndicationFeed;

$entry = new SyndicationEntry(
    id: 'publication:news-mail-test',
    url: 'https://example.org/news/test',
    title: 'Проверочный материал',
    description: 'Краткое описание.',
    contentHtml: '<p>Полный текст материала.</p>',
    publishedAt: new DateTimeImmutable('2026-10-13 12:00:00 UTC'),
    author: 'Редакция',
    categories: ['Новости'],
    imageUrl: 'https://example.org/media/test.webp',
    imageMime: 'image/webp',
    targets: ['news_mail'],
);
$feed = new SyndicationFeed(
    title: 'Проверочный сайт',
    siteUrl: 'https://example.org/',
    description: 'Проверочный фид',
    entries: [$entry],
);
$renderer = new NewsMailSyndicationRenderer();
$validator = new NewsMailFeedValidator();
$xml = $renderer->render($feed);
$issues = $validator->validate($xml, $renderer->contentType());
$errors = array_filter(
    $issues,
    static fn(array $issue): bool => ($issue['level'] ?? '') === 'error',
);
if ($errors !== []) {
    throw new RuntimeException(
        'Корректный технический фид Новости Mail не прошёл проверку: '
        . json_encode(array_values($errors), JSON_UNESCAPED_UNICODE),
    );
}
$broken = str_replace(
    '<content:encoded><![CDATA[<p>Полный текст материала.</p>]]></content:encoded>',
    '<content:encoded><![CDATA[]]></content:encoded>',
    $xml,
);
$codes = array_column(
    $validator->validate($broken, $renderer->contentType()),
    'code',
);
if (!in_array('full_text_missing', $codes, true)) {
    throw new RuntimeException('Не обнаружено отсутствие полного текста.');
}
fwrite(STDOUT, "Smoke-проверка контура Новости Mail успешно завершена.\n");
