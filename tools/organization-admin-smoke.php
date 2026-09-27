<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
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

echo "Organization admin domain smoke OK\n";
