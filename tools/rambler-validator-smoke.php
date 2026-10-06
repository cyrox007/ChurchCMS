<?php

declare(strict_types=1);

define('CHURCHCMS_ROOT', dirname(__DIR__));
require CHURCHCMS_ROOT . '/core/RuntimeAutoloader.php';

ChurchCMS\Core\RuntimeAutoloader::register(CHURCHCMS_ROOT);

use ChurchCMS\Core\RamblerFeedValidator;
use ChurchCMS\Core\RamblerSyndicationRenderer;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\SyndicationFeed;

$entry = new SyndicationEntry(
    id: 'feed-item-1',
    url: 'https://example.test/news/1',
    title: 'Проверочная новость',
    description: 'Краткое описание',
    contentHtml: '<p>Полный текст публикации.</p>',
    publishedAt: new DateTimeImmutable('2026-10-06T04:00:00+00:00'),
    updatedAt: new DateTimeImmutable('2026-10-06T04:00:00+00:00'),
    author: 'Редакция',
    categories: ['Новости'],
    imageUrl: 'https://example.test/image.webp',
    imageMime: 'image/webp',
    targets: ['rambler'],
);
$feed = new SyndicationFeed(
    title: 'ChurchCMS',
    siteUrl: 'https://example.test/',
    description: 'Новости',
    entries: [$entry],
);
$renderer = new RamblerSyndicationRenderer();
$validator = new RamblerFeedValidator();
$xml = $renderer->render($feed);

$issues = $validator->validate($xml, $renderer->contentType());
$errors = array_values(array_filter(
    $issues,
    static fn(array $issue): bool => $issue['level'] === 'error',
));
if ($errors !== []) {
    throw new RuntimeException(
        'Штатный Rambler renderer не прошёл собственный валидатор: '
        . json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

$withoutContent = str_replace(
    '<content><![CDATA[<p>Полный текст публикации.</p>]]></content>',
    '',
    $xml,
);
$issues = $validator->validate($withoutContent, $renderer->contentType());
$codes = array_column($issues, 'code');
if (!in_array('item_content', $codes, true)) {
    throw new RuntimeException('Валидатор не обнаружил отсутствие обязательного полного текста.');
}

$issues = $validator->validate($xml, 'text/xml');
if (!in_array('content_type', array_column($issues, 'code'), true)) {
    throw new RuntimeException('Валидатор не обнаружил неверный Content-Type.');
}

$duplicateLink = str_replace(
    '</channel>',
    '<item><title>Дубль</title><link>https://example.test/news/1</link><content><![CDATA[<p>Текст</p>]]></content></item></channel>',
    $xml,
);
$issues = $validator->validate($duplicateLink, $renderer->contentType());
if (!in_array('item_link_duplicate', array_column($issues, 'code'), true)) {
    throw new RuntimeException('Валидатор не обнаружил повторяющуюся ссылку публикации.');
}

$unsupportedImage = str_replace(
    'type="image/webp"',
    'type="image/gif"',
    $xml,
);
$issues = $validator->validate($unsupportedImage, $renderer->contentType());
$warning = array_values(array_filter(
    $issues,
    static fn(array $issue): bool =>
        $issue['code'] === 'enclosure_image_type'
        && $issue['level'] === 'warning',
));
if ($warning === []) {
    throw new RuntimeException('Валидатор не предупредил о неподдерживаемом формате изображения.');
}

fwrite(STDOUT, "Валидатор Rambler-фида работает корректно.\n");
