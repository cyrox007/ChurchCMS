<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\EducationDisclosures\EducationDisclosureCatalogService;
use ChurchCMS\Modules\EducationDisclosures\EducationDisclosureRepository;
use ChurchCMS\Modules\EducationDisclosures\EducationDisclosureService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = EducationDisclosureService::fromDatabase();
$repository = EducationDisclosureRepository::fromDatabase();
$catalog = EducationDisclosureCatalogService::fromDatabase();
$siteKey = 'education-disclosures-smoke';

$root = $organizations->ensureSiteRoot('Тестовая образовательная организация', 'education', $siteKey);
$ownerId = $organizations->create(
    name: 'Образовательный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $ownerId,
    sectionKey: 'basic_information',
    title: 'Основные сведения',
    summary: 'Краткая информация об организации.',
    bodyInput: '<p>Сведения об образовательной организации.</p><script>alert(1)</script>',
    sortOrder: 10,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->ownerOrganizationPublicId !== $ownerId
    || $record->sectionKey !== 'basic_information'
    || str_contains($record->bodyHtml, '<script')
) {
    fwrite(STDERR, "Черновик обязательных сведений сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик обязательных сведений попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (($detail['section_key'] ?? '') !== 'basic_information' || ($detail['body_html'] ?? '') === '') {
    fwrite(STDERR, "Опубликованный раздел не попал в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $ownerId,
    sectionKey: 'general_information',
    title: 'Общие сведения',
    summary: 'Обновлённое описание.',
    bodyInput: 'Безопасный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if ($updated === null || $updated->sectionKey !== 'general_information' || $updated->sortOrder !== 5) {
    fwrite(STDERR, "Обновление обязательных сведений не сохранилось.\n");
    exit(1);
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $ownerId,
        sectionKey: 'general_information',
        title: 'Дубликат',
        summary: '',
        bodyInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "База разрешила дублирующий ключ раздела у одного владельца.\n");
    exit(1);
} catch (PDOException) {
}

$foreignRoot = $organizations->ensureSiteRoot('Чужая организация', 'education', 'education-disclosures-foreign');
try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        sectionKey: 'foreign',
        title: 'Недопустимый раздел',
        summary: '',
        bodyInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE education_disclosures SET owner_organization_public_id = :owner WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute(['owner' => $foreignRoot->publicId, 'site_key' => $siteKey, 'public_id' => $publicId]);
    fwrite(STDERR, "База разрешила владельца из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятый с публикации раздел остался публичным.\n");
    exit(1);
}

$theme = ThemeRenderer::fromConfig()->activeTheme();
foreach ([
    'admin.education_programs',
    'education_programs.index',
    'education_programs.show',
    'admin.education_staff',
    'education_staff.index',
    'education_staff.show',
    'admin.education_admissions',
    'education_admissions.index',
    'education_admissions.show',
    'admin.education_disclosures',
    'education_disclosures.index',
    'education_disclosures.show',
] as $logicalName) {
    if ($theme->template($logicalName) === null) {
        fwrite(STDERR, "В теме не зарегистрирован шаблон {$logicalName}.\n");
        exit(1);
    }
}

echo "Проверка обязательных сведений пройдена.\n";
