<?php

declare(strict_types=1);

use ChurchCMS\App\Services\AuthorizationService;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$service = OrganizationService::fromDatabase();
$repository = OrganizationRepository::fromDatabase();

$root = $service->ensureSiteRoot(
    'Тестовая епархия',
    'diocese',
);

$deaneryId = $service->create(
    name: 'Северное благочиние',
    type: 'deanery',
    parentPublicId: $root->publicId,
    sortOrder: 10,
);
$deanery = $repository->findByPublicId($deaneryId);

$parishId = $service->create(
    name: 'Приход святителя Николая',
    type: 'parish',
    parentPublicId: $deaneryId,
);
$parish = $repository->findByPublicId($parishId);

$departmentId = $service->create(
    name: 'Молодёжный отдел',
    type: 'department',
);
$department = $repository->findByPublicId($departmentId);

if (
    $deanery === null
    || $parish === null
    || $department === null
    || $deanery->parentId !== $root->id
    || $parish->parentId !== $deanery->id
    || $department->parentId !== $root->id
) {
    fwrite(
        STDERR,
        "Единый root или parent links нарушены.\n",
    );
    exit(1);
}

$service->update(
    publicId: $deaneryId,
    name: 'Центральное благочиние',
    type: 'deanery',
    parentPublicId: $root->publicId,
    slug: 'centralnoe',
    descriptionInput: 'Обновлённое описание',
    sortOrder: 5,
);

$deanery = $repository->findByPublicId($deaneryId);
$parish = $repository->findByPublicId($parishId);

if (
    $deanery?->path !== $root->path . '/centralnoe'
    || $deanery->sortOrder !== 5
    || $parish?->path
        !== $root->path
            . '/centralnoe/'
            . $parish->slug
) {
    fwrite(
        STDERR,
        "Изменение узла не пересчитало поддерево.\n",
    );
    exit(1);
}

