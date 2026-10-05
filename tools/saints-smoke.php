<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Saints\SaintCatalogService;
use ChurchCMS\Modules\Saints\SaintRepository;
use ChurchCMS\Modules\Saints\SaintService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = SaintService::fromDatabase();
$repository = SaintRepository::fromDatabase();
$catalog = SaintCatalogService::fromDatabase();

$siteKey = 'saints-smoke';
$root = $organizations->ensureSiteRoot('Тестовый приход святых', 'parish', $siteKey);
$chapelId = $organizations->create(
    name: 'Приписной храм',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $chapelId,
    displayName: 'Святитель Тестовый',
    saintRank: 'святитель',
    secularName: 'Иоанн',
    commemorationText: '1 января',
    summary: 'Краткое житие.',
    biographyInput: '<p>Полное житие.</p><script>alert(1)</script>',
    sortOrder: 10,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->ownerOrganizationPublicId !== $chapelId
    || $record->saintRank !== 'святитель'
    || str_contains($record->biographyHtml, '<script')
) {
    fwrite(STDERR, "Черновик карточки святого сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик карточки святого попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$list = $catalog->index($siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($list) !== 1
    || ($list[0]['public_id'] ?? '') !== $publicId
    || ($detail['display_name'] ?? '') !== 'Святитель Тестовый'
    || ($detail['biography_html'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованная карточка святого не попала в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $root->publicId,
    displayName: 'Преподобный Тестовый',
    saintRank: 'преподобный',
    secularName: null,
    commemorationText: '2 января и 3 февраля',
    summary: 'Обновлённое краткое житие.',
    biographyInput: 'Безопасный обычный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->displayName !== 'Преподобный Тестовый'
    || $updated->ownerOrganizationPublicId !== $root->publicId
    || $updated->sortOrder !== 5
    || !str_contains($updated->biographyHtml, '<p>Безопасный обычный текст.</p>')
) {
    fwrite(STDERR, "Обновление карточки святого не сохранилось.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход святых',
    'parish',
    'saints-foreign',
);
try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        displayName: 'Недопустимая карточка',
        saintRank: null,
        secularName: null,
        commemorationText: null,
        summary: '',
        biographyInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца карточки святого из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE saints SET owner_organization_public_id = :owner
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила владельца карточки святого из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        displayName: '',
        saintRank: null,
        secularName: null,
        commemorationText: null,
        summary: '',
        biographyInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил пустое имя святого.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятая карточка святого осталась публичной.\n");
    exit(1);
}

echo "Saints smoke OK\n";
