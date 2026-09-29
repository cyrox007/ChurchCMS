<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\FederationProjectionService;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Core\Rss2SyndicationRenderer;
use ChurchCMS\Core\SyndicationFeed;
use ChurchCMS\Modules\Publications\FederatedPublicationFeedService;
use ChurchCMS\Modules\Publications\FederatedPublicationSyndicationProvider;
use ChurchCMS\Modules\Publications\PublicationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'aggregated-feed-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия агрегированной ленты',
    'diocese',
    $siteKey,
);

$publications = PublicationService::fromDatabase();
$localId = $publications->createDraft(
    title: 'Локальная публикация епархии',
    excerpt: 'Локальный материал.',
    siteKey: $siteKey,
);
$publications->publish(
    $localId,
    new DateTimeImmutable(
        '2025-01-01T00:00:00Z',
    ),
);

$federation = FederationService::fromDatabase();
$projections = FederationProjectionService::fromDatabase();

$childInstance = 'a1000000-0000-4000-8000-000000000001';
$childOrganization = 'a2000000-0000-4000-8000-000000000002';
$childOwner = 'a3000000-0000-4000-8000-000000000003';
$childPublication = 'a4000000-0000-4000-8000-000000000004';

$childLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $childInstance,
    remoteOrganizationPublicId: $childOrganization,
    remoteBaseUrl: 'https://child.example',
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
    'publication',
    [
        'id' => $childPublication,
        'type' => 'news',
        'title' => 'Новость дочернего прихода',
        'excerpt' => 'Материал пришёл из дочернего узла.',
        'body_html' => '<script>не отдавать в агрегированной ленте</script>',
        'author' => 'Редакция прихода',
        'organization_owner_id' => $childOwner,
        'published_at' => '2026-01-01T00:00:00Z',
        'updated_at' => '2026-01-02T00:00:00Z',
        'url' => 'https://child.example/publications/news',
    ],
    $siteKey,
);

$tombstoneId = 'a5000000-0000-4000-8000-000000000005';
$projections->applyUpsert(
    $childLink,
    'publication',
    [
        'id' => $tombstoneId,
        'title' => 'Удалённый материал',
        'organization_owner_id' => $childOwner,
        'updated_at' => '2026-01-03T00:00:00Z',
        'url' => 'https://child.example/publications/deleted',
    ],
    $siteKey,
);
$projections->applyTombstone(
    $childLink,
    'publication',
    [
        'action' => 'delete',
        'id' => $tombstoneId,
        'organization_owner_id' => $childOwner,
        'reason' => 'withdrawn',
        'updated_at' => '2026-01-04T00:00:00Z',
    ],
    $siteKey,
);

$peerInstance = 'b1000000-0000-4000-8000-000000000001';
$peerOrganization = 'b2000000-0000-4000-8000-000000000002';
$peerPublication = 'b4000000-0000-4000-8000-000000000004';

$peerLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'peer',
    remoteInstanceId: $peerInstance,
    remoteOrganizationPublicId: $peerOrganization,
    remoteBaseUrl: 'https://peer.example',
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
    'publication',
    [
        'id' => $peerPublication,
        'title' => 'Материал peer-узла',
        'organization_owner_id' => $peerOrganization,
        'published_at' => '2026-02-01T00:00:00Z',
        'updated_at' => '2026-02-01T00:00:00Z',
        'url' => 'https://peer.example/publications/peer',
    ],
    $siteKey,
);

$parentInstance = 'c1000000-0000-4000-8000-000000000001';
$parentOrganization = 'c2000000-0000-4000-8000-000000000002';
$parentPublication = 'c4000000-0000-4000-8000-000000000004';

$parentLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'parent',
    remoteInstanceId: $parentInstance,
    remoteOrganizationPublicId: $parentOrganization,
    remoteBaseUrl: 'https://parent.example',
    inboundScopes: ['content.read'],
    remoteProfile: 'metropolia',
    remoteName: 'Вышестоящая митрополия',
    siteKey: $siteKey,
);
$federation->recordHealthSuccess(
    publicId: $parentLink,
    remoteInstanceId: $parentInstance,
    remoteOrganizationPublicId: $parentOrganization,
    remoteProfile: 'metropolia',
    remoteName: 'Вышестоящая митрополия',
    siteKey: $siteKey,
);
$projections->applyUpsert(
    $parentLink,
    'publication',
    [
        'id' => $parentPublication,
        'title' => 'Материал вышестоящего узла',
        'organization_owner_id' => $parentOrganization,
        'published_at' => '2026-03-01T00:00:00Z',
        'updated_at' => '2026-03-01T00:00:00Z',
        'url' => 'https://parent.example/publications/parent',
    ],
    $siteKey,
);