try {
    $service->update(
        publicId: $deaneryId,
        name: $deanery->name,
        type: $deanery->type,
        parentPublicId: $parishId,
        slug: $deanery->slug,
        sortOrder: $deanery->sortOrder,
    );

    fwrite(
        STDERR,
        "Циклическое перемещение было разрешено.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$service->archiveSubtree($deaneryId);
$deanery = $repository->findByPublicId($deaneryId);
$parish = $repository->findByPublicId($parishId);

if (
    $deanery?->status !== 'archived'
    || $parish?->status !== 'archived'
) {
    fwrite(
        STDERR,
        "Архивирование не охватило поддерево.\n",
    );
    exit(1);
}

try {
    $service->create(
        name: 'Нельзя сюда',
        type: 'parish',
        parentPublicId: $deaneryId,
    );

    fwrite(
        STDERR,
        "Создание внутри архива ошибочно разрешено.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$service->restoreSubtree($deaneryId);
$deanery = $repository->findByPublicId($deaneryId);
$parish = $repository->findByPublicId($parishId);

if (
    $deanery?->status !== 'active'
    || $parish?->status !== 'active'
) {
    fwrite(
        STDERR,
        "Восстановление не охватило поддерево.\n",
    );
    exit(1);
}

try {
    $service->archiveSubtree($root->publicId);

    fwrite(
        STDERR,
        "Корневая организация была архивирована.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$query = $pdo->prepare(
    'SELECT permission_key
     FROM permissions
     WHERE permission_key IN (:read_permission, :manage_permission)
     ORDER BY permission_key'
);
$query->execute([
    'read_permission' => 'organizations.read',
    'manage_permission' => 'organizations.manage',
]);
$permissions = $query->fetchAll(PDO::FETCH_COLUMN);

if (
    $permissions !== [
        'organizations.manage',
        'organizations.read',
    ]
) {
    fwrite(
        STDERR,
        "Organization permissions не созданы миграцией.\n",
    );
    exit(1);
}

$testUser = $pdo->prepare(
    'INSERT INTO admin_users (
        public_id,
        username,
        password_hash,
        display_name,
        email,
        status,
        created_at,
        updated_at
     ) VALUES (
        :public_id,
        :username,
        :password_hash,
        :display_name,
        :email,
        :status,
        :created_at,
        :updated_at
     )
     RETURNING id'
);
$testUser->execute([
    'public_id' => '70000000-0000-4000-8000-000000000007',
    'username' => 'organization-scope-smoke',
    'password_hash' => password_hash(
        'organization-scope-smoke-password',
        PASSWORD_DEFAULT,
    ),
    'display_name' => 'Проверка ограниченного редактора',
    'email' => 'organization-scope-smoke@example.invalid',
    'status' => 'active',
    'created_at' => '2026-09-27 00:00:00',
    'updated_at' => '2026-09-27 00:00:00',
]);
$testUserId = (int) $testUser->fetchColumn();

$testRole = $pdo->prepare(
    'INSERT INTO roles (role_key, name)
     VALUES (:role_key, :name)
     RETURNING id'
);
$testRole->execute([
    'role_key' => 'organization_scope_smoke',
    'name' => 'Проверка области организации',
]);
$testRoleId = (int) $testRole->fetchColumn();

$permissionId = $pdo->query(
    "SELECT id
     FROM permissions
     WHERE permission_key = 'organizations.manage'
     LIMIT 1"
)->fetchColumn();

if ($testUserId <= 0 || $testRoleId <= 0 || $permissionId === false) {
    fwrite(
        STDERR,
        "Не удалось подготовить RBAC fixture.\n",
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
$pdo->prepare(
    'INSERT INTO role_permissions (role_id, permission_id)
     VALUES (:role_id, :permission_id)'
)->execute([
    'role_id' => $testRoleId,
    'permission_id' => (int) $permissionId,
]);

$authorization = new AuthorizationService($pdo);

if (
    !$authorization->hasPermission(
        $testUserId,
        'organizations.manage',
    )
    || $authorization->permissionScopeKeys(
        $testUserId,
        'organizations.manage',
        'default',
        'organization',
    ) !== null
) {
    fwrite(
        STDERR,
        "Роль без scope должна давать глобальное право.\n",
    );
    exit(1);
}

$pdo->prepare(
    'INSERT INTO admin_role_scopes (
        user_id,
        role_id,
        site_key,
        scope_type,
        scope_key
     ) VALUES (
        :user_id,
        :role_id,
        :site_key,
        :scope_type,
        :scope_key
     )'
)->execute([
    'user_id' => $testUserId,
    'role_id' => $testRoleId,
    'site_key' => 'default',
    'scope_type' => 'organization',
    'scope_key' => $deaneryId,
]);

$scopeKeys = $authorization->permissionScopeKeys(
    $testUserId,
    'organizations.manage',
    'default',
    'organization',
);

if ($scopeKeys !== [$deaneryId]) {
    fwrite(
        STDERR,
        "Organization scope не найден для назначения роли.\n",
    );
    exit(1);
}

$access = new OrganizationAccessService(
    $authorization,
    $repository,
);
$visibleIds = array_map(
    static fn($unit): string => $unit->publicId,
    $access->visibleTree(
        $testUserId,
        'organizations.manage',
    ),
);

if (
    !$access->can(
        $testUserId,
        'organizations.manage',
        $deanery,
    )
    || !$access->can(
        $testUserId,
        'organizations.manage',
        $parish,
    )
    || $access->can(
        $testUserId,
        'organizations.manage',
        $root,
    )
    || $access->can(
        $testUserId,
        'organizations.manage',
        $department,
    )
    || !in_array($deaneryId, $visibleIds, true)
    || !in_array($parishId, $visibleIds, true)
    || in_array($root->publicId, $visibleIds, true)
    || in_array($departmentId, $visibleIds, true)
) {
    fwrite(
        STDERR,
        "Organization scope не ограничивает дерево или не наследуется вниз.\n",
    );
    exit(1);
}

if (
    $access->defaultCreateParent(
        $testUserId,
        'organizations.manage',
    )?->publicId !== $deaneryId
) {
    fwrite(
        STDERR,
        "Единственная граница доступа не выбрана как родитель по умолчанию.\n",
    );
    exit(1);
}

echo "Organization admin domain + scoped RBAC smoke OK\n";
