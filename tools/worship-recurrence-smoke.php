<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Worship\WorshipRecurrenceService;
use ChurchCMS\Modules\Worship\WorshipRepository;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход повторяющихся богослужений',
    'parish',
    'worship-recurrence-smoke',
);

$recurrence = WorshipRecurrenceService::fromDatabase();
$ruleId = $recurrence->createRule(
    title: 'Божественная литургия',
    frequency: 'weekly',
    localTime: '09:00',
    timezone: 'Europe/Moscow',
    startsOn: '2026-10-01',
    ownerOrganizationPublicId: $root->publicId,
    siteKey: 'worship-recurrence-smoke',
    serviceType: 'liturgy',
    weekday: 7,
    durationMinutes: 120,
    endsOn: '2026-10-31',
    locationName: 'Главный храм',
);

$created = $recurrence->materialize(
    $ruleId,
    '2026-10-01',
    '2026-10-31',
    'worship-recurrence-smoke',
);

if (count($created) !== 4) {
    fwrite(
        STDERR,
        'Ожидалось четыре воскресных богослужения, создано: '
        . count($created)
        . "\n",
    );
    exit(1);
}

$repeat = $recurrence->materialize(
    $ruleId,
    '2026-10-01',
    '2026-10-31',
    'worship-recurrence-smoke',
);

if ($repeat !== []) {
    fwrite(
        STDERR,
        "Повторная материализация создала дубли.\n",
    );
    exit(1);
}

$repository = WorshipRepository::fromDatabase();
$services = $repository->forOrganization(
    $root->publicId,
    'worship-recurrence-smoke',
);

if (count($services) !== 4) {
    fwrite(
        STDERR,
        "Материализованные Worship не найдены в расписании.\n",
    );
    exit(1);
}

$expectedStarts = [
    '2026-10-04 06:00:00',
    '2026-10-11 06:00:00',
    '2026-10-18 06:00:00',
    '2026-10-25 06:00:00',
];

$actualStarts = array_map(
    static fn($service): string => $service->startsAt,
    $services,
);
sort($actualStarts);

if ($actualStarts !== $expectedStarts) {
    fwrite(
        STDERR,
        "Часовой пояс повторяющихся Worship обработан некорректно.\n",
    );
    exit(1);
}

$pdo = DatabaseManager::getInstance()->connection();
$count = $pdo->prepare(
    'SELECT COUNT(*)
     FROM worship_recurrence_occurrences o
     INNER JOIN worship_recurrence_rules r
        ON r.id = o.rule_id
     WHERE r.public_id = :public_id
       AND r.site_key = :site_key'
);
$count->execute([
    'public_id' => $ruleId,
    'site_key' => 'worship-recurrence-smoke',
]);

if ((int) $count->fetchColumn() !== 4) {
    fwrite(
        STDERR,
        "Журнал materialized occurrence заполнен некорректно.\n",
    );
    exit(1);
}

$recurrence->deactivate(
    $ruleId,
    'worship-recurrence-smoke',
);

try {
    $recurrence->materialize(
        $ruleId,
        '2026-11-01',
        '2026-11-30',
        'worship-recurrence-smoke',
    );
    fwrite(
        STDERR,
        "Неактивное правило продолжило создавать Worship.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Повторяющиеся богослужения: OK\n";
