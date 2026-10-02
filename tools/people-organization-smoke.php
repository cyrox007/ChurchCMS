<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\People\PeopleCatalogService;
use ChurchCMS\Modules\People\PeopleRepository;
use ChurchCMS\Modules\People\PersonMediaService;
use ChurchCMS\Modules\People\PeopleService;
use ChurchCMS\Modules\People\PersonMediaService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$people = PeopleService::fromDatabase();
$repository = PeopleRepository::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовая епархия людей',
    'diocese',
    'people-smoke',
);
$departmentId = $organizations->create(
    name: 'Отдел образования',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: 'people-smoke',
);
$deaneryId = $organizations->create(
    name: 'Центральное благочиние',
    type: 'deanery',
    parentPublicId: $root->publicId,
    siteKey: 'people-smoke',
);

$personId = $people->createPerson(
    displayName: 'протоиерей Иоанн Тестовый',
    siteKey: 'people-smoke',
    firstName: 'Иоанн',
    lastName: 'Тестовый',
);
$person = $repository->findPersonByPublicId(
    $personId,
    'people-smoke',
);

if (
    $person === null
    || $person->ownerOrganizationPublicId !== $root->publicId
) {
    fwrite(
        STDERR,
        "Новый человек не получил корневую организацию владельцем.\n",
    );
    exit(1);
}

$people->assignOrganizationOwner(
    $personId,
    $departmentId,
    'people-smoke',
);
$person = $repository->findPersonByPublicId(
    $personId,
    'people-smoke',
);

if (
    $person === null
    || $person->ownerOrganizationPublicId !== $departmentId
) {
    fwrite(
        STDERR,
        "Владелец карточки человека не изменился.\n",
    );
    exit(1);
}

$appointmentId = $people->createAppointment(
    personPublicId: $personId,
    organizationPublicId: $deaneryId,
    title: 'Благочинный',
    type: 'clergy_position',
    startedOn: '2026-01-01',
    sortOrder: 10,
    siteKey: 'people-smoke',
);
$appointment = $repository->findAppointmentByPublicId(
    $appointmentId,
    'people-smoke',
);

if (
    $appointment === null
    || $appointment->personPublicId !== $personId
    || $appointment->organizationPublicId !== $deaneryId
    || $appointment->title !== 'Благочинный'
) {
    fwrite(
        STDERR,
        "Назначение человека сохранено некорректно.\n",
    );
    exit(1);
}

$appointments = $repository->appointmentsForPerson(
    $personId,
    'people-smoke',
);
if (
    count($appointments) !== 1
    || $appointments[0]->publicId !== $appointmentId
) {
    fwrite(
        STDERR,
        "Репозиторий не вернул назначение человека.\n",
    );
    exit(1);
}

$people->updatePerson(
    $personId,
    'протоиерей Иоанн Обновлённый',
    firstName: 'Иоанн',
    lastName: 'Обновлённый',
    biographyHtml: 'Краткая биография.',
    siteKey: 'people-smoke',
);

$adminList = $repository->adminList(
    [$departmentId],
    'people-smoke',
);
if (
    count($adminList) !== 1
    || $adminList[0]->publicId !== $personId
    || $adminList[0]->displayName
        !== 'протоиерей Иоанн Обновлённый'
) {
    fwrite(
        STDERR,
        "Admin-список People или обновление карточки работают некорректно.\n",
    );
    exit(1);
}

if ($repository->adminList([], 'people-smoke') !== []) {
    fwrite(
        STDERR,
        "Пустой organization scope People не должен видеть карточки.\n",
    );
    exit(1);
}

$publicList = PeopleCatalogService::fromDatabase()
    ->index('people-smoke');
$publicDetail = PeopleCatalogService::fromDatabase()
    ->detail($personId, 'people-smoke');

if (
    count($publicList) !== 1
    || $publicDetail === null
    || ($publicDetail['display_name'] ?? '')
        !== 'протоиерей Иоанн Обновлённый'
    || count($publicDetail['appointments'] ?? []) !== 1
) {
    fwrite(
        STDERR,
        "Публичная projection People сформирована некорректно.\n",
    );
    exit(1);
}

$media = MediaService::fromDatabase();
$portraits = new PersonMediaService();

$portraitId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'portrait.jpg',
    mimeType: 'image/jpeg',
    bytes: 128,
    sha256: str_repeat('a', 64),
    ownerOrganizationPublicId: $departmentId,
    siteKey: 'people-smoke',
    title: 'Портрет протоиерея Иоанна',
);

