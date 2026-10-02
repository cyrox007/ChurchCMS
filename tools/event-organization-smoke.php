<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Events\EventCatalogService;
use ChurchCMS\Modules\Events\EventRepository;
use ChurchCMS\Modules\Events\EventService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$events = EventService::fromDatabase();
$repository = EventRepository::fromDatabase();

$siteKey = 'event-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия событий',
    'diocese',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Молодёжный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$rootEventId = $events->create(
    title: 'Епархиальное собрание',
    startsAt: '2026-10-10T10:00:00+03:00',
    siteKey: $siteKey,
    locationName: 'Епархиальное управление',
    excerpt: 'Рабочая встреча.',
);
$rootEvent = $repository->findByPublicId(
    $rootEventId,
    $siteKey,
);

if (
    $rootEvent === null
    || $rootEvent->ownerOrganizationPublicId !== $root->publicId
    || $rootEvent->startsAt !== '2026-10-10 07:00:00'
    || $rootEvent->status !== 'draft'
) {
    fwrite(
        STDERR,
        "Событие не получило root-владельца или UTC-время.\n",
    );
    exit(1);
}

$departmentEventId = $events->create(
    title: 'Молодёжный форум',
    startsAt: '2026-10-11T00:00:00+03:00',
    ownerOrganizationPublicId: $departmentId,
    siteKey: $siteKey,
    endsAt: '2026-10-11T23:59:00+03:00',
    allDay: true,
);
$departmentEvent = $repository->findByPublicId(
    $departmentEventId,
    $siteKey,
);

if (
    $departmentEvent === null
    || $departmentEvent->ownerOrganizationPublicId !== $departmentId
    || $departmentEvent->allDay !== true
    || $departmentEvent->endsAt !== '2026-10-11 20:59:00'
) {
    fwrite(
        STDERR,
        "Событие подразделения сохранено некорректно.\n",
    );
    exit(1);
}

$list = $repository->forOrganization(
    $departmentId,
    $siteKey,
);
if (
    count($list) !== 1
    || $list[0]->publicId !== $departmentEventId
) {
    fwrite(
        STDERR,
        "Репозиторий не вернул события выбранной организации.\n",
    );
    exit(1);
}

$events->assignOrganizationOwner(
    $rootEventId,
    $departmentId,
    $siteKey,
);
$moved = $repository->findByPublicId(
    $rootEventId,
    $siteKey,
);

if (
    $moved === null
    || $moved->ownerOrganizationPublicId !== $departmentId
) {
    fwrite(
        STDERR,
        "Смена владельца события не сохранилась.\n",
    );
    exit(1);
}

$events->cancel(
    $departmentEventId,
    $siteKey,
);
$cancelled = $repository->findByPublicId(
    $departmentEventId,
    $siteKey,
);

if ($cancelled?->status !== 'cancelled') {
    fwrite(
        STDERR,
        "Отмена события не сохранила status=cancelled.\n",
    );
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая епархия событий',
    'diocese',
    'event-foreign',
);

try {
    $events->create(
        title: 'Недопустимое событие',
        startsAt: '2026-10-12T10:00:00Z',
        ownerOrganizationPublicId: $foreignRoot->publicId,
        siteKey: $siteKey,
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца события из другого site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $events->create(
        title: 'Обратный интервал',
        startsAt: '2026-10-12T10:00:00Z',
        endsAt: '2026-10-12T09:00:00Z',
        siteKey: $siteKey,
    );
    fwrite(
        STDERR,
        "Сервис разрешил окончание события раньше начала.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE events
     SET owner_organization_public_id = :organization_id
     WHERE public_id = :public_id
       AND site_key = :site_key'
);

try {
    $statement->execute([
        'organization_id' => $foreignRoot->publicId,
        'public_id' => $rootEventId,
        'site_key' => $siteKey,
    ]);
    fwrite(
        STDERR,
        "База разрешила владельца события из другого site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

$events->update(
    $eventId,
    'Приходской праздник',
    '2026-10-15 10:00:00',
    '2026-10-15 13:00:00',
    false,
    'Приходской дом',
    'Краткое описание',
    'Полное описание',
    'events-smoke',
);

$events->publish(
    $eventId,
    'events-smoke',
);

$updated = $repository->findByPublicId(
    $eventId,
    'events-smoke',
);

if (
    $updated === null
    || $updated->title !== 'Приходской праздник'
    || $updated->locationName !== 'Приходской дом'
    || $updated->status !== 'published'
) {
    fwrite(
        STDERR,
        "Редактирование или публикация Events работает некорректно.\n",
    );
    exit(1);
}

$adminList = $repository->adminList(
    [$updated->ownerOrganizationPublicId],
    'events-smoke',
);

if (
    count($adminList) !== 1
    || $adminList[0]->publicId !== $eventId
) {
    fwrite(
        STDERR,
        "Organization-scoped Admin-список Events работает некорректно.\n",
    );
    exit(1);
}

$catalog = EventCatalogService::fromDatabase();
$detail = $catalog->detail(
    $eventId,
    'events-smoke',
);
$calendar = $catalog->month(
    '2026-10',
    'events-smoke',
);

if (
    $detail === null
    || ($detail['title'] ?? '') !== 'Приходской праздник'
    || empty($calendar['days']['2026-10-15'])
) {
    fwrite(
        STDERR,
        "Public/calendar projection Events сформирована некорректно.\n",
    );
    exit(1);
}

echo "Events organization/Admin/public/calendar smoke OK\n";
