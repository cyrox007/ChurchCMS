<?php

declare(strict_types=1);

use ChurchCMS\Modules\Events\EventApiResource;
use ChurchCMS\Modules\Events\EventPartnerTombstoneApiResource;
use ChurchCMS\Modules\Events\EventPartnerTombstoneRepository;
use ChurchCMS\Modules\Events\EventRepository;
use ChurchCMS\Modules\Events\EventService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use DateTimeImmutable;

require dirname(__DIR__) . '/core.php';

$siteKey = 'event-partner-smoke';
$organizations = OrganizationService::fromDatabase();
$events = EventService::fromDatabase();
$repository = EventRepository::fromDatabase();
$tombstones = EventPartnerTombstoneRepository::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовая епархия federation Events',
    'diocese',
    $siteKey,
);

$eventId = $events->create(
    title: 'Архиерейская встреча',
    startsAt: '2026-11-01T10:00:00+03:00',
    siteKey: $siteKey,
    locationName: 'Епархиальное управление',
    excerpt: 'Безопасное краткое описание.',
    descriptionHtml: '<script>alert(1)</script><p>Скрытое тело</p>',
);

$epoch = new DateTimeImmutable('1970-01-01T00:00:00Z');

if (
    $repository->publishedUpdatedSince(
        $epoch,
        $siteKey,
    ) !== []
) {
    fwrite(
        STDERR,
        "Черновик события попал в partner sync.\n",
    );
    exit(1);
}

$events->publish(
    $eventId,
    $siteKey,
);

$published = $repository->publishedUpdatedSince(
    $epoch,
    $siteKey,
);
if (
    count($published) !== 1
    || $published[0]->publicId !== $eventId
    || $published[0]->status !== 'published'
) {
    fwrite(
        STDERR,
        "Опубликованное событие не появилось в incremental sync.\n",
    );
    exit(1);
}

$projection = (new EventApiResource(
    $published[0],
))->toApiArray();

foreach ([
    'id',
    'title',
    'excerpt',
    'organization_owner_id',
    'starts_at',
    'all_day',
    'updated_at',
] as $key) {
    if (!array_key_exists($key, $projection)) {
        fwrite(
            STDERR,
            "Partner projection события потеряла поле {$key}.\n",
        );
        exit(1);
    }
}

if (
    array_key_exists('description_html', $projection)
    || str_contains(
        json_encode(
            $projection,
            JSON_UNESCAPED_UNICODE,
        ),
        '<script>',
    )
    || ($projection['organization_owner_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Partner projection события раскрыла сырой HTML или потеряла owner.\n",
    );
    exit(1);
}

$cursor = new DateTimeImmutable(
    $published[0]->updatedAt . ' UTC',
);
$afterSameCursor = $repository->publishedUpdatedSince(
    $cursor,
    $siteKey,
    100,
    $eventId,
);

if ($afterSameCursor !== []) {
    fwrite(
        STDERR,
        "Составной event cursor повторно вернул уже обработанное событие.\n",
    );
    exit(1);
}

$events->withdraw(
    $eventId,
    $siteKey,
);

if (
    $repository->publishedUpdatedSince(
        $epoch,
        $siteKey,
    ) !== []
) {
    fwrite(
        STDERR,
        "Снятое событие осталось в partner projection.\n",
    );
    exit(1);
}

$withdrawn = $tombstones->updatedSince(
    $epoch,
    $siteKey,
);
if (
    count($withdrawn) !== 1
    || $withdrawn[0]['event_public_id'] !== $eventId
    || $withdrawn[0]['reason'] !== 'withdrawn'
) {
    fwrite(
        STDERR,
        "Снятие опубликованного события не создало tombstone.\n",
    );
    exit(1);
}

$tombstoneProjection =
    (new EventPartnerTombstoneApiResource(
        $withdrawn[0],
    ))->toApiArray();

if (
    ($tombstoneProjection['action'] ?? null) !== 'delete'
    || ($tombstoneProjection['organization_owner_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Event tombstone API projection некорректна.\n",
    );
    exit(1);
}

$events->publish(
    $eventId,
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
        "Повторная публикация не очистила устаревший event tombstone.\n",
    );
    exit(1);
}

$events->cancel(
    $eventId,
    $siteKey,
);

$cancelled = $tombstones->updatedSince(
    $epoch,
    $siteKey,
);
if (
    count($cancelled) !== 1
    || $cancelled[0]['reason'] !== 'cancelled'
) {
    fwrite(
        STDERR,
        "Отмена опубликованного события не создала cancelled tombstone.\n",
    );
    exit(1);
}

$draftId = $events->create(
    title: 'Неопубликованное событие',
    startsAt: '2026-11-02T10:00:00Z',
    siteKey: $siteKey,
);
$events->cancel(
    $draftId,
    $siteKey,
);

$afterDraftCancel = $tombstones->updatedSince(
    $epoch,
    $siteKey,
);
if (count($afterDraftCancel) !== 1) {
    fwrite(
        STDERR,
        "Отмена черновика создала лишний federation tombstone.\n",
    );
    exit(1);
}

echo "Events partner sync source smoke OK\n";
