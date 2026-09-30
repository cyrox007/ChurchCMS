<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Documents\DocumentCatalogService;
use ChurchCMS\Modules\Documents\DocumentMediaService;
use ChurchCMS\Modules\Documents\DocumentOrganizationAccessService;
use ChurchCMS\Modules\Documents\DocumentRepository;
use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$pdo = DatabaseManager::getInstance()->connection();
$organizations = OrganizationService::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовый приход документов',
    'parish',
);
$allowedId = $organizations->create(
    name: 'Доступный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);
$allowedChildId = $organizations->create(
    name: 'Дочерний отдел',
    type: 'department',
    parentPublicId: $allowedId,
);
$hiddenId = $organizations->create(
    name: 'Скрытый отдел',
    type: 'department',
    parentPublicId: $root->publicId,
);

$now = '2026-09-30 00:00:00';

$pdo->prepare(
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
     )'
)->execute([
    'public_id' => '73000000-0000-4000-8000-000000000001',
    'username' => 'document-catalog-smoke',
    'password_hash' => password_hash(
        'document-catalog-smoke-password',
        PASSWORD_DEFAULT,
    ),
    'display_name' => 'Редактор документов',
    'email' => 'documents@example.invalid',
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);

$userId = (int) $pdo->query(
    "SELECT id
     FROM admin_users
     WHERE username = 'document-catalog-smoke'"
)->fetchColumn();

$pdo->prepare(
    'INSERT INTO roles (role_key, name)
     VALUES (:role_key, :name)'
)->execute([
    'role_key' => 'document_catalog_smoke',
    'name' => 'Редактор каталога документов',
]);

$roleId = (int) $pdo->query(
    "SELECT id
     FROM roles
     WHERE role_key = 'document_catalog_smoke'"
)->fetchColumn();

if ($userId <= 0 || $roleId <= 0) {
    fwrite(STDERR, "Не удалось подготовить RBAC каталога документов.\n");
    exit(1);
}

$permissionRows = $pdo->query(
    "SELECT id, permission_key
     FROM permissions
     WHERE permission_key IN (
        'documents.read',
        'documents.create',
        'documents.edit',
        'documents.publish'
     )"
)->fetchAll(PDO::FETCH_ASSOC);

if (count($permissionRows) !== 4) {
    fwrite(STDERR, "Permissions каталога документов не созданы.\n");
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
    'INSERT INTO admin_user_roles (user_id, role_id)
     VALUES (:user_id, :role_id)'
)->execute([
    'user_id' => $userId,
    'role_id' => $roleId,
]);

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
    'user_id' => $userId,
    'role_id' => $roleId,
    'site_key' => 'default',
    'scope_type' => 'organization',
    'scope_key' => $allowedId,
]);

$access = DocumentOrganizationAccessService::fromDatabase();
$visibleOwners = $access->visibleOwnerPublicIds(
    $userId,
    'documents.read',
);

if (
    !is_array($visibleOwners)
    || !in_array($allowedId, $visibleOwners, true)
    || !in_array($allowedChildId, $visibleOwners, true)
    || in_array($root->publicId, $visibleOwners, true)
    || in_array($hiddenId, $visibleOwners, true)
) {
    fwrite(STDERR, "Organization scope каталога документов нарушен.\n");
    exit(1);
}

$directory = sys_get_temp_dir()
    . '/churchcms-document-catalog-'
    . bin2hex(random_bytes(6));

if (
    !mkdir($directory, 0700, true)
    && !is_dir($directory)
) {
    fwrite(STDERR, "Не удалось создать временный каталог.\n");
    exit(1);
}

$allowedPdf = $directory . '/allowed.pdf';
$hiddenPdf = $directory . '/hidden.pdf';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

file_put_contents($allowedPdf, $pdf);
file_put_contents($hiddenPdf, $pdf . "% hidden\n");

