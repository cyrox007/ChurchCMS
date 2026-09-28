<?php

declare(strict_types=1);

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SiteProfileCatalog;
use ChurchCMS\Core\Slugger;
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

$expectedDeaneryPath = '/'
    . Slugger::fromText('Тестовая епархия')
    . '/'
    . Slugger::fromText('Центральное благочиние');

if (
    $deanery === null
    || $deanery->parentId !== $root->id
    || $deanery->path !== $expectedDeaneryPath
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

$federation = FederationService::fromDatabase();
$federation->revoke($linkId);

$revoked = FederationRepository::fromDatabase()
    ->findByPublicId($linkId);

if (
    $revoked === null
    || $revoked->status !== 'revoked'
    || FederationRepository::fromDatabase()
        ->outboundToken($revoked->id) !== null
) {
    fwrite(
        STDERR,
        "Отзыв federation trust не очистил credential.\n",
    );
    exit(1);
}

$replacementToken = 'ccms_federation_smoke_secret_reconnected';
$reconnectedId = $federation->connect(
    localOrganizationPublicId: $root->publicId,
    relation: 'parent',
    remoteInstanceId: $remoteInstance,
    remoteOrganizationPublicId: $remoteOrganization,
    remoteBaseUrl: 'https://metropolia.example.test',
    inboundScopes: [
        'documents.read',
    ],
    outboundScopes: [
        'content.read',
    ],
    outboundToken: $replacementToken,
    remoteProfile: 'metropolia',
    remoteName: 'Тестовая митрополия после переподключения',
);

$reconnected = FederationRepository::fromDatabase()
    ->findByPublicId($reconnectedId);

if (
    $reconnectedId !== $linkId
    || $reconnected === null
    || $reconnected->status !== 'pending'
    || $reconnected->remoteName
        !== 'Тестовая митрополия после переподключения'
    || FederationRepository::fromDatabase()
        ->outboundToken($reconnected->id)
        !== $replacementToken
) {
    fwrite(
        STDERR,
        "Повторное federation pairing после revoke некорректно.\n",
    );
    exit(1);
}

try {
    FederationService::fromDatabase()->connect(
        localOrganizationPublicId: $root->publicId,
        relation: 'peer',
        remoteInstanceId: (string) Config::get(
            'federation.instance_id',
            '',
        ),
        remoteOrganizationPublicId:
            '40000000-0000-4000-8000-000000000004',
        remoteBaseUrl: 'https://self.example.test',
    );

    fwrite(
        STDERR,
        "Связь ChurchCMS с самим собой ошибочно разрешена.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
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

foreach ([
    'https://operator:secret@remote.example.test',
    'https://remote.example.test?token=secret',
    'https://remote.example.test#metadata',
] as $unsafeBaseUrl) {
    try {
        FederationService::fromDatabase()->connect(
            localOrganizationPublicId: $root->publicId,
            relation: 'peer',
            remoteInstanceId:
                '30000000-0000-4000-8000-000000000003',
            remoteOrganizationPublicId:
                '40000000-0000-4000-8000-000000000004',
            remoteBaseUrl: $unsafeBaseUrl,
        );

        fwrite(
            STDERR,
            "Federation URL с userinfo/query/fragment ошибочно разрешён.\n",
        );
        exit(1);
    } catch (InvalidArgumentException) {
    }
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
