<?php

declare(strict_types=1);

use ChurchCMS\App\Services\AuthorizationService;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Pages\PageOrganizationAccessService;
use ChurchCMS\Modules\Pages\PageRepository;
use ChurchCMS\Modules\Pages\PageService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Приход проверки Pages Admin',
    'parish',
);
$editorialId = $organizations->create(
    name: 'Редакция постоянных страниц',
    type: 'department',
    parentPublicId: $root->publicId,
);
$editorialChildId = $organizations->create(
    name: 'Редакция раздела паломникам',
    type: 'department',
    parentPublicId: $editorialId,
);
$otherId = $organizations->create(
    name: 'Недоступный отдел Pages Admin',
    type: 'department',
    parentPublicId: $root->publicId,
);

$pages = PageService::fromDatabase();
$repository = PageRepository::fromDatabase();
$sectionId = $pages->createDraft(
    title: 'Доступный раздел Pages Admin',
    slug: 'pages-admin-visible',
    ownerOrganizationPublicId: $editorialId,
);
$childId = $pages->createDraft(
    title: 'Дочерняя страница Pages Admin',
    slug: 'child',
    parentPublicId: $sectionId,
    ownerOrganizationPublicId: $editorialChildId,
);
$targetId = $pages->createDraft(
    title: 'Целевой раздел Pages Admin',
    slug: 'pages-admin-target',
    ownerOrganizationPublicId: $editorialId,
);
$outsideId = $pages->createDraft(
    title: 'Недоступная страница Pages Admin',
    slug: 'pages-admin-hidden',
    ownerOrganizationPublicId: $otherId,
);

$pages->update(
    publicId: $sectionId,
    title: 'Перемещённый раздел Pages Admin',
    slug: 'moved',
    bodyInput: 'Обновлённое содержимое',
    navigationTitle: 'Паломникам',
    parentPublicId: $targetId,
    sortOrder: 20,
    ownerOrganizationPublicId: $editorialChildId,
);
$section = $repository->findByPublicId($sectionId);
$child = $repository->findByPublicId($childId);

if (
    $section?->path !== '/pages-admin-target/moved'
    || $section->ownerOrganizationPublicId !== $editorialChildId
    || $section->navigationTitle !== 'Паломникам'
    || $section->sortOrder !== 20
    || $child?->path !== '/pages-admin-target/moved/child'
) {
    fwrite(
        STDERR,
        "Атомарное сохранение Pages Admin потеряло дерево или владельца.\n",
    );
    exit(1);
}

$pdo = DatabaseManager::getInstance()->connection();
$permissionKeys = $pdo->query(
    "SELECT permission_key
     FROM permissions
     WHERE permission_key IN (
        'pages.read',
        'pages.create',
        'pages.edit',
        'pages.publish'
     )
     ORDER BY permission_key"
)->fetchAll(PDO::FETCH_COLUMN);

if ($permissionKeys !== [
    'pages.create',
    'pages.edit',
    'pages.publish',
    'pages.read',
]) {
    fwrite(STDERR, "Page permissions не созданы миграцией.\n");
    exit(1);
}

$testUser = $pdo->prepare(
    'INSERT INTO admin_users (
        public_id, username, password_hash, display_name, email,
        status, created_at, updated_at
     ) VALUES (
        :public_id, :username, :password_hash, :display_name, :email,
        :status, :created_at, :updated_at
     ) RETURNING id'
);
$testUser->execute([
    'public_id' => '72000000-0000-4000-8000-000000000007',
    'username' => 'page-owner-scope-smoke',
    'password_hash' => password_hash(
        'page-owner-scope-smoke-password',
        PASSWORD_DEFAULT,
    ),
    'display_name' => 'Редактор ветки страниц',
    'email' => 'page-owner-scope@example.invalid',
    'status' => 'active',
    'created_at' => '2026-09-28 00:00:00',
    'updated_at' => '2026-09-28 00:00:00',
]);
$testUserId = (int) $testUser->fetchColumn();