try {
    $upload = MediaUploadService::fromConfig();

    $allowedMedia = $upload->importFile(
        sourcePath: $allowedPdf,
        originalName: 'allowed.pdf',
        ownerOrganizationPublicId: $allowedId,
    );
    $hiddenMedia = $upload->importFile(
        sourcePath: $hiddenPdf,
        originalName: 'hidden.pdf',
        ownerOrganizationPublicId: $hiddenId,
    );

    $media = MediaService::fromDatabase();
    $media->setVisibility(
        $allowedMedia,
        'public',
    );
    $media->setVisibility(
        $hiddenMedia,
        'public',
    );

    $documentService = DocumentService::fromDatabase();

    $publicDocument = $documentService->createDraft(
        title: 'Публичное распоряжение',
        ownerOrganizationPublicId: $allowedChildId,
        documentType: 'order',
        documentNumber: '12',
        issuedOn: '2026-09-30',
        summary: 'Краткое описание публичного документа.',
    );

    $hiddenDocument = $documentService->createDraft(
        title: 'Скрытый документ',
        ownerOrganizationPublicId: $hiddenId,
    );

    $files = DocumentMediaService::fromDatabase()
        ->availableFiles($visibleOwners);

    $fileIds = array_column($files, 'public_id');

    if (
        !in_array($allowedMedia, $fileIds, true)
        || in_array($hiddenMedia, $fileIds, true)
    ) {
        fwrite(STDERR, "Media-файлы каталога нарушили organization scope.\n");
        exit(1);
    }

    DocumentMediaService::fromDatabase()->attachFile(
        $publicDocument,
        $allowedMedia,
    );

    $documentService->publish($publicDocument);
    $documentService->setVisibility(
        $publicDocument,
        'public',
    );

    $catalog = DocumentCatalogService::fromDatabase();
    $items = $catalog->index();

    if (
        count($items) !== 1
        || ($items[0]['id'] ?? null) !== $publicDocument
        || ($items[0]['title'] ?? null)
            !== 'Публичное распоряжение'
        || ($items[0]['file']['public_id'] ?? null)
            !== $allowedMedia
        || !str_starts_with(
            (string) ($items[0]['file']['url'] ?? ''),
            '/media/' . rawurlencode($allowedMedia) . '/',
        )
    ) {
        fwrite(STDERR, "Public document catalog projection некорректна.\n");
        exit(1);
    }

    if ($catalog->detail($hiddenDocument) !== null) {
        fwrite(STDERR, "Draft/private документ попал в public detail.\n");
        exit(1);
    }

    $adminList = DocumentRepository::fromDatabase()
        ->adminList($visibleOwners);

    if (
        count($adminList) !== 1
        || $adminList[0]->publicId !== $publicDocument
    ) {
        fwrite(STDERR, "Admin list документов показал чужую ветку.\n");
        exit(1);
    }

    $router = Router::getInstance();

    $routes = [
        'admin_documents' => '/admin/documents',
        'admin_documents_create' => '/admin/documents',
        'admin_documents_update' =>
            '/admin/documents/' . rawurlencode($publicDocument),
        'admin_documents_publish' =>
            '/admin/documents/'
            . rawurlencode($publicDocument)
            . '/publish',
        'admin_documents_withdraw' =>
            '/admin/documents/'
            . rawurlencode($publicDocument)
            . '/withdraw',
        'admin_documents_archive' =>
            '/admin/documents/'
            . rawurlencode($publicDocument)
            . '/archive',
        'document_index' => '/documents',
        'document_show' =>
            '/documents/' . rawurlencode($publicDocument),
        'api_v1_documents' => '/api/v1/documents',
        'api_v1_document_show' =>
            '/api/v1/documents/'
            . rawurlencode($publicDocument),
    ];

    foreach ($routes as $name => $expected) {
        $params = str_contains($name, 'update')
            || str_contains($name, 'publish')
            || str_contains($name, 'withdraw')
            || str_contains($name, 'archive')
            || $name === 'document_show'
            || $name === 'api_v1_document_show'
            ? ['publicId' => $publicDocument]
            : [];

        if ($router->url($name, $params) !== $expected) {
            fwrite(
                STDERR,
                "Маршрут {$name} зарегистрирован неверно.\n",
            );
            exit(1);
        }
    }

    $renderer = ThemeRenderer::fromConfig();

    $listHtml = $renderer->render(
        'document.index',
        [
            'documents' => $items,
        ],
    );

    $showHtml = $renderer->render(
        'document.show',
        [
            'document' => $items[0],
        ],
    );

    foreach ([
        'Публичное распоряжение',
        'Официальные материалы',
    ] as $expected) {
        if (!str_contains($listHtml, $expected)) {
            fwrite(
                STDERR,
                "Public document index не содержит {$expected}.\n",
            );
            exit(1);
        }
    }

    foreach ([
        'Публичное распоряжение',
        'Открыть файл',
        '/media/',
    ] as $expected) {
        if (!str_contains($showHtml, $expected)) {
            fwrite(
                STDERR,
                "Public document detail не содержит {$expected}.\n",
            );
            exit(1);
        }
    }

    $adminTemplate = file_get_contents(
        dirname(__DIR__)
        . '/themes/default/templates/admin/documents.php'
    );

    if (
        !is_string($adminTemplate)
        || !str_contains(
            $adminTemplate,
            'name="csrf_token"',
        )
        || !str_contains(
            $adminTemplate,
            'Файл из медиатеки',
        )
        || !str_contains(
            $adminTemplate,
            'admin_documents_publish',
        )
    ) {
        fwrite(STDERR, "Admin template документов неполный.\n");
        exit(1);
    }

    echo "Document catalog smoke OK\n";
} finally {
    foreach ([$allowedPdf, $hiddenPdf] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($directory);
}
