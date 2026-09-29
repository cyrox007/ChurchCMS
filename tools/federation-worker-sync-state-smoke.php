<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\FederationWorkerSyncStateRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'federation-worker-state-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый узел worker state',
    'diocese',
    $siteKey,
);

$remoteInstanceId = '71000000-0000-4000-8000-000000000007';
$remoteOrganizationId = '72000000-0000-4000-8000-000000000007';

$federation = FederationService::fromDatabase();
$linkPublicId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    remoteBaseUrl: 'https://worker-state.example',
    inboundScopes: ['content.read'],
    outboundScopes: [],
    outboundToken: 'worker-state-token',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $linkPublicId,
    remoteInstanceId: $remoteInstanceId,
    remoteOrganizationPublicId: $remoteOrganizationId,
    siteKey: $siteKey,
);

$link = FederationRepository::fromDatabase()
    ->findByPublicId(
        $linkPublicId,
        $siteKey,
    );

if ($link === null) {
    fwrite(STDERR, "Federation link для worker state не найден.\n");
    exit(1);
}

$states = FederationWorkerSyncStateRepository::fromDatabase();

$publicationCursorOne = '{"version":1,"publications":{"updated_since":"2040-01-01T00:00:00+00:00","after":null},"tombstones":{"updated_since":"1970-01-01T00:00:00+00:00","after":null}}';
$publicationCursorTwo = '{"version":1,"publications":{"updated_since":"2040-01-02T00:00:00+00:00","after":null},"tombstones":{"updated_since":"1970-01-01T00:00:00+00:00","after":null}}';
$eventCursor = '{"version":1,"events":{"updated_since":"2041-01-01T00:00:00+00:00","after":null},"tombstones":{"updated_since":"1970-01-01T00:00:00+00:00","after":null}}';

$states->saveCursor(
    $link->id,
    'publications',
    null,
    $publicationCursorOne,
);
$states->saveCursor(
    $link->id,
    'events',
    null,
    $eventCursor,
);

if (
    $states->cursor($link->id, 'publications')
        !== $publicationCursorOne
    || $states->cursor($link->id, 'events')
        !== $eventCursor
) {
    fwrite(
        STDERR,
        "Курсоры разных federation worker пересеклись.\n",
    );
    exit(1);
}

$states->recordFailure(
    $link->id,
    'events',
    'Тестовая ошибка Events.',
    $eventCursor,
);

$publicationState = $states->state(
    $link->id,
    'publications',
);
$eventState = $states->state(
    $link->id,
    'events',
);

if (
    ($publicationState['last_sync_error'] ?? null) !== null
    || ($eventState['last_sync_error'] ?? null)
        !== 'Тестовая ошибка Events.'
) {
    fwrite(
        STDERR,
        "Ошибка одного worker затронула состояние другого.\n",
    );
    exit(1);
}

$states->recordSuccess(
    $link->id,
    'publications',
    $publicationCursorOne,
    $publicationCursorTwo,
);

$publicationState = $states->state(
    $link->id,
    'publications',
);
$eventState = $states->state(
    $link->id,
    'events',
);
$linkAfterPublication = FederationRepository::fromDatabase()
    ->findByPublicId(
        $linkPublicId,
        $siteKey,
    );

if (
    ($publicationState['cursor'] ?? null)
        !== $publicationCursorTwo
    || ($publicationState['last_sync_at'] ?? null) === null
    || ($eventState['cursor'] ?? null) !== $eventCursor
    || ($eventState['last_sync_error'] ?? null)
        !== 'Тестовая ошибка Events.'
    || $linkAfterPublication === null
    || $linkAfterPublication->syncCursor
        !== $publicationCursorTwo
) {
    fwrite(
        STDERR,
        "Успех Publications повредил независимое состояние Events или legacy mirror.\n",
    );
    exit(1);
}

try {
    $states->saveCursor(
        $link->id,
        'events',
        '{"stale":true}',
        '{"unexpected":true}',
    );
    fwrite(
        STDERR,
        "Устаревший worker смог перезаписать Events cursor.\n",
    );
    exit(1);
} catch (RuntimeException) {
}

if (
    $states->cursor($link->id, 'events')
        !== $eventCursor
) {
    fwrite(
        STDERR,
        "Optimistic lock повредил актуальный Events cursor.\n",
    );
    exit(1);
}

$federation->revoke(
    $linkPublicId,
    $siteKey,
);

if (
    $states->state($link->id, 'publications') !== null
    || $states->state($link->id, 'events') !== null
) {
    fwrite(
        STDERR,
        "Revoke не очистил sync state отдельных worker.\n",
    );
    exit(1);
}

echo "Независимое состояние federation sync worker проверено\n";
