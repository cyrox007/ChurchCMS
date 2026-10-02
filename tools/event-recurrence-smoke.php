<?php

declare(strict_types=1);

use ChurchCMS\Modules\Events\EventRecurrenceService;
use ChurchCMS\Modules\Events\EventRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход повторяющихся событий',
    'parish',
    'events-recurrence-smoke',
);

$recurrence = EventRecurrenceService::fromDatabase();
$ruleId = $recurrence->createRule(
    title: 'Воскресная школа',
    frequency: 'weekly',
    localTime: '12:00',
    timezone: 'Europe/Moscow',
    startsOn: '2026-10-01',
    ownerOrganizationPublicId: $root->publicId,
    siteKey: 'events-recurrence-smoke',
    weekday: 7,
    durationMinutes: 90,
    endsOn: '2026-10-31',
    locationName: 'Приходской дом',
    excerpt: 'Еженедельное занятие',
);

$created = $recurrence->materialize(
    $ruleId,
    '2026-10-01',
    '2026-10-31',
    'events-recurrence-smoke',
);

if (count($created) !== 4) {
    fwrite(
        STDERR,
        'Ожидалось четыре воскресных события, создано: '
        . count($created)
        . "\n",
    );
    exit(1);
}

$repeat = $recurrence->materialize(
    $ruleId,
    '2026-10-01',
    '2026-10-31',
    'events-recurrence-smoke',
);

if ($repeat !== []) {
    fwrite(
        STDERR,
        "Повторная материализация Events создала дубли.\n",
    );
    exit(1);
}

$events = EventRepository::fromDatabase()->forOrganization(
    $root->publicId,
    'events-recurrence-smoke',
);

if (count($events) !== 4) {
    fwrite(
        STDERR,
        "Материализованные Events не найдены.\n",
    );
    exit(1);
}

foreach ($events as $event) {
    if ($event->status !== 'published') {
        fwrite(
            STDERR,
            "Повторяющееся событие не опубликовано автоматически.\n",
        );
        exit(1);
    }
}

$starts = array_map(
    static fn($event): string => $event->startsAt,
    $events,
);
sort($starts);

$expected = [
    '2026-10-04 09:00:00',
    '2026-10-11 09:00:00',
    '2026-10-18 09:00:00',
    '2026-10-25 09:00:00',
];

if ($starts !== $expected) {
    fwrite(
        STDERR,
        "Часовой пояс повторяющихся Events обработан некорректно.\n",
    );
    exit(1);
}

$recurrence->deactivate(
    $ruleId,
    'events-recurrence-smoke',
);

try {
    $recurrence->materialize(
        $ruleId,
        '2026-11-01',
        '2026-11-30',
        'events-recurrence-smoke',
    );
    fwrite(
        STDERR,
        "Неактивное правило Events продолжило создавать события.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Повторяющиеся Events: OK\n";
