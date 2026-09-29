<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationProjectionService;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Worship\FederatedWorshipFeedService;
use ChurchCMS\Modules\Worship\WorshipScheduleService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'aggregated-worship-feed-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия богослужений',
    'diocese',
    $siteKey,
);

$worship = WorshipScheduleService::fromDatabase();
$localId = $worship->create(
    title: 'Локальная Божественная литургия',
    startsAt: '2099-04-01T08:00:00Z',
    siteKey: $siteKey,
    serviceType: 'divine_liturgy',
    endsAt: '2099-04-01T10:00:00Z',
    locationName: 'Кафедральный собор',
);
$worship->schedule($localId, $siteKey);

$federation = FederationService::fromDatabase();
$projections = FederationProjectionService::fromDatabase();

$childInstance = '71000000-0000-4000-8000-000000000001';
$childOrganization = '72000000-0000-4000-8000-000000000002';
$childOwner = '73000000-0000-4000-8000-000000000003';
$childWorship = '74000000-0000-4000-8000-000000000004';

$childLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $childInstance,
    remoteOrganizationPublicId: $childOrganization,
    remoteBaseUrl: 'https://child-worship.example',
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
    'worship',
    [
        'id' => $childWorship,
        'type' => 'worship',
        'status' => 'cancelled',
        'title' => 'Отменённая ранняя литургия',
        'service_type' => 'divine_liturgy',
        'organization_owner_id' => $childOwner,
        'starts_at' => '2099-03-01T07:00:00Z',
        'ends_at' => null,
        'location' => 'Приходской храм',
        'description_html' => '<script>не отдавать наружу</script>',
        'updated_at' => '2098-12-01T00:00:00Z',
        'url' => 'https://child-worship.example/worship/march',
    ],
    $siteKey,
);

$tombstoneId = '75000000-0000-4000-8000-000000000005';
$projections->applyUpsert(
    $childLink,
    'worship',
    [
        'id' => $tombstoneId,
        'type' => 'worship',
        'status' => 'scheduled',
        'title' => 'Снятое богослужение',
        'service_type' => 'service',
        'organization_owner_id' => $childOwner,
        'starts_at' => '2099-02-01T09:00:00Z',
        'updated_at' => '2098-12-02T00:00:00Z',
        'url' => 'https://child-worship.example/worship/deleted',
    ],
    $siteKey,
);
$projections->applyTombstone(
    $childLink,
    'worship',
    [
        'action' => 'delete',
        'id' => $tombstoneId,
        'organization_owner_id' => $childOwner,
        'reason' => 'withdrawn',
        'updated_at' => '2098-12-03T00:00:00Z',
    ],
    $siteKey,
);

$peerInstance = '81000000-0000-4000-8000-000000000001';
$peerOrganization = '82000000-0000-4000-8000-000000000002';
$peerWorship = '84000000-0000-4000-8000-000000000004';
$peerLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'peer',
    remoteInstanceId: $peerInstance,
    remoteOrganizationPublicId: $peerOrganization,
    remoteBaseUrl: 'https://peer-worship.example',
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
    'worship',
    [
        'id' => $peerWorship,
        'type' => 'worship',
        'status' => 'scheduled',
        'title' => 'Богослужение peer-узла',
        'service_type' => 'service',
        'organization_owner_id' => $peerOrganization,
        'starts_at' => '2099-01-01T09:00:00Z',
        'updated_at' => '2098-12-04T00:00:00Z',
        'url' => 'https://peer-worship.example/worship/peer',
    ],
    $siteKey,
);

$noScopeInstance = '91000000-0000-4000-8000-000000000001';
$noScopeOrganization = '92000000-0000-4000-8000-000000000002';
$noScopeWorship = '94000000-0000-4000-8000-000000000004';
$noScopeLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $noScopeInstance,
    remoteOrganizationPublicId: $noScopeOrganization,
    remoteBaseUrl: 'https://hidden-worship.example',
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
    'worship',
    [
        'id' => $noScopeWorship,
        'type' => 'worship',
        'status' => 'scheduled',
        'title' => 'Скрытое богослужение',
        'service_type' => 'service',
        'organization_owner_id' => $noScopeOrganization,
        'starts_at' => '2099-01-02T09:00:00Z',
        'updated_at' => '2098-12-05T00:00:00Z',
        'url' => 'https://hidden-worship.example/worship/hidden',
    ],
    $siteKey,
);

$feed = FederatedWorshipFeedService::fromDatabase()
    ->upcoming(
        $siteKey,
        20,
        new DateTimeImmutable('2098-01-01T00:00:00Z'),
    );

if (count($feed) !== 2) {
    fwrite(
        STDERR,
        "Агрегированная Worship-лента содержит лишние или потерянные записи.\n",
    );
    exit(1);
}

$byId = [];
foreach ($feed as $item) {
    $byId[(string) ($item['id'] ?? '')] = $item;
}

$local = $byId[$localId] ?? null;
$remote = $byId[$childWorship] ?? null;

if (
    !is_array($local)
    || ($local['source']['kind'] ?? null) !== 'local'
    || ($local['source']['organization_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Локальное богослужение потеряло владельца в агрегированной ленте.\n",
    );
    exit(1);
}

if (
    !is_array($remote)
    || ($remote['status'] ?? null) !== 'cancelled'
    || ($remote['service_type'] ?? null) !== 'divine_liturgy'
    || ($remote['source']['kind'] ?? null) !== 'federation'
    || ($remote['source']['instance_id'] ?? null)
        !== $childInstance
    || ($remote['source']['organization_id'] ?? null)
        !== $childOwner
    || ($remote['source']['name'] ?? null)
        !== 'Дочерний приход'
    || ($remote['source']['canonical_url'] ?? null)
        !== 'https://child-worship.example/worship/march'
    || ($remote['url'] ?? null)
        !== 'https://child-worship.example/worship/march'
    || ($remote['starts_at'] ?? null)
        !== '2099-03-01T07:00:00+00:00'
    || array_key_exists('description_html', $remote)
) {
    fwrite(
        STDERR,
        "Remote-богослужение потеряло source/status или раскрыло лишний payload.\n",
    );
    exit(1);
}

foreach ([
    $tombstoneId,
    $peerWorship,
    $noScopeWorship,
] as $forbiddenId) {
    if (isset($byId[$forbiddenId])) {
        fwrite(
            STDERR,
            "В агрегированную Worship-ленту попал запрещённый источник {$forbiddenId}.\n",
        );
        exit(1);
    }
}

if (($feed[0]['id'] ?? null) !== $childWorship) {
    fwrite(
        STDERR,
        "Агрегированная Worship-лента отсортирована не по времени начала.\n",
    );
    exit(1);
}

echo "Агрегированная Worship-лента с сохранением источника проверена\n";
