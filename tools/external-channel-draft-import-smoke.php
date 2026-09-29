<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationStatus;
use InvalidArgumentException;
use PDO;

require dirname(__DIR__) . '/core.php';

$pdo = DatabaseManager::getInstance()->connection();
$organizations = OrganizationService::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовый приход внешнего импорта',
    'parish',
);
$allowed = $organizations->create(
    name: 'Доступный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);
$allowedChild = $organizations->create(
    name: 'Дочернее направление',
    type: 'department',
    parentPublicId: $allowed,
);
$denied = $organizations->create(
    name: 'Недоступный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);

$pdo->prepare(
    'INSERT INTO admin_users (
        public_id, username, password_hash, display_name, email,
        status, created_at, updated_at
     ) VALUES (
        :public_id, :username, :password_hash, :display_name, :email,
        :status, :created_at, :updated_at
     )'
)->execute([
    'public_id' => '72000000-0000-4000-8000-000000000008',
    'username' => 'external-import-scope-smoke',
    'password_hash' => password_hash(
        'external-import-scope-smoke-password',
        PASSWORD_DEFAULT,
    ),
    'display_name' => 'Редактор внешнего импорта',
    'email' => 'external-import@example.invalid',
    'status' => 'active',
    'created_at' => '2026-09-29 00:00:00',
    'updated_at' => '2026-09-29 00:00:00',
]);

$userStatement = $pdo->prepare(
    'SELECT id FROM admin_users WHERE username = :username LIMIT 1'
);
$userStatement->execute([
    'username' => 'external-import-scope-smoke',
]);
$userId = (int) $userStatement->fetchColumn();

$pdo->prepare(
    'INSERT INTO roles (role_key, name)
     VALUES (:role_key, :name)'
)->execute([
    'role_key' => 'external_import_scope_smoke',
    'name' => 'Ограниченный импорт внешних материалов',
]);

$roleStatement = $pdo->prepare(
    'SELECT id FROM roles WHERE role_key = :role_key LIMIT 1'
);
$roleStatement->execute([
    'role_key' => 'external_import_scope_smoke',
]);
$roleId = (int) $roleStatement->fetchColumn();

if ($userId <= 0 || $roleId <= 0) {
    fwrite(STDERR, "Не удалось подготовить RBAC smoke.\n");
    exit(1);
}

$pdo->prepare(
    'INSERT INTO admin_user_roles (user_id, role_id)
     VALUES (:user_id, :role_id)'
)->execute([
    'user_id' => $userId,
    'role_id' => $roleId,
]);

$permissionRows = $pdo->query(
    "SELECT id, permission_key
     FROM permissions
     WHERE permission_key IN (
        'publications.read',
        'publications.create',
        'social.manage'
     )"
)->fetchAll(PDO::FETCH_ASSOC);

if (count($permissionRows) !== 3) {
    fwrite(STDERR, "Не найдены permissions внешнего импорта.\n");
    exit(1);
}

$grant = $pdo->prepare(
    'INSERT INTO role_permissions (role_id, permission_id)
     VALUES (:role_id, :permission_id)'
);
foreach ($permissionRows as $permissionRow) {
    $grant->execute([
        'role_id' => $roleId,
        'permission_id' => (int) $permissionRow['id'],
    ]);
}

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
    'scope_key' => $allowed,
]);

$capability = ModuleRuntimeLoader::capability(
    'publications',
    'publications.external-import',
);
if (
    $capability === null
    || !method_exists($capability, 'externalImportOwners')
    || !method_exists($capability, 'importExternalDraft')
    || !method_exists($capability, 'findLinkablePublication')
) {
    fwrite(STDERR, "Контракт внешнего импорта Publications не зарегистрирован.\n");
    exit(1);
}

$owners = $capability->externalImportOwners($userId);
$ownerIds = array_column($owners, 'public_id');

if (
    !in_array($allowed, $ownerIds, true)
    || !in_array($allowedChild, $ownerIds, true)
    || in_array($root->publicId, $ownerIds, true)
    || in_array($denied, $ownerIds, true)
) {
    fwrite(STDERR, "Список владельцев внешнего импорта нарушил organization scope.\n");
    exit(1);
}

try {
    $capability->importExternalDraft(
        userId: $userId,
        ownerPublicId: $denied,
        title: 'Запрещённый импорт',
        bodyText: 'Не должен сохраниться',
    );
    fwrite(STDERR, "Импорт разрешил недоступную организацию.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$draft = $capability->importExternalDraft(
    userId: $userId,
    ownerPublicId: $allowedChild,
    title: 'Внешний материал',
    bodyText: "<script>alert('x')</script>\n\n<b>обычный текст</b>",
    kind: 'article',
    canonicalUrl: 'https://example.org/source',
);

$publication = $capability->repository()->findByPublicId(
    (string) $draft['public_id'],
);
if (
    $publication === null
    || $publication->status !== PublicationStatus::Draft
    || $publication->ownerOrganizationPublicId !== $allowedChild
) {
    fwrite(STDERR, "Внешний материал создан не как scoped draft.\n");
    exit(1);
}

if (
    str_contains($publication->bodyHtml, '<script')
    || str_contains($publication->bodyHtml, '<b>')
    || !str_contains($publication->bodyHtml, '&lt;script&gt;')
    || !str_contains($publication->bodyHtml, '&lt;b&gt;')
) {
    fwrite(STDERR, "Внешний обычный текст повысился до HTML-разметки.\n");
    exit(1);
}

if (
    $capability->findLinkablePublication(
        $userId,
        $publication->publicId,
    ) === null
) {
    fwrite(STDERR, "Доступная публикация не разрешена для привязки.\n");
    exit(1);
}

$outside = $capability->service()->createDraft(
    title: 'Публикация соседней ветки',
    ownerOrganizationPublicId: $denied,
);
if (
    $capability->findLinkablePublication(
        $userId,
        $outside,
    ) !== null
) {
    fwrite(STDERR, "Привязка разрешила публикацию вне organization scope.\n");
    exit(1);
}

echo "External channel draft import smoke OK\n";
