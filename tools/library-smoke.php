<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Library\LibraryCatalogService;
use ChurchCMS\Modules\Library\LibraryItemRepository;
use ChurchCMS\Modules\Library\LibraryItemService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = LibraryItemService::fromDatabase();
$repository = LibraryItemRepository::fromDatabase();
$catalog = LibraryCatalogService::fromDatabase();

$siteKey = 'library-smoke';
$root = $organizations->ensureSiteRoot('Тестовый приход библиотеки', 'parish', $siteKey);
$departmentId = $organizations->create(
    name: 'Просветительский отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $departmentId,
    title: 'История Русской Церкви',
    authorName: 'Тестовый автор',
    publisherName: 'Приходское издательство',
    publicationYear: 2025,
    isbn: '978-5-00000-001-1',
    shelfCode: 'ИСТ-001',
    availabilityNote: 'Доступна в читальном зале',
    summary: 'Тестовая карточка библиотечного издания.',
    descriptionInput: '<p>Описание книги.</p><script>alert(1)</script>',
    sortOrder: 20,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->ownerOrganizationPublicId !== $departmentId
    || $record->publicationYear !== 2025
    || $record->isbn !== '978-5-00000-001-1'
    || str_contains($record->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик библиотечного издания сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик библиотечного издания попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$publicList = $catalog->index($siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($publicList) !== 1
    || ($publicList[0]['public_id'] ?? '') !== $publicId
    || ($detail['author_name'] ?? '') !== 'Тестовый автор'
    || ($detail['description_html'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованное издание не попало в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $root->publicId,
    title: 'Обновлённая история Русской Церкви',
    authorName: 'Другой автор',
    publisherName: null,
    publicationYear: 2026,
    isbn: null,
    shelfCode: 'ИСТ-002',
    availabilityNote: 'Выдаётся на дом',
    summary: 'Обновлённая карточка.',
    descriptionInput: 'Безопасный обычный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->title !== 'Обновлённая история Русской Церкви'
    || $updated->ownerOrganizationPublicId !== $root->publicId
    || $updated->publicationYear !== 2026
    || $updated->sortOrder !== 5
    || !str_contains($updated->descriptionHtml, '<p>Безопасный обычный текст.</p>')
) {
    fwrite(STDERR, "Обновление библиотечного издания не сохранилось.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход библиотеки',
    'parish',
    'library-foreign',
);

try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        title: 'Недопустимое издание',
        authorName: null,
        publisherName: null,
        publicationYear: null,
        isbn: null,
        shelfCode: null,
        availabilityNote: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE library_items SET owner_organization_public_id = :owner
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила владельца из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятое с публикации издание осталось публичным.\n");
    exit(1);
}

foreach ([
    ['', null],
    ['Книга с неверным годом', 2300],
] as [$title, $year]) {
    try {
        $service->createDraft(
            ownerOrganizationPublicId: $root->publicId,
            title: $title,
            authorName: null,
            publisherName: null,
            publicationYear: $year,
            isbn: null,
            shelfCode: null,
            availabilityNote: null,
            summary: '',
            descriptionInput: '',
            siteKey: $siteKey,
        );
        fwrite(STDERR, "Сервис разрешил некорректную карточку библиотеки.\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: 'Книга с неверным ISBN',
        authorName: null,
        publisherName: null,
        publicationYear: null,
        isbn: 'invalid-isbn',
        shelfCode: null,
        availabilityNote: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил некорректный ISBN.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Library smoke OK\n";
