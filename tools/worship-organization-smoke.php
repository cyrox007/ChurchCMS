<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Worship\WorshipCatalogService;
use ChurchCMS\Modules\Worship\WorshipRepository;
use ChurchCMS\Modules\Worship\WorshipScheduleService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$worship = WorshipScheduleService::fromDatabase();
$repository = WorshipRepository::fromDatabase();

$siteKey = 'worship-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовый приход расписания',
    'parish',
    $siteKey,
);
$chapelId = $organizations->create(
    name: 'Приписной храм',
    type: 'chapel',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$rootServiceId = $worship->create(
    title: 'Божественная литургия',
    startsAt: '2026-10-01T07:00:00+03:00',
    siteKey: $siteKey,
    serviceType: 'divine_liturgy',
    locationName: 'Главный храм',
);
$rootService = $repository->findByPublicId(
    $rootServiceId,
    $siteKey,
);

if (
    $rootService === null
    || $rootService->ownerOrganizationPublicId !== $root->publicId
    || $rootService->startsAt !== '2026-10-01 04:00:00'
    || $rootService->status !== 'scheduled'
) {
    fwrite(
        STDERR,
        "Богослужение не получило root-владельца или UTC-время.
",
    );
    exit(1);
}

$chapelServiceId = $worship->create(
    title: 'Всенощное бдение',
    startsAt: '2026-10-02T17:00:00+03:00',
    ownerOrganizationPublicId: $chapelId,
    siteKey: $siteKey,
    serviceType: 'vigil',
    endsAt: '2026-10-02T19:30:00+03:00',
);
$chapelService = $repository->findByPublicId(
    $chapelServiceId,
    $siteKey,
);

if (
    $chapelService === null
    || $chapelService->ownerOrganizationPublicId !== $chapelId
    || $chapelService->endsAt !== '2026-10-02 16:30:00'
) {
    fwrite(
        STDERR,
        "Богослужение дочерней организации сохранено некорректно.
",
    );
    exit(1);
}

$services = $repository->forOrganization(
    $chapelId,
    $siteKey,
);

if (
    count($services) !== 1
    || $services[0]->publicId !== $chapelServiceId
) {
    fwrite(
        STDERR,
        "Репозиторий не вернул расписание выбранной организации.
",
    );
    exit(1);
}

$worship->assignOrganizationOwner(
    $rootServiceId,
    $chapelId,
    $siteKey,
);
$moved = $repository->findByPublicId(
    $rootServiceId,
    $siteKey,
);

if (
    $moved === null
    || $moved->ownerOrganizationPublicId !== $chapelId
) {
    fwrite(
        STDERR,
        "Смена владельца богослужения не сохранилась.
",
    );
    exit(1);
}

$worship->cancel(
    $chapelServiceId,
    $siteKey,
);
$cancelled = $repository->findByPublicId(
    $chapelServiceId,
    $siteKey,
);

if ($cancelled?->status !== 'cancelled') {
    fwrite(
        STDERR,
        "Отмена богослужения не сохранила статус cancelled.
",
    );
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход расписания',
    'parish',
    'worship-foreign',
);

try {
    $worship->create(
        title: 'Недопустимая служба',
        startsAt: '2026-10-03T08:00:00Z',
        ownerOrganizationPublicId: $foreignRoot->publicId,
        siteKey: $siteKey,
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца из другого site_key.
",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $worship->create(
        title: 'Обратный интервал',
        startsAt: '2026-10-03T10:00:00Z',
        endsAt: '2026-10-03T09:00:00Z',
        siteKey: $siteKey,
    );
    fwrite(
        STDERR,
        "Сервис разрешил окончание раньше начала.
",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE worship_services
     SET owner_organization_public_id = :organization_id
     WHERE public_id = :public_id
       AND site_key = :site_key'
);

try {
    $statement->execute([
        'organization_id' => $foreignRoot->publicId,
        'public_id' => $rootServiceId,
        'site_key' => $siteKey,
    ]);
    fwrite(
        STDERR,
        "База разрешила владельца богослужения из другого site_key.
",
    );
    exit(1);
} catch (PDOException) {
}

echo "Worship organization foundation smoke OK
";
