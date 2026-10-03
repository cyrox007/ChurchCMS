<?php

declare(strict_types=1);

use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Search\PublicSearchService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'search-smoke';
$root = OrganizationService::fromDatabase()->ensureSiteRoot(
    'Тестовый приход поиска',
    'parish',
    $siteKey,
);

$documents = DocumentService::fromDatabase();

$publicId = $documents->createDraft(
    title: 'Указ о приходском собрании',
    ownerOrganizationPublicId: $root->publicId,
    siteKey: $siteKey,
    documentType: 'decree',
    documentNumber: '42',
    summary: 'Публичный документ для проверки поиска.',
);
$documents->publish($publicId, $siteKey);
$documents->setVisibility($publicId, 'public', $siteKey);

$privateId = $documents->createDraft(
    title: 'Указ внутренний служебный',
    ownerOrganizationPublicId: $root->publicId,
    siteKey: $siteKey,
    documentType: 'decree',
    summary: 'Этот документ не должен попадать в публичный поиск.',
);
$documents->publish($privateId, $siteKey);

$result = PublicSearchService::fromDatabase()->search('Указ', $siteKey);
$urls = array_map(
    static fn(array $item): string => (string) ($item['url'] ?? ''),
    $result['items'],
);

$publicUrl = '/documents/' . rawurlencode($publicId);
$privateUrl = '/documents/' . rawurlencode($privateId);

if (!in_array($publicUrl, $urls, true)) {
    fwrite(STDERR, "Публичный документ не найден поиском.\n");
    exit(1);
}

if (in_array($privateUrl, $urls, true)) {
    fwrite(STDERR, "Приватный документ попал в публичный поиск.\n");
    exit(1);
}

try {
    PublicSearchService::fromDatabase()->search('x', $siteKey);
    fwrite(STDERR, "Поиск принял слишком короткий запрос.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Публичный поиск: OK\n";
