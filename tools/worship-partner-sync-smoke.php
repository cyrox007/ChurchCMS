<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Worship\WorshipApiResource;
use ChurchCMS\Modules\Worship\WorshipPartnerTombstoneApiResource;
use ChurchCMS\Modules\Worship\WorshipPartnerTombstoneRepository;
use ChurchCMS\Modules\Worship\WorshipRepository;
use ChurchCMS\Modules\Worship\WorshipScheduleService;
use DateTimeImmutable;

require dirname(__DIR__) . '/core.php';

$siteKey = 'worship-partner-smoke';
$organizations = OrganizationService::fromDatabase();
$worship = WorshipScheduleService::fromDatabase();
$repository = WorshipRepository::fromDatabase();
$tombstones = WorshipPartnerTombstoneRepository::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовый приход federation Worship',
    'parish',
    $siteKey,
);

$serviceId = $worship->create(
    title: 'Божественная литургия',
    startsAt: '2026-11-08T07:00:00+03:00',
    siteKey: $siteKey,
    serviceType: 'divine_liturgy',
    locationName: 'Главный храм',
    descriptionHtml: '<script>alert(1)</script><p>Скрытое описание</p>',
);

$epoch = new DateTimeImmutable('1970-01-01T00:00:00Z');
$visible = $repository->visibleUpdatedSince(
    $epoch,
    $siteKey,
);

if (
    count($visible) !== 1
    || $visible[0]->publicId !== $serviceId
    || $visible[0]->status !== 'scheduled'
) {
    fwrite(
        STDERR,
        "Запланированное богослужение не попало в partner-поток.\n",
    );
    exit(1);
}

$projection = (new WorshipApiResource(
    $visible[0],
))->toApiArray();

if (
    ($projection['status'] ?? null) !== 'scheduled'
    || ($projection['organization_owner_id'] ?? null)
        !== $root->publicId
    || array_key_exists('description_html', $projection)
    || str_contains(
        json_encode(
            $projection,
            JSON_UNESCAPED_UNICODE,
        ),
        '<script>',
    )
) {
    fwrite(
        STDERR,
        "Worship partner projection раскрыла HTML или потеряла owner/status.\n",
    );
    exit(1);
}

$cursor = new DateTimeImmutable(
    $visible[0]->updatedAt . ' UTC',
);
if (
    $repository->visibleUpdatedSince(
        $cursor,
        $siteKey,
        100,
        $serviceId,
    ) !== []
) {
    fwrite(
        STDERR,
        "Составной Worship cursor повторно вернул обработанную запись.\n",
    );
    exit(1);
}

$worship->cancel(
    $serviceId,
    $siteKey,
);

$cancelled = $repository->visibleUpdatedSince(
    $epoch,
    $siteKey,
);
if (
    count($cancelled) !== 1
    || $cancelled[0]->status !== 'cancelled'
    || $tombstones->updatedSince(
        $epoch,
        $siteKey,
    ) !== []
) {
    fwrite(
        STDERR,
        "Отмена богослужения должна оставаться видимой без tombstone.\n",
    );
    exit(1);
}

$worship->withdraw(
    $serviceId,
    $siteKey,
);

if (
    $repository->visibleUpdatedSince(
        $epoch,
        $siteKey,
    ) !== []
) {
    fwrite(
        STDERR,
        "Снятое богослужение осталось в partner-потоке.\n",
    );
    exit(1);
}

$withdrawn = $tombstones->updatedSince(
    $epoch,
    $siteKey,
);
if (
    count($withdrawn) !== 1
    || $withdrawn[0]['worship_public_id'] !== $serviceId
    || $withdrawn[0]['reason'] !== 'withdrawn'
) {
    fwrite(
        STDERR,
        "Снятие богослужения не создало tombstone.\n",
    );
    exit(1);
}

$tombstoneProjection =
    (new WorshipPartnerTombstoneApiResource(
        $withdrawn[0],
    ))->toApiArray();

if (
    ($tombstoneProjection['action'] ?? null) !== 'delete'
    || ($tombstoneProjection['organization_owner_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Worship tombstone projection некорректна.\n",
    );
    exit(1);
}

$worship->schedule(
    $serviceId,
    $siteKey,
);

if (
    $tombstones->updatedSince(
        $epoch,
        $siteKey,
    ) !== []
) {
    fwrite(
        STDERR,
        "Возврат в расписание не очистил устаревший tombstone.\n",
    );
    exit(1);
}

$rescheduled = $repository->visibleUpdatedSince(
    $epoch,
    $siteKey,
);
if (
    count($rescheduled) !== 1
    || $rescheduled[0]->status !== 'scheduled'
) {
    fwrite(
        STDERR,
        "Возвращённое богослужение не появилось в partner-потоке.\n",
    );
    exit(1);
}

echo "Worship partner sync source smoke OK\n";
