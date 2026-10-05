<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\EducationAdmissions\EducationAdmissionCatalogService;
use ChurchCMS\Modules\EducationAdmissions\EducationAdmissionRepository;
use ChurchCMS\Modules\EducationAdmissions\EducationAdmissionService;
use ChurchCMS\Modules\EducationPrograms\EducationProgramService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$programs = EducationProgramService::fromDatabase();
$service = EducationAdmissionService::fromDatabase();
$repository = EducationAdmissionRepository::fromDatabase();
$catalog = EducationAdmissionCatalogService::fromDatabase();

$siteKey = 'education-admissions-smoke';
$root = $organizations->ensureSiteRoot('Тестовая семинария', 'education', $siteKey);
$programId = $programs->createDraft(
    ownerOrganizationPublicId: $root->publicId,
    title: 'Богословие',
    educationLevel: 'высшее образование',
    studyForm: 'очная',
    durationMonths: 48,
    qualification: 'бакалавр',
    admissionNote: 'По результатам вступительных испытаний.',
    summary: 'Тестовая программа.',
    descriptionInput: 'Описание программы.',
    siteKey: $siteKey,
);
$programs->publish($programId, $siteKey);

$publicId = $service->createDraft(
    programPublicId: $programId,
    title: 'Приём 2026/2027',
    academicYear: '2026/2027',
    startsOn: '2026-06-01',
    endsOn: '2026-08-20',
    budgetSeats: 20,
    paidSeats: 10,
    tuitionNote: 'Стоимость определяется договором.',
    requirements: 'Документ об образовании и заявление.',
    entranceTests: 'Собеседование и русский язык.',
    contactNote: 'Приёмная комиссия.',
    sortOrder: 10,
    siteKey: $siteKey,
);

$record = $repository->find($publicId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->programPublicId !== $programId
    || $record->budgetSeats !== 20
    || $record->paidSeats !== 10
) {
    fwrite(STDERR, "Черновик приёмной кампании сохранён некорректно.\n");
    exit(1);
}
if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Черновик приёмной кампании попал в публичный каталог.\n");
    exit(1);
}

$service->publish($publicId, $siteKey);
$detail = $catalog->detail($publicId, $siteKey);
if (
    count($catalog->index($siteKey)) !== 1
    || ($detail['academic_year'] ?? '') !== '2026/2027'
    || ($detail['requirements'] ?? '') === ''
    || ($detail['entrance_tests'] ?? '') === ''
) {
    fwrite(STDERR, "Опубликованная приёмная кампания не попала в публичную проекцию.\n");
    exit(1);
}

$service->update(
    publicId: $publicId,
    programPublicId: $programId,
    title: 'Приём 2027',
    academicYear: '2027/2028',
    startsOn: '2027-05-15',
    endsOn: '2027-08-15',
    budgetSeats: 25,
    paidSeats: 5,
    tuitionNote: 'Новая стоимость.',
    requirements: 'Обновлённые требования.',
    entranceTests: 'Обновлённые испытания.',
    contactNote: 'Новые контакты.',
    sortOrder: 5,
    siteKey: $siteKey,
);
$updated = $repository->find($publicId, $siteKey);
if (
    $updated === null
    || $updated->title !== 'Приём 2027'
    || $updated->academicYear !== '2027/2028'
    || $updated->budgetSeats !== 25
    || $updated->sortOrder !== 5
) {
    fwrite(STDERR, "Обновление приёмной кампании не сохранилось.\n");
    exit(1);
}

try {
    $service->createDraft(
        programPublicId: $programId,
        title: 'Ошибочные даты',
        academicYear: '2028/2029',
        startsOn: '2028-09-01',
        endsOn: '2028-08-01',
        budgetSeats: 1,
        paidSeats: 0,
        tuitionNote: '',
        requirements: '',
        entranceTests: '',
        contactNote: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил окончание приёма раньше начала.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$foreignSiteKey = 'education-admissions-foreign';
$foreignRoot = $organizations->ensureSiteRoot('Чужая семинария', 'education', $foreignSiteKey);
$foreignProgramId = $programs->createDraft(
    ownerOrganizationPublicId: $foreignRoot->publicId,
    title: 'Чужая программа',
    educationLevel: 'высшее образование',
    studyForm: 'очная',
    durationMonths: 48,
    qualification: null,
    admissionNote: '',
    summary: '',
    descriptionInput: '',
    siteKey: $foreignSiteKey,
);

try {
    $service->createDraft(
        programPublicId: $foreignProgramId,
        title: 'Чужая кампания',
        academicYear: '2026/2027',
        startsOn: null,
        endsOn: null,
        budgetSeats: 0,
        paidSeats: 0,
        tuitionNote: '',
        requirements: '',
        entranceTests: '',
        contactNote: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил программу из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE education_admissions SET program_public_id = :program_public_id
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'program_public_id' => $foreignProgramId,
        'site_key' => $siteKey,
        'public_id' => $publicId,
    ]);
    fwrite(STDERR, "База разрешила программу из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$programs->unpublish($programId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Кампания опубликована при снятой с публикации программе.\n");
    exit(1);
}

$programs->publish($programId, $siteKey);
$service->unpublish($publicId, $siteKey);
if ($catalog->detail($publicId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятая с публикации кампания осталась публичной.\n");
    exit(1);
}

echo "Проверка приёмной кампании пройдена.\n";
