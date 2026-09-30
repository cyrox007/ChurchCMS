<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Modules\Media\MediaRepository;
use ChurchCMS\Modules\Media\MediaService;
use ChurchCMS\Modules\Media\MediaUsageService;
use ChurchCMS\Modules\Organizations\OrganizationService;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Media usage smoke',
    'parish',
);

$media = MediaService::fromDatabase();

$firstId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'first.png',
    mimeType: 'image/png',
    bytes: 10,
    sha256: str_repeat('a', 64),
    ownerOrganizationPublicId: $root->publicId,
);

$secondId = $media->registerMetadata(
    mediaType: 'image',
    originalName: 'second.png',
    mimeType: 'image/png',
    bytes: 20,
    sha256: str_repeat('b', 64),
    ownerOrganizationPublicId: $root->publicId,
);

$capability = ModuleRuntimeLoader::capability(
    'media',
    'media.usage-references',
);

if (
    $capability === null
    || !method_exists(
        $capability,
        'replaceConsumerReferences',
    )
    || !method_exists(
        $capability,
        'referencesForAsset',
    )
) {
    fwrite(
        STDERR,
        "Media usage capability не зарегистрирован.\n",
    );
    exit(1);
}

$consumerId = 'publication-smoke-1';

$capability->replaceConsumerReferences(
    'publication',
    $consumerId,
    [
        'hero' => $firstId,
        'body' => $secondId,
    ],
);

$firstRefs = $capability->referencesForAsset(
    $firstId,
);
$secondRefs = $capability->referencesForAsset(
    $secondId,
);

if (
    count($firstRefs) !== 1
    || count($secondRefs) !== 1
    || $firstRefs[0]->usageKey !== 'hero'
    || $secondRefs[0]->usageKey !== 'body'
) {
    fwrite(
        STDERR,
        "Media usage references сохранены некорректно.\n",
    );
    exit(1);
}

try {
    $media->archive($firstId);
    fwrite(
        STDERR,
        "Используемый Media asset ошибочно архивирован.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$capability->replaceConsumerReferences(
    'publication',
    $consumerId,
    [
        'hero' => $secondId,
    ],
);

if (
    $capability->referencesForAsset($firstId) !== []
    || count(
        $capability->referencesForAsset($secondId)
    ) !== 1
    || $capability
        ->referencesForAsset($secondId)[0]
        ->usageKey !== 'hero'
) {
    fwrite(
        STDERR,
        "Атомарная замена Media references не удалила старые slots.\n",
    );
    exit(1);
}

$media->archive($firstId);

$first = MediaRepository::fromDatabase()->findByPublicId(
    $firstId,
);
if ($first === null || $first->status !== 'archived') {
    fwrite(
        STDERR,
        "Освобождённый Media asset не архивирован.\n",
    );
    exit(1);
}

try {
    $capability->replaceConsumerReferences(
        'publication',
        $consumerId,
        [
            'hero' => $firstId,
        ],
    );
    fwrite(
        STDERR,
        "Архивный Media asset ошибочно разрешён для новой ссылки.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$usage = MediaUsageService::fromDatabase();

try {
    $usage->replaceConsumerReferences(
        'publication',
        'publication-smoke-2',
        [
            'hero' =>
                'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        ],
    );
    fwrite(
        STDERR,
        "Несуществующий Media asset ошибочно разрешён.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$capability->replaceConsumerReferences(
    'publication',
    $consumerId,
    [],
);

if (
    $capability->referencesForAsset($secondId) !== []
) {
    fwrite(
        STDERR,
        "Очистка Media references не сработала.\n",
    );
    exit(1);
}

$media->archive($secondId);

$second = MediaRepository::fromDatabase()->findByPublicId(
    $secondId,
);
if ($second === null || $second->status !== 'archived') {
    fwrite(
        STDERR,
        "Media asset после снятия ссылок не архивирован.\n",
    );
    exit(1);
}

echo "Media usage references smoke OK\n";
