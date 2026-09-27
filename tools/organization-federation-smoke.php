<?php

declare(strict_types=1);

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SiteProfileCatalog;
use ChurchCMS\Modules\Organizations\FederationRepository;
use ChurchCMS\Modules\Organizations\FederationService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

foreach ([
    'parish',
    'monastery',
    'deanery',
    'diocese',
    'metropolia',
    'education',
] as $profile) {
    if (!SiteProfileCatalog::exists($profile)) {
        fwrite(
            STDERR,
            "Отсутствует профиль: {$profile}\n",
        );
        exit(1);
    }
}

$organizations = OrganizationService::fromDatabase();
$repository = OrganizationRepository::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовая епархия',
    'diocese',
);

if (
    $root->type !== 'diocese'
    || $repository->siteRoot()?->publicId
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Корневая организация епархии создана некорректно.\n",
    );
    exit(1);
}

$deaneryId = $organizations->create(
    name: 'Центральное благочиние',
    type: 'deanery',
    parentPublicId: $root->publicId,
);
$deanery = $repository->findByPublicId(
    $deaneryId,
);

if (
    $deanery === null
    || $deanery->parentId !== $root->id
    || $deanery->path
        !== '/testovaya-eparhiya/centralnoe-blagochinie'
) {
    fwrite(
        STDERR,
        "Благочиние не вошло в локальное дерево.\n",
    );
    exit(1);
}

$parishId = $organizations->create(
    name: 'Приход святителя Николая',
    type: 'parish',
    parentPublicId: $deaneryId,
);
$parish = $repository->findByPublicId(
    $parishId,
);

if (
    $parish === null
    || $parish->parentId !== $deanery->id
) {
    fwrite(
        STDERR,
        "Приход не вошёл в благочиние.\n",
    );
    exit(1);
}

$remoteInstance = '10000000-0000-4000-8000-000000000001';
$remoteOrganization = '20000000-0000-4000-8000-000000000002';
$token = 'ccms_federation_smoke_secret';

$linkId = FederationService::fromDatabase()->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'parent',
    remoteInstanceId: $remoteInstance,
    remoteOrganizationPublicId: $remoteOrganization,
    remoteBaseUrl: 'https://metropolia.example.test',
    inboundScopes: [
        'documents.read',
        'events.read',
        'documents.read',
    ],
    outboundScopes: [
        'content.read',
        'events.read',
    ],
    outboundToken: $token,
    remoteProfile: 'metropolia',
    remoteName: 'Тестовая митрополия',
);

$links = FederationRepository::fromDatabase()
    ->links();

if (
    count($links) !== 1
    || $links[0]->publicId !== $linkId
    || $links[0]->relation !== 'parent'
    || $links[0]->remoteInstanceId !== $remoteInstance
    || $links[0]->inboundScopes
        !== ['documents.read', 'events.read']
    || FederationRepository::fromDatabase()
        ->outboundToken($links[0]->id) !== $token
) {
    fwrite(
        STDERR,
        "Federation link сохранён некорректно.\n",
    );
    exit(1);
}

$pdo = DatabaseManager::getInstance()->connection();
$encrypted = $pdo->query(
    'SELECT outbound_token_encrypted
     FROM organization_federation_links
     LIMIT 1'
)->fetchColumn();

if (
    !is_string($encrypted)
    || $encrypted === ''
    || str_contains($encrypted, $token)
) {
    fwrite(
        STDERR,
        "Federation credential хранится небезопасно.\n",
    );
    exit(1);
}

try {
    FederationService::fromDatabase()->connect(
        localOrganizationPublicId: $root->publicId,
        relation: 'peer',
        remoteInstanceId: '30000000-0000-4000-8000-000000000003',
        remoteOrganizationPublicId:
            '40000000-0000-4000-8000-000000000004',
        remoteBaseUrl: 'http://public.example.org',
        outboundToken: 'must-not-travel-over-http',
    );

    fwrite(
        STDERR,
        "Public HTTP ошибочно разрешён для federation credential.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

if (
    $repository->siteRoot()?->parentId !== null
) {
    fwrite(
        STDERR,
        "Federation link ошибочно изменил локальную иерархию.\n",
    );
    exit(1);
}

$instanceId = (string) Config::get(
    'federation.instance_id',
    '',
);

if (
    $instanceId
        !== '50000000-0000-4000-8000-000000000005'
) {
    fwrite(
        STDERR,
        "Постоянный instance ID не загружен из конфигурации.\n",
    );
    exit(1);
}

echo "Organization/federation smoke OK\n";
