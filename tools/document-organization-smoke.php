<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Documents\DocumentRepository;
use ChurchCMS\Modules\Documents\DocumentService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$documents = DocumentService::fromDatabase();
$repository = DocumentRepository::fromDatabase();

$siteKey = 'document-smoke';
$root = $organizations->ensureSiteRoot(
    'Тестовая епархия документов',
    'diocese',
    $siteKey,
);
$departmentId = $organizations->create(
    name: 'Канцелярия',
    type: 'department',
    parentPublicId: $root->publicId,
    siteKey: $siteKey,
);

$rootDocumentId = $documents->createDraft(
    title: 'Распоряжение по епархии',
    siteKey: $siteKey,
    documentType: 'decree',
    documentNumber: '12/2026',
    issuedOn: '2026-10-15',
    summary: 'Тестовый документ.',
);
$rootDocument = $repository->findByPublicId(
    $rootDocumentId,
    $siteKey,
);

if (
    $rootDocument === null
    || $rootDocument->ownerOrganizationPublicId !== $root->publicId
    || $rootDocument->documentType !== 'decree'
    || $rootDocument->documentNumber !== '12/2026'
    || $rootDocument->issuedOn !== '2026-10-15'
    || $rootDocument->status !== 'draft'
) {
    fwrite(
        STDERR,
        "Документ не получил корректную карточку/root-владельца.\n",
    );
    exit(1);
}

$departmentDocumentId = $documents->createDraft(
    title: 'Положение отдела',
    ownerOrganizationPublicId: $departmentId,
    siteKey: $siteKey,
    documentType: 'regulation',
);
$list = $repository->forOrganization(
    $departmentId,
    $siteKey,
);

if (
    count($list) !== 1
    || $list[0]->publicId !== $departmentDocumentId
) {
    fwrite(
        STDERR,
        "Репозиторий не вернул документы выбранной организации.\n",
    );
    exit(1);
}

$documents->assignOrganizationOwner(
    $rootDocumentId,
    $departmentId,
    $siteKey,
);
$moved = $repository->findByPublicId(
    $rootDocumentId,
    $siteKey,
);

if (
    $moved === null
    || $moved->ownerOrganizationPublicId !== $departmentId
) {
    fwrite(
        STDERR,
        "Смена владельца документа не сохранилась.\n",
    );
    exit(1);
}

$documents->archive(
    $departmentDocumentId,
    $siteKey,
);
$archived = $repository->findByPublicId(
    $departmentDocumentId,
    $siteKey,
);

if ($archived?->status !== 'archived') {
    fwrite(
        STDERR,
        "Архивирование документа не сохранилось.\n",
    );
    exit(1);
}

$foreignRoot = $organizations->ensureSiteRoot(
    'Чужая епархия документов',
    'diocese',
    'document-foreign',
);

try {
    $documents->createDraft(
        title: 'Недопустимый документ',
        ownerOrganizationPublicId: $foreignRoot->publicId,
        siteKey: $siteKey,
    );
    fwrite(
        STDERR,
        "Сервис разрешил владельца документа из другого site_key.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $documents->createDraft(
        title: 'Некорректная дата',
        siteKey: $siteKey,
        issuedOn: '2026-02-31',
    );
    fwrite(
        STDERR,
        "Сервис принял некорректную дату документа.\n",
    );
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE documents
     SET owner_organization_public_id = :organization_id
     WHERE public_id = :public_id
       AND site_key = :site_key'
);

try {
    $statement->execute([
        'organization_id' => $foreignRoot->publicId,
        'public_id' => $rootDocumentId,
        'site_key' => $siteKey,
    ]);
    fwrite(
        STDERR,
        "База разрешила владельца документа из другого site_key.\n",
    );
    exit(1);
} catch (PDOException) {
}

echo "Documents organization foundation smoke OK\n";
