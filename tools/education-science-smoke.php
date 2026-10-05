<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\EducationScience\EducationScienceCatalogService;
use ChurchCMS\Modules\EducationScience\EducationScienceRepository;
use ChurchCMS\Modules\EducationScience\EducationScienceService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = EducationScienceService::fromDatabase();
$repository = EducationScienceRepository::fromDatabase();
$catalog = EducationScienceCatalogService::fromDatabase();
$siteKey = 'education-science-smoke';

$root = $organizations->ensureSiteRoot('Тестовая образовательная организация', 'education', $siteKey);
$ownerId = $organizations->create(
    name: 'Научный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $ownerId,
    activityType: 'research',
    title: 'История приходского образования',
    startsOn: '2026-01-10',
    endsOn: '2026-12-20',
    summary: 'Исследовательский проект.',
    descriptionInput: '<p>Архивные материалы и исследования.</p><script>alert(1)</script>',
    externalUrl: 'https://example.org/research',
    sortOrder: 10,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->activityType !== 'research'
    || $record->ownerOrganizationPublicId !== $ownerId
    || str_contains($record->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик научной деятельности сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик научной деятельности попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    ($detail['activity_type'] ?? '') !== 'research'
    || ($detail['external_url'] ?? '') !== 'https://example.org/research'
    || ($detail['description_html'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованная научная активность не попала в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $ownerId,
    activityType: 'publication',
    title: 'Сборник научных статей',
    startsOn: '2026-06-01',
    endsOn: null,
    summary: 'Обновлённая запись.',
    descriptionInput: 'Сборник опубликован.',
    externalUrl: null,
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if ($updated === null || $updated->activityType !== 'publication' || $updated->sortOrder !== 5) {
    fwrite(STDERR, "Обновление научной активности не сохранилось.\n");
    exit(1);
}

foreach ([
    ['type' => 'unknown', 'starts' => null, 'ends' => null, 'url' => null],
    ['type' => 'research', 'starts' => '2026-12-01', 'ends' => '2026-01-01', 'url' => null],
    ['type' => 'research', 'starts' => null, 'ends' => null, 'url' => 'http://example.org/insecure'],
] as $invalid) {
    try {
        $service->createDraft(
            ownerOrganizationPublicId: $ownerId,
            activityType: $invalid['type'],
            title: 'Недопустимая запись',
            startsOn: $invalid['starts'],
            endsOn: $invalid['ends'],
            summary: '',
            descriptionInput: '',
            externalUrl: $invalid['url'],
            siteKey: $siteKey,
        );
        fwrite(STDERR, "Сервис разрешил некорректную научную активность.\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая образовательная организация',
    'education',
    'education-science-foreign',
);
try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        activityType: 'research',
        title: 'Недопустимая запись',
        startsOn: null,
        endsOn: null,
        summary: '',
        descriptionInput: '',
        externalUrl: null,
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE education_science_activities SET owner_organization_public_id = :owner
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
    fwrite(STDERR, "Снятая с публикации научная активность осталась публичной.\n");
    exit(1);
}

$theme = ThemeRenderer::fromConfig()->activeTheme();
foreach (['admin.education_science', 'education_science.index', 'education_science.show'] as $logicalName) {
    if ($theme->template($logicalName) === null) {
        fwrite(STDERR, "В теме не зарегистрирован шаблон {$logicalName}.\n");
        exit(1);
    }
}

echo "Проверка научной деятельности пройдена.\n";
