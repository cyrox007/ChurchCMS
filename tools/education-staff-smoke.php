<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\EducationStaff\EducationStaffCatalogService;
use ChurchCMS\Modules\EducationStaff\EducationStaffRepository;
use ChurchCMS\Modules\EducationStaff\EducationStaffService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\People\PeopleService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$siteKey = 'education-staff-smoke';
$organizations = OrganizationService::fromDatabase();
$people = PeopleService::fromDatabase();
$service = EducationStaffService::fromDatabase();
$repository = EducationStaffRepository::fromDatabase();
$catalog = EducationStaffCatalogService::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовая духовная школа',
    'education',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Учебная часть',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$personId = $people->createPerson(
    displayName: 'Иван Петрович Преподаватель',
    ownerOrganizationPublicId: $departmentId,
    siteKey: $siteKey,
    biographyHtml: '<p>Преподаватель церковной истории.</p>',
);
$secondPersonId = $people->createPerson(
    displayName: 'Алексей Сергеевич Богослов',
    ownerOrganizationPublicId: $departmentId,
    siteKey: $siteKey,
);

$chairId = $service->createChair(
    ownerOrganizationPublicId: $departmentId,
    name: 'Кафедра церковной истории',
    shortName: 'КЦИ',
    descriptionInput: '<p>История Церкви и церковная археология.</p><script>alert(1)</script>',
    sortOrder: 10,
    siteKey: $siteKey,
);
$chair = $repository->findChair($chairId, $siteKey);
if (
    $chair === null
    || $chair->status !== 'draft'
    || $chair->ownerOrganizationPublicId !== $departmentId
    || str_contains($chair->descriptionHtml, '<script')
) {
    fwrite(STDERR, "Черновик кафедры сохранён некорректно.\n");
    exit(1);
}

if ($catalog->index($siteKey) !== [] || $catalog->detail($chairId, $siteKey) !== null) {
    fwrite(STDERR, "Черновик кафедры попал в публичную проекцию.\n");
    exit(1);
}

$assignmentId = $service->assignTeacher(
    chairPublicId: $chairId,
    personPublicId: $personId,
    positionTitle: 'Доцент',
    academicDegree: 'кандидат богословия',
    academicTitle: 'доцент',
    disciplines: "История Русской Церкви\nЦерковная археология",
    sortOrder: 5,
    siteKey: $siteKey,
);
$assignment = $repository->findTeacher($assignmentId, $siteKey);
if (
    $assignment === null
    || $assignment->status !== 'active'
    || $assignment->personPublicId !== $personId
    || $assignment->personDisplayName !== 'Иван Петрович Преподаватель'
) {
    fwrite(STDERR, "Назначение преподавателя сохранено некорректно.\n");
    exit(1);
}

try {
    $service->assignTeacher(
        chairPublicId: $chairId,
        personPublicId: $personId,
        positionTitle: 'Преподаватель',
        academicDegree: null,
        academicTitle: null,
        disciplines: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "База разрешила повторное назначение человека на ту же кафедру.\n");
    exit(1);
} catch (PDOException) {
}

$service->publishChair($chairId, $siteKey);
$detail = $catalog->detail($chairId, $siteKey);
if (
    $detail === null
    || ($detail['teacher_count'] ?? 0) !== 1
    || count($detail['teachers'] ?? []) !== 1
    || ($detail['teachers'][0]['person_public_id'] ?? '') !== $personId
    || ($detail['teachers'][0]['position_title'] ?? '') !== 'Доцент'
) {
    fwrite(STDERR, "Опубликованная кафедра не показывает действующего преподавателя.\n");
    exit(1);
}

$service->updateTeacher(
    assignmentPublicId: $assignmentId,
    personPublicId: $secondPersonId,
    positionTitle: 'Заведующий кафедрой',
    academicDegree: 'доктор богословия',
    academicTitle: 'профессор',
    disciplines: 'История Вселенской Церкви',
    sortOrder: 1,
    siteKey: $siteKey,
);
$updatedAssignment = $repository->findTeacher($assignmentId, $siteKey);
if (
    $updatedAssignment === null
    || $updatedAssignment->personPublicId !== $secondPersonId
    || $updatedAssignment->positionTitle !== 'Заведующий кафедрой'
    || $updatedAssignment->academicDegree !== 'доктор богословия'
    || $updatedAssignment->academicTitle !== 'профессор'
    || $updatedAssignment->disciplines !== 'История Вселенской Церкви'
    || $updatedAssignment->sortOrder !== 1
) {
    fwrite(STDERR, "Обновление назначения преподавателя не сохранилось.\n");
    exit(1);
}

$service->deactivateTeacher($assignmentId, $siteKey);
$detailWithoutTeacher = $catalog->detail($chairId, $siteKey);
if (
    $detailWithoutTeacher === null
    || ($detailWithoutTeacher['teacher_count'] ?? -1) !== 0
    || ($detailWithoutTeacher['teachers'] ?? []) !== []
) {
    fwrite(STDERR, "Завершённое назначение осталось в публичной кафедре.\n");
    exit(1);
}

$service->reactivateTeacher($assignmentId, $siteKey);
$detailRestored = $catalog->detail($chairId, $siteKey);
if (($detailRestored['teacher_count'] ?? 0) !== 1) {
    fwrite(STDERR, "Восстановленное назначение не вернулось в публичную кафедру.\n");
    exit(1);
}

$service->updateChair(
    publicId: $chairId,
    ownerOrganizationPublicId: $root->publicId,
    name: 'Кафедра истории Церкви',
    shortName: 'КИЦ',
    descriptionInput: 'Обновлённое описание кафедры.',
    sortOrder: 2,
    siteKey: $siteKey,
);
$updatedChair = $repository->findChair($chairId, $siteKey);
if (
    $updatedChair === null
    || $updatedChair->name !== 'Кафедра истории Церкви'
    || $updatedChair->ownerOrganizationPublicId !== $root->publicId
    || $updatedChair->sortOrder !== 2
) {
    fwrite(STDERR, "Обновление кафедры не сохранилось.\n");
    exit(1);
}

$foreignSiteKey = 'education-staff-foreign';
$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая духовная школа',
    'education',
    $foreignSiteKey,
);
$foreignPersonId = $people->createPerson(
    displayName: 'Чужой преподаватель',
    ownerOrganizationPublicId: $foreignRoot->publicId,
    siteKey: $foreignSiteKey,
);

try {
    $service->assignTeacher(
        chairPublicId: $chairId,
        personPublicId: $foreignPersonId,
        positionTitle: 'Преподаватель',
        academicDegree: null,
        academicTitle: null,
        disciplines: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил преподавателя из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE education_teacher_assignments
     SET person_public_id = :person_public_id
     WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute([
        'person_public_id' => $foreignPersonId,
        'site_key' => $siteKey,
        'public_id' => $assignmentId,
    ]);
    fwrite(STDERR, "База разрешила преподавателя из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$service->unpublishChair($chairId, $siteKey);
if ($catalog->detail($chairId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Снятая с публикации кафедра осталась публичной.\n");
    exit(1);
}

try {
    $service->createChair(
        ownerOrganizationPublicId: $root->publicId,
        name: '',
        shortName: null,
        descriptionInput: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил кафедру без названия.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $service->assignTeacher(
        chairPublicId: $chairId,
        personPublicId: $personId,
        positionTitle: '',
        academicDegree: null,
        academicTitle: null,
        disciplines: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил назначение без должности.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Проверка кафедр и преподавателей пройдена.\n";
