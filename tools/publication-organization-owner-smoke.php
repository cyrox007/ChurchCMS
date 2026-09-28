<?php

declare(strict_types=1);

use ChurchCMS\App\Services\AuthorizationService;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationApiResource;
use ChurchCMS\Modules\Publications\PublicationAdminSearchProvider;
use ChurchCMS\Modules\Publications\PublicationAdminTaskProvider;
use ChurchCMS\Modules\Publications\PublicationOrganizationAccessService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$publications = PublicationService::fromDatabase();
$repository = PublicationRepository::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовый приход',
    'parish',
);
$departmentId = $organizations->create(
    name: 'Миссионерский отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);
$departmentChildId = $organizations->create(
    name: 'Молодёжное направление',
    type: 'department',
    parentPublicId: $departmentId,
);
$otherDepartmentId = $organizations->create(
    name: 'Социальный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);

$publicationId = $publications->createDraft(
    title: 'Материал с каноническим владельцем',
);
$publication = $repository->findByPublicId($publicationId);

if (
    $publication === null
    || $publication->ownerOrganizationPublicId
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "Новая публикация не получила site root владельцем.\n",
    );
    exit(1);
}

$resource = (new PublicationApiResource($publication))->toApiArray();
if (
    ($resource['organization_owner_id'] ?? null)
        !== $root->publicId
) {
    fwrite(
        STDERR,
        "API не вернул stable public ID владельца.\n",
    );
    exit(1);
}

$publications->assignOrganizationOwner(
    $publicationId,
    $departmentId,
);
$reassigned = $repository->findByPublicId($publicationId);

if (
    $reassigned === null
    || $reassigned->ownerOrganizationPublicId
        !== $departmentId
) {
    fwrite(STDERR, "Владелец публикации не изменился.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход',
    'parish',
    'foreign',
);

try {
    $publications->assignOrganizationOwner(
        $publicationId,
        $foreignRoot->publicId,
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца из другого site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE publications
     SET owner_organization_public_id = :owner
     WHERE public_id = :public_id'
);

try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'public_id' => $publicationId,
    ]);
    fwrite(
        STDERR,
        "База данных разрешила владельца из другого site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

$insidePublicationId = $publications->createDraft(
    title: 'Материал доступной ветки',
    ownerOrganizationPublicId: $departmentChildId,
);
$outsidePublicationId = $publications->createDraft(
    title: 'Материал соседней ветки',
    ownerOrganizationPublicId: $otherDepartmentId,
);
$insidePublication = $repository->findByPublicId(
    $insidePublicationId,
);
$outsidePublication = $repository->findByPublicId(
    $outsidePublicationId,
);

if ($insidePublication === null || $outsidePublication === null) {
    fwrite(STDERR, "Не удалось подготовить публикации для RBAC smoke.\n");
    exit(1);
}

$testUser = $pdo->prepare(
    'INSERT INTO admin_users (
        public_id, username, password_hash, display_name, email,
        status, created_at, updated_at
     ) VALUES (
        :public_id, :username, :password_hash, :display_name, :email,
        :status, :created_at, :updated_at
     )'
);
$testUser->execute([
    'public_id' => '71000000-0000-4000-8000-000000000007',
    'username' => 'publication-owner-scope-smoke',
    'password_hash' => password_hash(
        'publication-owner-scope-smoke-password',
        PASSWORD_DEFAULT,
    ),
    'display_name' => 'Редактор ветки публикаций',
    'email' => 'publication-owner-scope@example.invalid',
    'status' => 'active',
    'created_at' => '2026-09-28 00:00:00',
    'updated_at' => '2026-09-28 00:00:00',
]);
$userIdLookup = $pdo->prepare(
    'SELECT id
     FROM admin_users
     WHERE username = :username
     LIMIT 1'
);
$userIdLookup->execute([
    'username' => 'publication-owner-scope-smoke',
]);
$testUserId = (int) $userIdLookup->fetchColumn();

$testRole = $pdo->prepare(
    'INSERT INTO roles (role_key, name)
     VALUES (:role_key, :name)'
);
$testRole->execute([
    'role_key' => 'publication_owner_scope_smoke',
    'name' => 'Редактор публикаций ограниченной ветки',
]);
$roleIdLookup = $pdo->prepare(
    'SELECT id
     FROM roles
     WHERE role_key = :role_key
     LIMIT 1'
);
$roleIdLookup->execute([
    'role_key' => 'publication_owner_scope_smoke',
]);
$testRoleId = (int) $roleIdLookup->fetchColumn();

if ($testUserId <= 0 || $testRoleId <= 0) {
    fwrite(
        STDERR,
        "Не удалось подготовить пользователя или роль RBAC smoke.\n",
    );
    exit(1);
}

$pdo->prepare(
    'INSERT INTO admin_user_roles (user_id, role_id)
     VALUES (:user_id, :role_id)'
)->execute([
    'user_id' => $testUserId,
    'role_id' => $testRoleId,
]);

$permissionIds = $pdo->query(
    "SELECT id
     FROM permissions
     WHERE permission_key IN (
        'publications.read',
        'publications.create',
        'publications.edit',
        'publications.publish'
     )
     ORDER BY permission_key"
)->fetchAll(PDO::FETCH_COLUMN);

if (count($permissionIds) !== 4) {
    fwrite(STDERR, "Publication permissions не найдены.\n");
    exit(1);
}

$assignPermission = $pdo->prepare(
    'INSERT INTO role_permissions (role_id, permission_id)
     VALUES (:role_id, :permission_id)'
);
foreach ($permissionIds as $permissionId) {
    $assignPermission->execute([
        'role_id' => $testRoleId,
        'permission_id' => (int) $permissionId,
    ]);
}

$pdo->prepare(
    'INSERT INTO admin_role_scopes (
        user_id, role_id, site_key, scope_type, scope_key
     ) VALUES (
        :user_id, :role_id, :site_key, :scope_type, :scope_key
     )'
)->execute([
    'user_id' => $testUserId,
    'role_id' => $testRoleId,
    'site_key' => 'default',
    'scope_type' => 'organization',
    'scope_key' => $departmentId,
]);