$noScopeInstance = 'd1000000-0000-4000-8000-000000000001';
$noScopeOrganization = 'd2000000-0000-4000-8000-000000000002';
$noScopePublication = 'd4000000-0000-4000-8000-000000000004';

$noScopeLink = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'child',
    remoteInstanceId: $noScopeInstance,
    remoteOrganizationPublicId: $noScopeOrganization,
    remoteBaseUrl: 'https://hidden-child.example',
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
    'publication',
    [
        'id' => $noScopePublication,
        'title' => 'Материал без разрешения на агрегацию',
        'organization_owner_id' => $noScopeOrganization,
        'published_at' => '2026-04-01T00:00:00Z',
        'updated_at' => '2026-04-01T00:00:00Z',
        'url' => 'https://hidden-child.example/publications/hidden',
    ],
    $siteKey,
);

$feed = FederatedPublicationFeedService::fromDatabase()
    ->latest(
        $siteKey,
        20,
    );

if (count($feed) !== 2) {
    fwrite(
        STDERR,
        "Агрегированная лента содержит лишние или потерянные материалы.\n",
    );
    exit(1);
}

$byId = [];
foreach ($feed as $item) {
    $byId[(string) ($item['id'] ?? '')] = $item;
}

$local = $byId[$localId] ?? null;
$remote = $byId[$childPublication] ?? null;

if (
    !is_array($local)
    || ($local['source']['kind'] ?? null) !== 'local'
    || ($local['source']['organization_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Локальный материал потерял владельца в агрегированной ленте.\n",
    );
    exit(1);
}

if (
    !is_array($remote)
    || ($remote['source']['kind'] ?? null)
        !== 'federation'
    || ($remote['source']['instance_id'] ?? null)
        !== $childInstance
    || ($remote['source']['organization_id'] ?? null)
        !== $childOwner
    || ($remote['source']['name'] ?? null)
        !== 'Дочерний приход'
    || ($remote['source']['canonical_url'] ?? null)
        !== 'https://child.example/publications/news'
    || ($remote['url'] ?? null)
        !== 'https://child.example/publications/news'
    || array_key_exists('body_html', $remote)
) {
    fwrite(
        STDERR,
        "Remote-публикация потеряла canonical source или раскрыла лишний payload.\n",
    );
    exit(1);
}

foreach ([
    $tombstoneId,
    $peerPublication,
    $parentPublication,
    $noScopePublication,
] as $forbiddenId) {
    if (isset($byId[$forbiddenId])) {
        fwrite(
            STDERR,
            "В агрегированную ленту попал запрещённый источник {$forbiddenId}.\n",
        );
        exit(1);
    }
}

if (($feed[0]['id'] ?? null) !== $childPublication) {
    fwrite(
        STDERR,
        "Агрегированная лента отсортирована не по времени публикации.\n",
    );
    exit(1);
}

$remoteEntries = iterator_to_array(
    (new FederatedPublicationSyndicationProvider($siteKey))
        ->entries(),
    false,
);

if (count($remoteEntries) !== 1) {
    fwrite(
        STDERR,
        "RSS provider federation-публикаций вернул неверное число элементов.\n",
    );
    exit(1);
}

$entry = $remoteEntries[0];
$expectedGuid = 'urn:churchcms:federation:'
    . $childInstance
    . ':publication:'
    . $childPublication;

if (
    $entry->id !== $expectedGuid
    || $entry->url !== 'https://child.example/publications/news'
    || $entry->sourceName !== 'Дочерний приход'
    || $entry->sourceUrl !== 'https://child.example/publications/news'
    || $entry->targets !== ['rss']
    || $entry->contentHtml !== ''
) {
    fwrite(
        STDERR,
        "RSS federation-публикации потерял source или раскрыл лишний payload.\n",
    );
    exit(1);
}

$xml = (new Rss2SyndicationRenderer())->render(
    new SyndicationFeed(
        title: 'Тестовая агрегированная лента',
        siteUrl: 'https://diocese.example/',
        description: 'Smoke',
        entries: $remoteEntries,
    ),
);

if (
    !str_contains($xml, '<source url="https://child.example/publications/news">Дочерний приход</source>')
    || !str_contains($xml, $expectedGuid)
    || str_contains($xml, '<script>')
) {
    fwrite(
        STDERR,
        "RSS XML не сохранил безопасный источник federation-публикации.\n",
    );
    exit(1);
}

echo "Агрегированная лента публикаций и RSS source проверены\n";
