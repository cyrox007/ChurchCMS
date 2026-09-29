<?php

declare(strict_types=1);

use ChurchCMS\Modules\Events\EventService;
use ChurchCMS\Modules\Events\FederatedEventFeedService;
use ChurchCMS\Modules\Organizations\FederationProjectionService;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'aggregated-event-feed-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия событий',
    'diocese',
    $siteKey,
);

$events = EventService::fromDatabase();
$localId = $events->create(
    title: 'Локальное епархиальное событие',
    startsAt: '2099-04-01T10:00:00Z',
    siteKey: $siteKey,
    endsAt: '2099-04-01T12:00:00Z',
    locationName: 'Епархиальный дом',
    excerpt: 'Локальное событие.',
);
$events->publish($localId, $siteKey);

$federation = FederationService::fromDatabase();
$projections = FederationProjectionService::fromDatabase();

$childInstance = 'e1000000-0000-4000-8000-000000000001';
$childOrganization = 'e2000000-0000-4000-8000-000000000002';
$childOwner = 'e3000000-0000-4000-8000-000000000003';
$childEvent = 'e4000000-0000-4000-8000-000000000004';

$childLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $childInstance,
    remoteOrganizationPublicId: $childOrganization,
    remoteBaseUrl: 'https://child-events.example',
    inboundScopes: ['content.read'],
    remoteProfile: 'parish',
    remoteName: 'Дочерний приход',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $childLink,
    remoteInstanceId: $childInstance,
    remoteOrganizationPublicId: $childOrganization,
    remoteProfile: 'parish',
    remoteName: 'Дочерний приход',
    siteKey: $siteKey,
);
$projections->applyUpsert(
    $childLink,
    'event',
    [
        'id' => $childEvent,
        'type' => 'event',
        'title' => 'Престольный праздник прихода',
        'excerpt' => 'Событие пришло из дочернего узла.',
        'description_html' => '<script>не отдавать наружу</script>',
        'organization_owner_id' => $childOwner,
        'starts_at' => '2099-03-01T08:00:00Z',
        'ends_at' => '2099-03-01T11:00:00Z',
        'all_day' => false,
        'location' => 'Приходской храм',
        'updated_at' => '2098-12-01T00:00:00Z',
        'url' => 'https://child-events.example/events/feast',
    ],
    $siteKey,
);

$tombstoneId = 'e5000000-0000-4000-8000-000000000005';
$projections->applyUpsert(
    $childLink,
    'event',
    [
        'id' => $tombstoneId,
        'title' => 'Удалённое событие',
        'organization_owner_id' => $childOwner,
        'starts_at' => '2099-02-01T10:00:00Z',
        'all_day' => false,
        'updated_at' => '2098-12-02T00:00:00Z',
        'url' => 'https://child-events.example/events/deleted',
    ],
    $siteKey,
);
$projections->applyTombstone(
    $childLink,
    'event',
    [
        'action' => 'delete',
        'id' => $tombstoneId,
        'organization_owner_id' => $childOwner,
        'reason' => 'cancelled',
        'updated_at' => '2098-12-03T00:00:00Z',
    ],
    $siteKey,
);

$peerInstance = 'f1000000-0000-4000-8000-000000000001';
$peerOrganization = 'f2000000-0000-4000-8000-000000000002';
$peerEvent = 'f4000000-0000-4000-8000-000000000004';
$peerLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'peer',
    remoteInstanceId: $peerInstance,
    remoteOrganizationPublicId: $peerOrganization,
    remoteBaseUrl: 'https://peer-events.example',
    inboundScopes: ['content.read'],
    remoteProfile: 'diocese',
    remoteName: 'Соседняя епархия',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $peerLink,
    remoteInstanceId: $peerInstance,
    remoteOrganizationPublicId: $peerOrganization,
    remoteProfile: 'diocese',
    remoteName: 'Соседняя епархия',
    siteKey: $siteKey,
);
$projections->applyUpsert(
    $peerLink,
    'event',
    [
        'id' => $peerEvent,
        'title' => 'Событие peer-узла',
        'organization_owner_id' => $peerOrganization,
        'starts_at' => '2099-01-01T10:00:00Z',
        'all_day' => false,
        'updated_at' => '2098-12-04T00:00:00Z',
        'url' => 'https://peer-events.example/events/peer',
    ],
    $siteKey,
);

