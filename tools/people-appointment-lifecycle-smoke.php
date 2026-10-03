<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\People\PeopleCatalogService;
use ChurchCMS\Modules\People\PeopleRepository;
use ChurchCMS\Modules\People\PeopleService;
use ChurchCMS\Modules\People\PersonAppointmentLifecycleService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'people-lifecycle-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия lifecycle People',
    'diocese',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Миссионерский отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$people = PeopleService::fromDatabase();
$personId = $people->createPerson(
    displayName: 'Священник Тестовый',
    ownerOrganizationPublicId: $root->publicId,
    siteKey: $siteKey,
);
$appointmentId = $people->createAppointment(
    personPublicId: $personId,
    organizationPublicId: $root->publicId,
    title: 'Клирик',
    type: 'clergy_position',
    startedOn: '2026-01-01',
    siteKey: $siteKey,
);

$lifecycle = PersonAppointmentLifecycleService::fromDatabase();
$lifecycle->update(
    $appointmentId,
    $departmentId,
    'Руководитель миссионерского отдела',
    'department_head',
    '2026-01-01',
    null,
    20,
    $siteKey,
);

$repository = PeopleRepository::fromDatabase();
$appointment = $repository->findAppointmentByPublicId(
    $appointmentId,
    $siteKey,
);

if (
    $appointment === null
    || $appointment->organizationPublicId !== $departmentId
    || $appointment->title !== 'Руководитель миссионерского отдела'
    || $appointment->type !== 'department_head'
    || $appointment->sortOrder !== 20
) {
    fwrite(STDERR, "Редактирование назначения People работает некорректно.\n");
    exit(1);
}

$lifecycle->end(
    $appointmentId,
    '2026-09-30',
    $siteKey,
);

$ended = $repository->findAppointmentByPublicId(
    $appointmentId,
    $siteKey,
);
$publicAfterEnd = PeopleCatalogService::fromDatabase()->detail(
    $personId,
    $siteKey,
);

if (
    $ended === null
    || $ended->status !== 'inactive'
    || $ended->endedOn !== '2026-09-30'
    || $publicAfterEnd === null
    || ($publicAfterEnd['appointments'] ?? []) !== []
) {
    fwrite(STDERR, "Завершённое назначение People обработано некорректно.\n");
    exit(1);
}

$lifecycle->reactivate(
    $appointmentId,
    $siteKey,
);

$active = $repository->findAppointmentByPublicId(
    $appointmentId,
    $siteKey,
);
$publicAfterReactivate = PeopleCatalogService::fromDatabase()->detail(
    $personId,
    $siteKey,
);

if (
    $active === null
    || $active->status !== 'active'
    || $active->endedOn !== null
    || count($publicAfterReactivate['appointments'] ?? []) !== 1
) {
    fwrite(STDERR, "Повторная активация назначения People работает некорректно.\n");
    exit(1);
}

try {
    $lifecycle->update(
        $appointmentId,
        $departmentId,
        'Некорректный период',
        'position',
        '2026-10-10',
        '2026-10-01',
        0,
        $siteKey,
    );
    fwrite(STDERR, "Lifecycle People разрешил обратный период назначения.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Lifecycle назначений People: OK\n";
