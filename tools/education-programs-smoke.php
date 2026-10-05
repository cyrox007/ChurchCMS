<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\EducationPrograms\EducationProgramCatalogService;
use ChurchCMS\Modules\EducationPrograms\EducationProgramRepository;
use ChurchCMS\Modules\EducationPrograms\EducationProgramService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$service = EducationProgramService::fromDatabase();
$repository = EducationProgramRepository::fromDatabase();
$catalog = EducationProgramCatalogService::fromDatabase();

$siteKey = 'education-programs-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовая образовательная организация',
    'education',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Учебный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$publicId = $service->createDraft(
    ownerOrganizationPublicId: $departmentId,
    title: 'Основы православной культуры',
    educationLevel: 'дополнительное образование',
    studyForm: 'очная',
    durationMonths: 9,
    qualification: 'свидетельство об освоении программы',
    admissionNote: 'Приём по заявлению.',
    summary: 'Годовая образовательная программа.',
    descriptionInput: '<p>История, культура и основы вероучения.</p><script>alert(1)</script>',
    sortOrder: 20,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->ownerOrganizationPublicId !== $departmentId
    || $record->durationMonths !== 9
    || str_contains($record->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик образовательной программы сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик образовательной программы попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$publicList = $catalog->index($siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($publicList) !== 1
    || ($publicList[0]['public_id'] ?? '') !== $publicId
    || ($detail['education_level'] ?? '') !== 'дополнительное образование'
    || ($detail['study_form'] ?? '') !== 'очная'
    || ($detail['description_html'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованная образовательная программа не попала в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    ownerOrganizationPublicId: $root->publicId,
    title: 'Православная культура и история',
    educationLevel: 'дополнительное образование детей и взрослых',
    studyForm: 'очно-заочная',
    durationMonths: 12,
    qualification: null,
    admissionNote: 'Приём после собеседования.',
    summary: 'Обновлённая программа.',
    descriptionInput: 'Безопасный обычный текст.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->title !== 'Православная культура и история'
    || $updated->ownerOrganizationPublicId !== $root->publicId
    || $updated->studyForm !== 'очно-заочная'
    || $updated->durationMonths !== 12
    || $updated->sortOrder !== 5
    || !str_contains($updated->descriptionHtml, '<p>Безопасный обычный текст.</p>')
) {
    fwrite(STDERR, "Обновление образовательной программы не сохранилось.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая образовательная организация',
    'education',
    'education-programs-foreign',
);

try {
    $service->createDraft(
        ownerOrganizationPublicId: $foreignRoot->publicId,
        title: 'Недопустимая программа',
        educationLevel: 'общее образование',
        studyForm: 'очная',
        durationMonths: 12,
        qualification: null,
        admissionNote: '',
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил владельца программы из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE education_programs SET owner_organization_public_id = :owner
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила владельца программы из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятая с публикации программа осталась публичной.\n");
    exit(1);
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: '',
        educationLevel: 'общее образование',
        studyForm: 'очная',
        durationMonths: 12,
        qualification: null,
        admissionNote: '',
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил пустое название программы.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $service->createDraft(
        ownerOrganizationPublicId: $root->publicId,
        title: 'Слишком длинная программа',
        educationLevel: 'общее образование',
        studyForm: 'очная',
        durationMonths: 241,
        qualification: null,
        admissionNote: '',
        summary: '',
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил недопустимую длительность обучения.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Проверка образовательных программ пройдена.\n";
