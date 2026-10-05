<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Ministries\MinistryCatalogService;
use ChurchCMS\Modules\Ministries\MinistryRepository;
use ChurchCMS\Modules\Ministries\MinistryService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = MinistryService::fromDatabase();
$repository = MinistryRepository::fromDatabase();
$catalog = MinistryCatalogService::fromDatabase();

$siteKey = 'ministries-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовый приход служений',
    'parish',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Молодёжное служение',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $departmentId,
    title: 'Молодёжный отдел',
    shortTitle: 'Молодёжь',
    leaderName: 'Иван Иванов',
    contactEmail: 'youth@example.test',
    contactPhone: '+7 900 000-00-00',
    summary: 'Работа с молодёжью прихода.',
    descriptionInput: '<p>Встречи каждую неделю.</p><script>alert(1)</script>',
    sortOrder: 20,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->ownerOrganizationPublicId !== $departmentId
    || $record->shortTitle !== 'Молодёжь'
    || str_contains($record->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик служения сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$publicList = $catalog->index($siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($publicList) !== 1
    || ($publicList[0]['public_id'] ?? '') !== $publicId
    || ($detail['description_html'] ?? '') === ''
    || ($detail['leader_name'] ?? '') !== 'Иван Иванов'
) {
    fwrite(STDERR, "Опубликованное служение не попало в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $root->publicId,
    title: 'Приходское молодёжное служение',
    shortTitle: null,
    leaderName: 'Пётр Петров',
    contactEmail: null,
    contactPhone: null,
    summary: 'Обновлённое описание.',
    descriptionInput: 'Безопасный обычный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->title !== 'Приходское молодёжное служение'
    || $updated->ownerOrganizationPublicId !== $root->publicId
    || $updated->sortOrder !== 5
    || !str_contains($updated->descriptionHtml, '<p>Безопасный обычный текст.</p>')
) {
    fwrite(STDERR, "Обновление служения не сохранилось.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход служений',
    'parish',
    'ministries-foreign',
);

try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        title: 'Недопустимое служение',
        shortTitle: null,
        leaderName: null,
        contactEmail: null,
        contactPhone: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца служения из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE ministries SET owner_organization_public_id = :owner
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила владельца служения из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятое служение осталось публичным.\n");
    exit(1);
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: '',
        shortTitle: null,
        leaderName: null,
        contactEmail: null,
        contactPhone: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил пустое название служения.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: 'Некорректный контакт',
        shortTitle: null,
        leaderName: null,
        contactEmail: 'not-an-email',
        contactPhone: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил некорректный email.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Ministries smoke OK\n";
