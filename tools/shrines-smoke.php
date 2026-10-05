<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Shrines\ShrineCatalogService;
use ChurchCMS\Modules\Shrines\ShrineRepository;
use ChurchCMS\Modules\Shrines\ShrineService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = ShrineService::fromDatabase();
$repository = ShrineRepository::fromDatabase();
$catalog = ShrineCatalogService::fromDatabase();

$siteKey = 'shrines-smoke';
$root = $organizations->ensureSiteRoot('Тестовый приход святынь', 'parish', $siteKey);
$chapelId = $organizations->create(
    name: 'Часовня',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $chapelId,
    shrineType: 'icon',
    title: 'Почитаемая икона',
    subtitle: 'Приходская святыня',
    locationName: 'Главный храм',
    summary: 'Краткое описание святыни.',
    descriptionInput: '<p>История иконы.</p><script>alert(1)</script>',
    sortOrder: 10,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->shrineType !== 'icon'
    || $record->ownerOrganizationPublicId !== $chapelId
    || str_contains($record->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик святыни сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик святыни попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$list = $catalog->index($siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($list) !== 1
    || ($list[0]['public_id'] ?? '') !== $publicId
    || ($detail['shrine_type'] ?? '') !== 'icon'
    || ($detail['description_html'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованная святыня не попала в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $root->publicId,
    shrineType: 'relics',
    title: 'Частица святых мощей',
    subtitle: null,
    locationName: 'Алтарная часть храма',
    summary: 'Обновлённое описание.',
    descriptionInput: 'Безопасный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->shrineType !== 'relics'
    || $updated->ownerOrganizationPublicId !== $root->publicId
    || $updated->sortOrder !== 5
    || !str_contains($updated->descriptionHtml, '<p>Безопасный текст.</p>')
) {
    fwrite(STDERR, "Обновление святыни не сохранилось.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход святынь',
    'parish',
    'shrines-foreign',
);
try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        shrineType: 'other',
        title: 'Недопустимая святыня',
        subtitle: null,
        locationName: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца святыни из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE shrines SET owner_organization_public_id = :owner
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила владельца святыни из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        shrineType: 'unknown',
        title: 'Некорректный тип',
        subtitle: null,
        locationName: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил неизвестный тип святыни.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятая святыня осталась публичной.\n");
    exit(1);
}

echo "Shrines smoke OK\n";
