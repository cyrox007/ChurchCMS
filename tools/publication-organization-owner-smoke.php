<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationApiResource;
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
