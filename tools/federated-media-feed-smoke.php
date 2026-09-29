<?php

declare(strict_types=1);

use ChurchCMS\Modules\Media\FederatedMediaFeedService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\FederationProjectionService;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'aggregated-media-feed-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия медиатеки',
    'diocese',
    $siteKey,
);

$media = MediaService::fromDatabase();

$publicId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'public.jpg',
    mimeType: 'image/jpeg',
    bytes: 1200,
    sha256: hash('sha256', 'public-media'),
    siteKey: $siteKey,
    title: 'Публичная карточка',
    altText: 'Публичное изображение',
);
$media->setVisibility($publicId, 'public', $siteKey);

$privateId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'private.jpg',
    mimeType: 'image/jpeg',
    bytes: 1300,
    sha256: hash('sha256', 'private-media'),
    siteKey: $siteKey,
);
$federatedLocalId = $media->registerMetadata(
    mediaType: 'document',
    originalName: 'federated.pdf',
    mimeType: 'application/pdf',
    bytes: 1400,
    sha256: hash('sha256', 'federated-local-media'),
    siteKey: $siteKey,
);
$media->setVisibility(
    $federatedLocalId,
    'federated',
    $siteKey,
);

$federation = FederationService::fromDatabase();
$projections = FederationProjectionService::fromDatabase();

function mediaAggregationLink(
    FederationService $federation,
    string $siteKey,
    string $localOrganizationPublicId,
    string $relation,
    string $instanceId,
    string $organizationId,
    string $baseUrl,
    array $inboundScopes,
    string $name,
): string {
    $publicId = $federation->connect(
        localOrganizationPublicId: $localOrganizationPublicId,
        relation: $relation,
        remoteInstanceId: $instanceId,
        remoteOrganizationPublicId: $organizationId,
        remoteBaseUrl: $baseUrl,
        inboundScopes: $inboundScopes,
        remoteProfile: 'parish',
        remoteName: $name,
        siteKey: $siteKey,
    );
    $federation->recordHealthSuccess(
        publicId: $publicId,
        remoteInstanceId: $instanceId,
        remoteOrganizationPublicId: $organizationId,
        remoteProfile: 'parish',
        remoteName: $name,
        siteKey: $siteKey,
    );

    return $publicId;
}

$childInstance = 'aa000000-0000-4000-8000-000000000001';
$childOrganization = 'ab000000-0000-4000-8000-000000000002';
$childOwner = 'ac000000-0000-4000-8000-000000000003';
$childMedia = 'ad000000-0000-4000-8000-000000000004';

$childLink = mediaAggregationLink(
    $federation,
    $siteKey,
    $root->publicId,
    'child',
    $childInstance,
    $childOrganization,
    'https://child-media.example',
    ['content.read'],
    'Дочерний приход медиатеки',
);
$projections->applyUpsert(
    $childLink,
    'media',
    [
        'id' => $childMedia,
        'type' => 'media',
        'media_type' => 'image',
        'mime_type' => 'image/jpeg',
        'bytes' => 2048,
        'sha256' => hash('sha256', 'remote-media'),
        'title' => 'Карточка дочернего прихода',
        'alt_text' => 'Удалённое изображение',
        'organization_owner_id' => $childOwner,
        'blob_available' => false,
        'updated_at' => '2099-04-03T12:00:00Z',
        'url' => null,
        'original_name' => 'НЕ ДОЛЖНО ПОПАСТЬ НАРУЖУ.jpg',
        'filesystem_path' => '/secret/path.jpg',
    ],
    $siteKey,
);

$deletedMedia = 'ae000000-0000-4000-8000-000000000005';
$projections->applyUpsert(
    $childLink,
    'media',
    [
        'id' => $deletedMedia,
        'media_type' => 'image',
        'mime_type' => 'image/jpeg',
        'bytes' => 1,
        'sha256' => hash('sha256', 'deleted-media'),
        'organization_owner_id' => $childOwner,
        'blob_available' => false,
        'updated_at' => '2099-04-04T09:00:00Z',
    ],
    $siteKey,
);
$projections->applyTombstone(
    $childLink,
    'media',
    [
        'id' => $deletedMedia,
        'action' => 'delete',
        'reason' => 'withdrawn',
        'organization_owner_id' => $childOwner,
        'updated_at' => '2099-04-04T10:00:00Z',
    ],
    $siteKey,
);