$organizationRepository = OrganizationRepository::fromDatabase();
$publicationAccess = new PublicationOrganizationAccessService(
    new OrganizationAccessService(
        new AuthorizationService($pdo),
        $organizationRepository,
    ),
    $organizationRepository,
);
$availableOwners = $publicationAccess->availableOwners(
    $testUserId,
    'publications.edit',
);
$availableOwnerIds = array_map(
    static fn($unit): string => $unit->publicId,
    $availableOwners,
);

if (
    !in_array($departmentId, $availableOwnerIds, true)
    || !in_array($departmentChildId, $availableOwnerIds, true)
    || in_array($root->publicId, $availableOwnerIds, true)
    || in_array($otherDepartmentId, $availableOwnerIds, true)
) {
    fwrite(STDERR, "Список владельцев вышел за organization scope.\n");
    exit(1);
}

if (
    !$publicationAccess->canAccess(
        $testUserId,
        'publications.edit',
        $insidePublication,
    )
    || $publicationAccess->canAccess(
        $testUserId,
        'publications.edit',
        $outsidePublication,
    )
    || $publicationAccess->assignableOwner(
        $testUserId,
        'publications.edit',
        $otherDepartmentId,
    ) !== null
) {
    fwrite(STDERR, "Organization scope не ограничил действия с публикацией.\n");
    exit(1);
}

$ownerPublicIds = $publicationAccess->visibleOwnerPublicIds(
    $testUserId,
    'publications.read',
);
$visiblePublicationIds = array_map(
    static fn($item): string => $item->publicId,
    $repository->adminList(
        ownerPublicIds: $ownerPublicIds,
    ),
);
if (
    !in_array($insidePublicationId, $visiblePublicationIds, true)
    || in_array($outsidePublicationId, $visiblePublicationIds, true)
) {
    fwrite(STDERR, "Admin-список не соблюдает organization scope.\n");
    exit(1);
}

$searchResults = (new PublicationAdminSearchProvider())->search(
    'Материал',
    8,
    $testUserId,
);
$searchPublicIds = array_map(
    static fn(array $result): string =>
        $result['route_params']['publicId'] ?? '',
    $searchResults,
);
if (
    !in_array($insidePublicationId, $searchPublicIds, true)
    || in_array($outsidePublicationId, $searchPublicIds, true)
) {
    fwrite(STDERR, "Глобальный Admin-поиск нарушил organization scope.\n");
    exit(1);
}

$tasks = (new PublicationAdminTaskProvider())->tasks(
    5,
    $testUserId,
);
if (($tasks[0]['count'] ?? null) !== 2) {
    fwrite(STDERR, "Счётчик редакционных задач нарушил organization scope.\n");
    exit(1);
}

$publications->update(
    publicId: $insidePublication->publicId,
    title: $insidePublication->title,
    slug: $insidePublication->slug,
    type: $insidePublication->type,
    excerpt: $insidePublication->excerpt,
    bodyHtml: $insidePublication->bodyHtml,
    authorName: $insidePublication->authorName,
    syndicationTargets: $insidePublication->syndicationTargets,
    commentsEnabled: $insidePublication->commentsEnabled,
    ownerOrganizationPublicId: $departmentId,
);
$updatedOwner = $repository->findByPublicId(
    $insidePublication->publicId,
)?->ownerOrganizationPublicId;

if ($updatedOwner !== $departmentId) {
    fwrite(STDERR, "Update публикации не сохранил выбранного владельца.\n");
    exit(1);
}

$editor = ThemeRenderer::fromConfig()->capture(
    'admin.publications.editor',
    [
        'publication' => null,
        'form' => [
            'type' => 'news',
            'title' => '',
            'excerpt' => '',
            'body' => '',
            'author_name' => '',
            'owner_organization_public_id' => $departmentId,
            'slug' => '',
            'comments_enabled' => false,
            'categories' => '',
            'tags' => '',
        ],
        'organizationUnits' => $availableOwners,
        'error' => null,
        'success' => null,
        'canPublish' => false,
        'canSyndicate' => false,
    ],
);

foreach ([
    'name="owner_organization_public_id"',
    $departmentId,
    $departmentChildId,
    'Доступны только активные организации',
] as $expected) {
    if (!str_contains($editor, $expected)) {
        fwrite(STDERR, "Редактор не содержит owner control: {$expected}\n");
        exit(1);
    }
}

if (str_contains($editor, $otherDepartmentId)) {
    fwrite(STDERR, "Редактор показал владельца вне organization scope.\n");
    exit(1);
}

$legacyId = $publications->createDraft(
    title: 'Legacy site без корневой организации',
    siteKey: 'legacy',
);
$legacy = $repository->findByPublicId($legacyId, 'legacy');

if (
    $legacy === null
    || $legacy->ownerOrganizationPublicId !== null
) {
    fwrite(
        STDERR,
        "Legacy-совместимость nullable owner нарушена.\n",
    );
    exit(1);
}

echo "Publication organization owner smoke OK\n";