$testRole = $pdo->prepare(
    'INSERT INTO roles (role_key, name)
     VALUES (:role_key, :name)
     RETURNING id'
);
$testRole->execute([
    'role_key' => 'page_owner_scope_smoke',
    'name' => 'Редактор страниц ограниченной ветки',
]);
$testRoleId = (int) $testRole->fetchColumn();

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
        'pages.read',
        'pages.create',
        'pages.edit',
        'pages.publish'
     )"
)->fetchAll(PDO::FETCH_COLUMN);
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
    'scope_key' => $editorialId,
]);

$organizationRepository = OrganizationRepository::fromDatabase();
$access = new PageOrganizationAccessService(
    new OrganizationAccessService(
        new AuthorizationService($pdo),
        $organizationRepository,
    ),
    $organizationRepository,
    $repository,
);
$availableOwnerIds = array_map(
    static fn($unit): string => $unit->publicId,
    $access->availableOwners($testUserId, 'pages.edit'),
);

if (
    !in_array($editorialId, $availableOwnerIds, true)
    || !in_array($editorialChildId, $availableOwnerIds, true)
    || in_array($root->publicId, $availableOwnerIds, true)
    || in_array($otherId, $availableOwnerIds, true)
    || $access->defaultOwnerPublicId(
        $testUserId,
        'pages.create',
    ) !== $editorialId
) {
    fwrite(STDERR, "Выбор владельца Pages вышел за organization scope.\n");
    exit(1);
}

$section = $repository->findByPublicId($sectionId);
$outside = $repository->findByPublicId($outsideId);
if (
    $section === null
    || $outside === null
    || !$access->canAccess($testUserId, 'pages.edit', $section)
    || $access->canAccess($testUserId, 'pages.edit', $outside)
    || $access->assignableOwner(
        $testUserId,
        'pages.edit',
        $otherId,
    ) !== null
) {
    fwrite(STDERR, "Organization scope не ограничил действия Pages.\n");
    exit(1);
}

$visibleOwnerIds = $access->visibleOwnerPublicIds(
    $testUserId,
    'pages.read',
);
$visiblePageIds = array_map(
    static fn($page): string => $page->publicId,
    $repository->adminTree(ownerPublicIds: $visibleOwnerIds),
);

if (
    !in_array($sectionId, $visiblePageIds, true)
    || !in_array($childId, $visiblePageIds, true)
    || in_array($outsideId, $visiblePageIds, true)
) {
    fwrite(STDERR, "Admin-дерево Pages нарушило organization scope.\n");
    exit(1);
}

if (!$access->canAccessSubtree(
    $testUserId,
    'pages.edit',
    $section,
)) {
    fwrite(STDERR, "Доступное поддерево Pages ошибочно запрещено.\n");
    exit(1);
}

$foreignChildId = $pages->createDraft(
    title: 'Чужой дочерний владелец',
    slug: 'foreign-owner',
    parentPublicId: $sectionId,
    ownerOrganizationPublicId: $otherId,
);

if (
    $repository->findByPublicId($foreignChildId) === null
    || $access->canAccessSubtree(
        $testUserId,
        'pages.edit',
        $section,
    )
) {
    fwrite(STDERR, "Проверка полномочий всего поддерева Pages не сработала.\n");
    exit(1);
}

$router = Router::getInstance();
if (
    $router->url('admin_pages') !== '/admin/pages'
    || $router->url('admin_page_new') !== '/admin/pages/new'
    || $router->url(
        'admin_page_edit',
        ['publicId' => $sectionId],
    ) !== '/admin/pages/' . $sectionId
    || $router->url(
        'admin_page_publish',
        ['publicId' => $sectionId],
    ) !== '/admin/pages/' . $sectionId . '/publish'
) {
    fwrite(STDERR, "Маршруты Pages Admin не зарегистрированы.\n");
    exit(1);
}

$navigation = \ChurchCMS\App\Services\AdminNavigationRegistry::entries();
$pageNavigation = null;

foreach ($navigation as $entry) {
    if (($entry['id'] ?? null) === 'pages') {
        $pageNavigation = $entry;
        break;
    }
}

if (
    !is_array($pageNavigation)
    || ($pageNavigation['route'] ?? null) !== 'admin_pages'
    || ($pageNavigation['permission'] ?? null) !== 'pages.read'
) {
    fwrite(STDERR, "Pages не зарегистрирован в Admin Shell.\n");
    exit(1);
}

echo "Pages Admin organization owner smoke OK\n";
