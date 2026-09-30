<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Pages\PageRepository;
use ChurchCMS\Modules\Pages\PageRevisionService;
use ChurchCMS\Modules\Pages\PageService;
use ChurchCMS\Modules\Pages\PageStatus;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationRevisionService;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Publications\PublicationStatus;
use ChurchCMS\Modules\Publications\PublicationTaxonomyService;
use ChurchCMS\Modules\Publications\PublicationType;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход revisions',
    'parish',
);

$pageService = PageService::fromDatabase();
$pageId = $pageService->createDraft(
    title: 'Первая страница',
    slug: 'first-page',
    bodyInput: 'Первый текст',
    ownerOrganizationPublicId: $root->publicId,
);

$pageService->update(
    publicId: $pageId,
    title: 'Вторая страница',
    slug: 'second-page',
    bodyInput: 'Второй текст',
    ownerOrganizationPublicId: $root->publicId,
);

$pageRevisions = PageRevisionService::fromDatabase();
$pageHistory = $pageRevisions->history($pageId);

if (
    count($pageHistory) !== 1
    || $pageHistory[0]->revisionNumber !== 1
    || ($pageHistory[0]->snapshot['title'] ?? null)
        !== 'Первая страница'
) {
    fwrite(STDERR, "Первый revision страницы сформирован неверно.\n");
    exit(1);
}

$pageService->publish($pageId);
$pageService->update(
    publicId: $pageId,
    title: 'Третья страница',
    slug: 'third-page',
    bodyInput: 'Третий текст',
    ownerOrganizationPublicId: $root->publicId,
);

$pageHistory = $pageRevisions->history($pageId);
if (
    count($pageHistory) !== 2
    || ($pageHistory[0]->snapshot['title'] ?? null)
        !== 'Вторая страница'
) {
    fwrite(STDERR, "Второй revision страницы сформирован неверно.\n");
    exit(1);
}

$pageRevisions->restore(
    $pageId,
    $pageHistory[1]->publicId,
);

$page = PageRepository::fromDatabase()->findByPublicId($pageId);
if (
    $page === null
    || $page->title !== 'Первая страница'
    || $page->slug !== 'first-page'
    || $page->status !== PageStatus::Published
) {
    fwrite(STDERR, "Restore страницы изменил данные или lifecycle неверно.\n");
    exit(1);
}

$pageHistory = $pageRevisions->history($pageId);
if (
    count($pageHistory) !== 3
    || ($pageHistory[0]->snapshot['title'] ?? null)
        !== 'Третья страница'
) {
    fwrite(STDERR, "Restore страницы не сохранил предыдущую текущую версию.\n");
    exit(1);
}

$beforeInvalid = count($pageHistory);

try {
    $pageService->update(
        publicId: $pageId,
        title: 'Невалидная страница',
        slug: 'invalid-page',
        bodyInput: 'Не должно сохраниться',
        parentPublicId: $pageId,
        ownerOrganizationPublicId: $root->publicId,
    );
    fwrite(STDERR, "Самоссылка страницы ошибочно разрешена.\n");
    exit(1);
} catch (\InvalidArgumentException) {
}

if (
    count($pageRevisions->history($pageId))
    !== $beforeInvalid
) {
    fwrite(STDERR, "Неудачная транзакция оставила revision страницы.\n");
    exit(1);
}

$publicationService = PublicationService::fromDatabase();
$publicationId = $publicationService->createDraft(
    title: 'Первая публикация',
    slug: 'first-publication',
    type: PublicationType::News,
    excerpt: 'Первое описание',
    bodyHtml: 'Первый текст публикации',
    authorName: 'Автор 1',
    syndicationTargets: ['rss'],
    commentsEnabled: false,
    categoryNames: ['Новости'],
    tagNames: ['Первый'],
    ownerOrganizationPublicId: $root->publicId,
);

$publicationService->update(
    publicId: $publicationId,
    title: 'Вторая публикация',
    slug: 'second-publication',
    type: PublicationType::Article,
    excerpt: 'Второе описание',
    bodyHtml: 'Второй текст публикации',
    authorName: 'Автор 2',
    syndicationTargets: ['rss', 'diocese'],
    commentsEnabled: true,
    categoryNames: ['Статьи'],
    tagNames: ['Второй'],
    ownerOrganizationPublicId: $root->publicId,
);

$publicationRevisions =
    PublicationRevisionService::fromDatabase();
$publicationHistory =
    $publicationRevisions->history($publicationId);

if (
    count($publicationHistory) !== 1
    || ($publicationHistory[0]->snapshot['title'] ?? null)
        !== 'Первая публикация'
    || ($publicationHistory[0]->snapshot['categories'] ?? [])
        !== ['Новости']
) {
    fwrite(STDERR, "Первый revision публикации сформирован неверно.\n");
    exit(1);
}

$publicationService->publish($publicationId);

$publicationService->update(
    publicId: $publicationId,
    title: 'Третья публикация',
    slug: 'third-publication',
    type: PublicationType::Interview,
    excerpt: 'Третье описание',
    bodyHtml: 'Третий текст публикации',
    authorName: 'Автор 3',
    syndicationTargets: [],
    commentsEnabled: false,
    categoryNames: ['Интервью'],
    tagNames: ['Третий'],
    ownerOrganizationPublicId: $root->publicId,
);

$publicationHistory =
    $publicationRevisions->history($publicationId);

$publicationRevisions->restore(
    $publicationId,
    $publicationHistory[1]->publicId,
);

$publication = PublicationRepository::fromDatabase()
    ->findByPublicId($publicationId);
$taxonomy = $publication !== null
    ? PublicationTaxonomyService::fromDatabase()
        ->forPublication($publication->id)
    : ['categories' => [], 'tags' => []];

if (
    $publication === null
    || $publication->title !== 'Первая публикация'
    || $publication->slug !== 'first-publication'
    || $publication->type !== PublicationType::News
    || $publication->status !== PublicationStatus::Published
    || PublicationTaxonomyService::names(
        $taxonomy['categories'],
    ) !== 'Новости'
    || PublicationTaxonomyService::names(
        $taxonomy['tags'],
    ) !== 'Первый'
) {
    fwrite(STDERR, "Restore публикации восстановил данные неверно.\n");
    exit(1);
}

$publicationHistory =
    $publicationRevisions->history($publicationId);
if (
    count($publicationHistory) !== 3
    || ($publicationHistory[0]->snapshot['title'] ?? null)
        !== 'Третья публикация'
) {
    fwrite(
        STDERR,
        "Restore публикации не сохранил текущую версию в истории.\n",
    );
    exit(1);
}

echo "Content revisions smoke OK\n";
