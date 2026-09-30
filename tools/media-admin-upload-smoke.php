<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ThemeContext;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Media\MediaOrganizationAccessService;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use PDO;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationRepository::fromDatabase();
$tree = $organizations->tree();

$allowed = null;
$allowedChild = null;
$denied = null;

foreach ($tree as $unit) {
    $allowed ??= $unit->name === 'Доступная Media-организация'
        ? $unit
        : null;
    $allowedChild ??= $unit->name === 'Дочерняя Media-организация'
        ? $unit
        : null;
    $denied ??= $unit->name === 'Недоступная Media-организация'
        ? $unit
        : null;
}

if ($allowed === null || $allowedChild === null || $denied === null) {
    fwrite(STDERR, "Не найдены организации Media smoke.\n");
    exit(1);
}

$pdo = DatabaseManager::getInstance()->connection();

$pdo->prepare(
    'INSERT INTO admin_users (
        public_id, username, password_hash, display_name, email,
        status, created_at, updated_at
     ) VALUES (
        :public_id, :username, :password_hash, :display_name, :email,
        :status, :created_at, :updated_at
     )'
)->execute([
    'public_id' => '73000000-0000-4000-8000-000000000001',
    'username' => 'media-scope-smoke',
    'password_hash' => password_hash(
        'media-scope-smoke-password',
        PASSWORD_DEFAULT,
    ),
    'display_name' => 'Media scope smoke',
    'email' => 'media-scope@example.invalid',
    'status' => 'active',
    'created_at' => '2026-09-30 00:00:00',
    'updated_at' => '2026-09-30 00:00:00',
]);

$userStatement = $pdo->prepare(
    'SELECT id FROM admin_users WHERE username = :username LIMIT 1'
);
$userStatement->execute([
    'username' => 'media-scope-smoke',
]);
$userId = (int) $userStatement->fetchColumn();

$pdo->prepare(
    'INSERT INTO roles (role_key, name)
     VALUES (:role_key, :name)'
)->execute([
    'role_key' => 'media_scope_smoke',
    'name' => 'Media scope smoke',
]);

$roleStatement = $pdo->prepare(
    'SELECT id FROM roles WHERE role_key = :role_key LIMIT 1'
);
$roleStatement->execute([
    'role_key' => 'media_scope_smoke',
]);
$roleId = (int) $roleStatement->fetchColumn();

$permissionStatement = $pdo->prepare(
    'SELECT id FROM permissions WHERE permission_key = :permission LIMIT 1'
);
$permissionStatement->execute([
    'permission' => 'media.manage',
]);
$permissionId = (int) $permissionStatement->fetchColumn();

if ($userId <= 0 || $roleId <= 0 || $permissionId <= 0) {
    fwrite(STDERR, "Не удалось подготовить Media RBAC smoke.\n");
    exit(1);
}

$pdo->prepare(
    'INSERT INTO admin_user_roles (user_id, role_id)
     VALUES (:user_id, :role_id)'
)->execute([
    'user_id' => $userId,
    'role_id' => $roleId,
]);

$pdo->prepare(
    'INSERT INTO role_permissions (role_id, permission_id)
     VALUES (:role_id, :permission_id)'
)->execute([
    'role_id' => $roleId,
    'permission_id' => $permissionId,
]);

$pdo->prepare(
    'INSERT INTO admin_role_scopes (
        user_id, role_id, site_key, scope_type, scope_key
     ) VALUES (
        :user_id, :role_id, :site_key, :scope_type, :scope_key
     )'
)->execute([
    'user_id' => $userId,
    'role_id' => $roleId,
    'site_key' => 'default',
    'scope_type' => 'organization',
    'scope_key' => $allowed->publicId,
]);

$access = MediaOrganizationAccessService::fromDatabase();
$ownerIds = array_map(
    static fn($unit): string => $unit->publicId,
    $access->availableOwners($userId),
);

if (
    !in_array($allowed->publicId, $ownerIds, true)
    || !in_array($allowedChild->publicId, $ownerIds, true)
    || in_array($denied->publicId, $ownerIds, true)
    || $access->assignableOwner(
        $userId,
        $denied->publicId,
    ) !== null
) {
    fwrite(STDERR, "Media organization scope нарушен.\n");
    exit(1);
}

$media = MediaService::fromDatabase();
$deniedAsset = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'denied.png',
    mimeType: 'image/png',
    bytes: 1,
    sha256: str_repeat('d', 64),
    ownerOrganizationPublicId: $denied->publicId,
);

$visible = MediaRepository::fromDatabase()->adminList(
    $access->visibleOwnerPublicIds($userId),
);
$visibleIds = array_map(
    static fn($asset): string => $asset->publicId,
    $visible,
);

if (in_array($deniedAsset, $visibleIds, true)) {
    fwrite(STDERR, "Scoped Media list показал чужой asset.\n");
    exit(1);
}

$uploaded = array_values(array_filter(
    $visible,
    static fn($asset): bool =>
        $asset->mimeType === 'image/png'
        && $asset->originalName === 'dangerous.php',
));

if (
    count($uploaded) !== 1
    || $uploaded[0]->ownerOrganizationPublicId
        !== $allowed->publicId
) {
    fwrite(STDERR, "Multipart Media upload не найден в scoped списке.\n");
    exit(1);
}

$renderer = ThemeRenderer::fromConfig();
$theme = new ThemeContext(
    $renderer,
    'default',
);
$assets = $visible;
$organizationUnits = $access->availableOwners($userId);
$defaultOwnerPublicId = $access->defaultOwnerPublicId($userId);
$mediaStatus = null;

ob_start();
require dirname(__DIR__)
    . '/themes/default/templates/admin/media/index.php';
$html = (string) ob_get_clean();

foreach ([
    'enctype="multipart/form-data"',
    'name="media_file"',
    'name="owner_organization_public_id"',
    'dangerous.php',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(
            STDERR,
            "Media Admin template не содержит: {$expected}\n",
        );
        exit(1);
    }
}

if (str_contains($html, '/tmp/churchcms-media-admin')) {
    fwrite(STDERR, "Media Admin template раскрыл storage path.\n");
    exit(1);
}

echo "Media admin upload smoke OK\n";
