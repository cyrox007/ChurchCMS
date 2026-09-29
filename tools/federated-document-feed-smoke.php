<?php

declare(strict_types=1);

use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Documents\FederatedDocumentFeedService;
use ChurchCMS\Modules\Organizations\FederationProjectionService;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$siteKey = 'aggregated-document-feed-smoke';
$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия документов',
    'diocese',
    $siteKey,
);

$documents = DocumentService::fromDatabase();

$localId = $documents->createDraft(
    title: 'Локальный публичный указ',
    siteKey: $siteKey,
    documentType: 'decree',
    documentNumber: '10/2099',
    issuedOn: '2099-04-02',
    summary: 'Локальная публичная карточка.',
);
$documents->publish($localId, $siteKey);
$documents->setVisibility($localId, 'public', $siteKey);

$privateId = $documents->createDraft(
    title: 'Приватный документ',
    siteKey: $siteKey,
    documentType: 'notice',
    issuedOn: '2099-04-05',
);
$documents->publish($privateId, $siteKey);

$federatedLocalId = $documents->createDraft(
    title: 'Только для federation',
    siteKey: $siteKey,
    documentType: 'decree',
    issuedOn: '2099-04-06',
);
$documents->publish($federatedLocalId, $siteKey);
$documents->setVisibility(
    $federatedLocalId,
    'federated',
    $siteKey,
);

$federation = FederationService::fromDatabase();
$projections = FederationProjectionService::fromDatabase();

function documentAggregationLink(
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

$childInstance = 'd1000000-0000-4000-8000-000000000001';
$childOrganization = 'd2000000-0000-4000-8000-000000000002';
$childOwner = 'd3000000-0000-4000-8000-000000000003';
$childDocument = 'd4000000-0000-4000-8000-000000000004';

$childLink = documentAggregationLink(
    $federation,
    $siteKey,
    $root->publicId,
    'child',
    $childInstance,
    $childOrganization,
    'https://child-documents.example',
    ['content.read'],
    'Дочерний приход документов',
);

$projections->applyUpsert(
    $childLink,
    'document',
    [
        'id' => $childDocument,
        'type' => 'document',
        'title' => 'Указ дочернего прихода',
        'document_type' => 'decree',
        'document_number' => '20/2099',
        'issued_on' => '2099-04-03',
        'summary' => 'Удалённая публичная карточка.',
        'organization_owner_id' => $childOwner,
        'updated_at' => '2099-04-03T12:00:00Z',
        'url' => 'https://child-documents.example/documents/20',
        'internal_note' => 'НЕ ДОЛЖНО ПОПАСТЬ НАРУЖУ',
    ],
    $siteKey,
);

$deletedDocument = 'd5000000-0000-4000-8000-000000000005';
$projections->applyUpsert(
    $childLink,
    'document',
    [
        'id' => $deletedDocument,
        'type' => 'document',
        'title' => 'Удалённый документ',
        'document_type' => 'notice',
        'organization_owner_id' => $childOwner,
        'updated_at' => '2099-04-04T09:00:00Z',
        'url' => null,
    ],
    $siteKey,
);
$projections->applyTombstone(
    $childLink,
    'document',
    [
        'id' => $deletedDocument,
        'action' => 'delete',
        'reason' => 'withdrawn',
        'organization_owner_id' => $childOwner,
        'updated_at' => '2099-04-04T10:00:00Z',
    ],
    $siteKey,
);

$peerLink = documentAggregationLink(
    $federation,
    $siteKey,
    $root->publicId,
    'peer',
    'd6000000-0000-4000-8000-000000000006',
    'd7000000-0000-4000-8000-000000000007',
    'https://peer-documents.example',
    ['content.read'],
    'Равноправный узел документов',
);
$projections->applyUpsert(
    $peerLink,
    'document',
    [
        'id' => 'd8000000-0000-4000-8000-000000000008',
        'type' => 'document',
        'title' => 'Документ peer-узла',
        'document_type' => 'notice',
        'organization_owner_id' =>
            'd9000000-0000-4000-8000-000000000009',
        'updated_at' => '2099-04-07T10:00:00Z',
        'url' => null,
    ],
    $siteKey,
);

$noScopeLink = documentAggregationLink(
    $federation,
    $siteKey,
    $root->publicId,
    'child',
    'da000000-0000-4000-8000-00000000000a',
    'db000000-0000-4000-8000-00000000000b',
    'https://no-scope-documents.example',
    [],
    'Дочерний узел без scope',
);
$projections->applyUpsert(
    $noScopeLink,
    'document',
    [
        'id' => 'dc000000-0000-4000-8000-00000000000c',
        'type' => 'document',
        'title' => 'Документ без разрешения',
        'document_type' => 'notice',
        'organization_owner_id' =>
            'dd000000-0000-4000-8000-00000000000d',
        'updated_at' => '2099-04-08T10:00:00Z',
        'url' => null,
    ],
    $siteKey,
);

$items = FederatedDocumentFeedService::fromDatabase()
    ->latest($siteKey, 20);

if (count($items) !== 2) {
    fwrite(
        STDERR,
        "Агрегированная Documents-лента вернула лишние записи.\n",
    );
    exit(1);
}

$remote = $items[0] ?? [];
$local = $items[1] ?? [];

if (
    ($remote['id'] ?? null) !== $childDocument
    || ($remote['source']['kind'] ?? null) !== 'federation'
    || ($remote['source']['instance_id'] ?? null)
        !== $childInstance
    || ($remote['source']['organization_id'] ?? null)
        !== $childOwner
    || ($remote['source']['name'] ?? null)
        !== 'Дочерний приход документов'
    || ($remote['url'] ?? null)
        !== 'https://child-documents.example/documents/20'
    || ($remote['issued_on'] ?? null) !== '2099-04-03'
) {
    fwrite(
        STDERR,
        "Remote Documents projection потеряла источник или поля.\n",
    );
    exit(1);
}

if (
    ($local['id'] ?? null) !== $localId
    || ($local['source']['kind'] ?? null) !== 'local'
    || ($local['source']['organization_id'] ?? null)
        !== $root->publicId
    || ($local['issued_on'] ?? null) !== '2099-04-02'
) {
    fwrite(
        STDERR,
        "Локальный публичный документ агрегирован неверно.\n",
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
    'НЕ ДОЛЖНО ПОПАСТЬ НАРУЖУ',
    $privateId,
    $federatedLocalId,
    $deletedDocument,
    'Документ peer-узла',
    'Документ без разрешения',
] as $forbidden) {
    if (str_contains($json, $forbidden)) {
        fwrite(
            STDERR,
            "Агрегированная Documents-лента раскрыла запрещённую запись.\n",
        );
        exit(1);
    }
}

echo "Агрегированная Documents-лента и сохранение источника проверены\n";
