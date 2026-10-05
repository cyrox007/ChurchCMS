<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\SundaySchool\SundaySchoolCatalogService;
use ChurchCMS\Modules\SundaySchool\SundaySchoolRepository;
use ChurchCMS\Modules\SundaySchool\SundaySchoolService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = SundaySchoolService::fromDatabase();
$repository = SundaySchoolRepository::fromDatabase();
$catalog = SundaySchoolCatalogService::fromDatabase();

$siteKey = 'sunday-school-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовый приход воскресной школы',
    'parish',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Просветительский отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $departmentId,
    title: 'Воскресная школа прихода',
    leaderName: 'Мария Иванова',
    locationName: 'Приходской дом',
    contactEmail: 'school@example.test',
    contactPhone: '+7 900 000-00-01',
    ageInfo: '7–14 лет',
    summary: 'Занятия по воскресеньям.',
    descriptionInput: '<p>Закон Божий, история Церкви и творчество.</p><script>alert(1)</script>',
    sortOrder: 20,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->ownerOrganizationPublicId !== $departmentId
    || $record->ageInfo !== '7–14 лет'
    || str_contains($record->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик воскресной школы сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик воскресной школы попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$publicList = $catalog->index($siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($publicList) !== 1
    || ($publicList[0]['public_id'] ?? '') !== $publicId
    || ($detail['leader_name'] ?? '') !== 'Мария Иванова'
    || ($detail['description_html'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованная воскресная школа не попала в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $root->publicId,
    title: 'Приходская воскресная школа',
    leaderName: 'Ирина Петрова',
    locationName: 'Учебный класс',
    contactEmail: null,
    contactPhone: null,
    ageInfo: '6–16 лет',
    summary: 'Обновлённая карточка.',
    descriptionInput: 'Безопасный обычный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->title !== 'Приходская воскресная школа'
    || $updated->ownerOrganizationPublicId !== $root->publicId
    || $updated->leaderName !== 'Ирина Петрова'
    || $updated->sortOrder !== 5
    || !str_contains($updated->descriptionHtml, '<p>Безопасный обычный текст.</p>')
) {
    fwrite(STDERR, "Обновление воскресной школы не сохранилось.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход воскресной школы',
    'parish',
    'sunday-school-foreign',
);

try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        title: 'Недопустимая школа',
        leaderName: null,
        locationName: null,
        contactEmail: null,
        contactPhone: null,
        ageInfo: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца школы из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE sunday_schools SET owner_organization_public_id = :owner
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила владельца школы из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятая школа осталась публичной.\n");
    exit(1);
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: '',
        leaderName: null,
        locationName: null,
        contactEmail: null,
        contactPhone: null,
        ageInfo: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил пустое название школы.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: 'Школа с ошибкой контакта',
        leaderName: null,
        locationName: null,
        contactEmail: 'not-an-email',
        contactPhone: null,
        ageInfo: null,
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил некорректный email.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Sunday school smoke OK\n";