$peerLink = mediaAggregationLink(
    $federation,
    $siteKey,
    $root->publicId,
    'peer',
    'ba000000-0000-4000-8000-000000000001',
    'bb000000-0000-4000-8000-000000000002',
    'https://peer-media.example',
    ['content.read'],
    'Peer медиатеки',
);
$projections->applyUpsert(
    $peerLink,
    'media',
    [
        'id' => 'bc000000-0000-4000-8000-000000000003',
        'media_type' => 'image',
        'mime_type' => 'image/jpeg',
        'bytes' => 1,
        'sha256' => hash('sha256', 'peer-media'),
        'organization_owner_id' =>
            'bd000000-0000-4000-8000-000000000004',
        'blob_available' => false,
        'updated_at' => '2099-04-05T10:00:00Z',
    ],
    $siteKey,
);

$noScopeLink = mediaAggregationLink(
    $federation,
    $siteKey,
    $root->publicId,
    'child',
    'ca000000-0000-4000-8000-000000000001',
    'cb000000-0000-4000-8000-000000000002',
    'https://hidden-media.example',
    [],
    'Узел без scope',
);
$projections->applyUpsert(
    $noScopeLink,
    'media',
    [
        'id' => 'cc000000-0000-4000-8000-000000000003',
        'media_type' => 'image',
        'mime_type' => 'image/jpeg',
        'bytes' => 1,
        'sha256' => hash('sha256', 'hidden-media'),
        'organization_owner_id' =>
            'cd000000-0000-4000-8000-000000000004',
        'blob_available' => false,
        'updated_at' => '2099-04-06T10:00:00Z',
    ],
    $siteKey,
);

$items = FederatedMediaFeedService::fromDatabase()
    ->latest($siteKey, 20);

if (count($items) !== 2) {
    fwrite(
        STDERR,
        "Агрегированная Media-лента вернула лишние записи.\n",
    );
    exit(1);
}

$byId = [];
foreach ($items as $item) {
    $byId[(string) ($item['id'] ?? '')] = $item;
}

$local = $byId[$publicId] ?? null;
$remote = $byId[$childMedia] ?? null;

if (
    !is_array($local)
    || ($local['source']['kind'] ?? null) !== 'local'
    || ($local['source']['organization_id'] ?? null)
        !== $root->publicId
    || ($local['blob_available'] ?? null) !== false
    || array_key_exists('original_name', $local)
) {
    fwrite(
        STDERR,
        "Локальная публичная Media-карточка агрегирована неверно.\n",
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
        !== 'Дочерний приход медиатеки'
    || ($remote['blob_available'] ?? null) !== false
    || array_key_exists('original_name', $remote)
    || array_key_exists('filesystem_path', $remote)
) {
    fwrite(
        STDERR,
        "Remote Media потеряла источник или раскрыла лишний payload.\n",
    );
    exit(1);
}

$json = json_encode(
    $items,
    JSON_THROW_ON_ERROR
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES,
);

foreach ([
    $privateId,
    $federatedLocalId,
    $deletedMedia,
    'bc000000-0000-4000-8000-000000000003',
    'cc000000-0000-4000-8000-000000000003',
    'НЕ ДОЛЖНО ПОПАСТЬ НАРУЖУ.jpg',
    '/secret/path.jpg',
] as $forbidden) {
    if (str_contains($json, $forbidden)) {
        fwrite(
            STDERR,
            "Агрегированная Media-лента раскрыла запрещённую запись.\n",
        );
        exit(1);
    }
}

echo "Агрегированная Media-лента с сохранением источника проверена\n";