$portraits->attachPortrait(
    $personId,
    $portraitId,
    'people-smoke',
);

if (
    $portraits->portraitMediaPublicId(
        $personId,
        'people-smoke',
    ) !== $portraitId
) {
    fwrite(
        STDERR,
        "Портрет People не сохранён через Media usage reference.\n",
    );
    exit(1);
}

$privatePortraitDetail = PeopleCatalogService::fromDatabase()
    ->detail($personId, 'people-smoke');

if (
    $privatePortraitDetail === null
    || ($privatePortraitDetail['portrait'] ?? null) !== null
) {
    fwrite(
        STDERR,
        "Private Media-портрет попал в публичную карточку People.\n",
    );
    exit(1);
}

$documentAssetId = $media->registerMetadata(
    mediaType: 'document',
    originalName: 'not-portrait.pdf',
    mimeType: 'application/pdf',
    bytes: 64,
    sha256: str_repeat('b', 64),
    ownerOrganizationPublicId: $departmentId,
    siteKey: 'people-smoke',
);

try {
    $portraits->attachPortrait(
        $personId,
        $documentAssetId,
        'people-smoke',
    );
    fwrite(
        STDERR,
        "People разрешил использовать документ вместо портрета.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$media = MediaService::fromDatabase();
$portraitId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'portrait.jpg',
    mimeType: 'image/jpeg',
    bytes: 128,
    sha256: str_repeat('a', 64),
    ownerOrganizationPublicId: $departmentId,
    siteKey: 'people-smoke',
    title: 'Портрет тестового человека',
);

$personMedia = new PersonMediaService();
$personMedia->attachPortrait(
    $personId,
    $portraitId,
    'people-smoke',
);

if (
    $personMedia->portraitMediaPublicId(
        $personId,
        'people-smoke',
    ) !== $portraitId
) {
    fwrite(
        STDERR,
        "Media usage-reference портрета People не сохранился.\n",
    );
    exit(1);
}

$privateProjection = PeopleCatalogService::fromDatabase()
    ->detail($personId, 'people-smoke');

if (
    $privateProjection === null
    || ($privateProjection['portrait'] ?? null) !== null
) {
    fwrite(
        STDERR,
        "Private Media asset не должен попадать в public People projection.\n",
    );
    exit(1);
}

$media->setVisibility(
    $portraitId,
    'public',
    'people-smoke',
);

$publicProjection = PeopleCatalogService::fromDatabase()
    ->detail($personId, 'people-smoke');

if (
    $publicProjection === null
    || !is_array($publicProjection['portrait'] ?? null)
    || ($publicProjection['portrait']['public_id'] ?? '')
        !== $portraitId
) {
    fwrite(
        STDERR,
        "Public Media portrait не появился в People projection.\n",
    );
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая епархия людей',
    'diocese',
    'people-foreign',
);

try {
    $people->assignOrganizationOwner(
        $personId,
        $foreignRoot->publicId,
        'people-smoke',
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца человека из другого site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $people->createAppointment(
        personPublicId: $personId,
        organizationPublicId: $foreignRoot->publicId,
        title: 'Недопустимое назначение',
        siteKey: 'people-smoke',
    );
    fwrite(
        STDERR,
        "Сервис разрешил назначение в чужом site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $people->createAppointment(
        personPublicId: $personId,
        organizationPublicId: $deaneryId,
        title: 'Некорректный период',
        startedOn: '2026-02-01',
        endedOn: '2026-01-01',
        siteKey: 'people-smoke',
    );
    fwrite(
        STDERR,
        "Сервис разрешил обратный период назначения.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$personOwnerUpdate = $pdo->prepare(
    'UPDATE people
     SET owner_organization_public_id = :organization_id
     WHERE public_id = :person_id
       AND site_key = :site_key'
);

try {
    $personOwnerUpdate->execute([
        'organization_id' => $foreignRoot->publicId,
        'person_id' => $personId,
        'site_key' => 'people-smoke',
    ]);
    fwrite(
        STDERR,
        "База разрешила владельца человека из другого site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

$appointmentUpdate = $pdo->prepare(
    'UPDATE person_appointments
     SET organization_public_id = :organization_id
     WHERE public_id = :appointment_id
       AND site_key = :site_key'
);

try {
    $appointmentUpdate->execute([
        'organization_id' => $foreignRoot->publicId,
        'appointment_id' => $appointmentId,
        'site_key' => 'people-smoke',
    ]);
    fwrite(
        STDERR,
        "База разрешила назначение в другом site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

echo "People organization/Admin/public/Media smoke OK\n";