$noScopeInstance = 'd1000000-0000-4000-8000-000000000011';
$noScopeOrganization = 'd2000000-0000-4000-8000-000000000012';
$noScopeEvent = 'd4000000-0000-4000-8000-000000000014';
$noScopeLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $noScopeInstance,
    remoteOrganizationPublicId: $noScopeOrganization,
    remoteBaseUrl: 'https://hidden-events.example',
    inboundScopes: [],
    remoteProfile: 'parish',
    remoteName: 'Приход без входящего scope',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $noScopeLink,
    remoteInstanceId: $noScopeInstance,
    remoteOrganizationPublicId: $noScopeOrganization,
    remoteProfile: 'parish',
    remoteName: 'Приход без входящего scope',
    siteKey: $siteKey,
);
$projections->applyUpsert(
    $noScopeLink,
    'event',
    [
        'id' => $noScopeEvent,
        'title' => 'Скрытое событие',
        'organization_owner_id' => $noScopeOrganization,
        'starts_at' => '2099-01-02T10:00:00Z',
        'all_day' => false,
        'updated_at' => '2098-12-05T00:00:00Z',
        'url' => 'https://hidden-events.example/events/hidden',
    ],
    $siteKey,
);

$feed = FederatedEventFeedService::fromDatabase()
    ->upcoming(
        $siteKey,
        20,
        new DateTimeImmutable('2098-01-01T00:00:00Z'),
    );

if (count($feed) !== 2) {
    fwrite(
        STDERR,
        "Агрегированная Events-лента содержит лишние или потерянные события.\n",
    );
    exit(1);
}

$byId = [];
foreach ($feed as $item) {
    $byId[(string) ($item['id'] ?? '')] = $item;
}

$local = $byId[$localId] ?? null;
$remote = $byId[$childEvent] ?? null;

if (
    !is_array($local)
    || ($local['source']['kind'] ?? null) !== 'local'
    || ($local['source']['organization_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Локальное событие потеряло владельца в агрегированной ленте.\n",
    );
    exit(1);
}

if (
    !is_array($remote)
    || ($remote['source']['kind'] ?? null) !== 'federation'
    || ($remote['source']['instance_id'] ?? null)
        !== $childInstance
    || ($remote['source']['organization_id'] ?? null)
        !== $childOwner
    || ($remote['source']['name'] ?? null)
        !== 'Дочерний приход'
    || ($remote['source']['canonical_url'] ?? null)
        !== 'https://child-events.example/events/feast'
    || ($remote['url'] ?? null)
        !== 'https://child-events.example/events/feast'
    || ($remote['starts_at'] ?? null)
        !== '2099-03-01T08:00:00+00:00'
    || array_key_exists('description_html', $remote)
) {
    fwrite(
        STDERR,
        "Remote-событие потеряло source или раскрыло лишний payload.\n",
    );
    exit(1);
}

foreach ([
    $tombstoneId,
    $peerEvent,
    $noScopeEvent,
] as $forbiddenId) {
    if (isset($byId[$forbiddenId])) {
        fwrite(
            STDERR,
            "В агрегированную Events-ленту попал запрещённый источник {$forbiddenId}.\n",
        );
        exit(1);
    }
}

if (($feed[0]['id'] ?? null) !== $childEvent) {
    fwrite(
        STDERR,
        "Агрегированная Events-лента отсортирована не по времени начала.\n",
    );
    exit(1);
}

echo "Агрегированная Events-лента с сохранением источника проверена\n";
