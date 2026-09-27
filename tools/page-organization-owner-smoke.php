<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Pages\PageRepository;
use ChurchCMS\Modules\Pages\PageService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$pages = PageService::fromDatabase();
$repository = PageRepository::fromDatabase();

$root = $organizations->ensureSiteRoot(
    'Тестовый приход страниц',
    'parish',
    'pages-owner',
);
$departmentId = $organizations->create(
    name: 'Информационный отдел',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: 'pages-owner',
);

$pageId = $pages->createDraft(
    title: 'Страница с каноническим владельцем',
    siteKey: 'pages-owner',
);
$page = $repository->findByPublicId($pageId, 'pages-owner');

if (
    $page === null
    || $page->ownerOrganizationPublicId !== $root->publicId
) {
    fwrite(
        STDERR,
        "Новая страница не получила site root владельцем.\n",
    );
    exit(1);
}

$pages->assignOrganizationOwner(
    $pageId,
    $departmentId,
    'pages-owner',
);
$reassigned = $repository->findByPublicId(
    $pageId,
    'pages-owner',
);

if (
    $reassigned === null
    || $reassigned->ownerOrganizationPublicId !== $departmentId
) {
    fwrite(STDERR, "Владелец страницы не изменился.\n");
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужой приход страниц',
    'parish',
    'pages-foreign',
);

try {
    $pages->assignOrganizationOwner(
        $pageId,
        $foreignRoot->publicId,
        'pages-owner',
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца страницы из другого site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE pages
     SET owner_organization_public_id = :owner
     WHERE public_id = :public_id'
);

try {
    $statement->execute([
        'owner' => $foreignRoot->publicId,
        'public_id' => $pageId,
    ]);
    fwrite(
        STDERR,
        "База данных разрешила владельца страницы из другого site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

$legacyId = $pages->createDraft(
    title: 'Legacy page без корневой организации',
    siteKey: 'pages-legacy',
);
$legacy = $repository->findByPublicId(
    $legacyId,
    'pages-legacy',
);

if (
    $legacy === null
    || $legacy->ownerOrganizationPublicId !== null
) {
    fwrite(
        STDERR,
        "Legacy-совместимость nullable owner страницы нарушена.\n",
    );
    exit(1);
}

echo "Page organization owner smoke OK\n";
