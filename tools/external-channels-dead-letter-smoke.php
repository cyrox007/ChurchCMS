<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Social\SocialConnectionRepository;
use ChurchCMS\Modules\Social\SocialOutboundFailureService;
use ChurchCMS\Modules\Social\SocialPostRepository;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход dead-letter',
    'parish',
);

$connection = SocialConnectionRepository::fromDatabase()
    ->create(
        provider: 'dead-letter-smoke',
        name: 'Тестовый внешний канал',
        targetRef: 'dead-letter-target',
        tokenEncrypted: SecretVault::encrypt(
            'dead-letter-token',
        ),
        outboundEnabled: true,
        inboundEnabled: false,
    );

$channels = ModuleRuntimeLoader::capability(
    'social',
    'social.publication',
);
if (
    $channels === null
    || !method_exists(
        $channels,
        'savePublicationSelection',
    )
    || !method_exists(
        $channels,
        'queuePublication',
    )
) {
    fwrite(STDERR, "Capability внешних публикаций недоступен.\n");
    exit(1);
}

$createFailedPost = static function (
    string $title,
) use (
    $root,
    $connection,
    $channels,
): array {
    $publicId = PublicationService::fromDatabase()
        ->createDraft(
            title: $title,
            ownerOrganizationPublicId: $root->publicId,
        );
    $publication = PublicationRepository::fromDatabase()
        ->findByPublicId($publicId);

    if ($publication === null) {
        throw new RuntimeException(
            'Тестовая публикация не найдена.'
        );
    }

    $channels->savePublicationSelection(
        $publication->id,
        [$connection->publicId],
    );
    PublicationService::fromDatabase()->publish(
        $publication->publicId,
    );
    $channels->queuePublication($publication->id);

    $posts = SocialPostRepository::fromDatabase()
        ->forPublication($publication->id);
    if (count($posts) !== 1) {
        throw new RuntimeException(
            'Тестовая outbox-запись не создана.'
        );
    }

    $repository = SocialPostRepository::fromDatabase();
    $repository->markFailed(
        $posts[0]->id,
        '<b>внешняя ошибка</b>',
        2,
    );
    $repository->markFailed(
        $posts[0]->id,
        '<b>внешняя ошибка</b>',
        2,
    );

    $failed = $repository->findByPublicId(
        $posts[0]->publicId,
    );
    if (
        $failed === null
        || $failed->status !== 'failed'
        || $failed->attempts !== 2
    ) {
        throw new RuntimeException(
            'Тестовая запись не попала в dead-letter.'
        );
    }

    return [$publication, $failed];
};

[$publication, $failed] = $createFailedPost(
    'Публикация для успешного retry',
);

$service = SocialOutboundFailureService::fromDatabase();
$failures = $service->failures();

if (
    count($failures) !== 1
    || ($failures[0]['post_public_id'] ?? null)
        !== $failed->publicId
    || ($failures[0]['publication_title'] ?? null)
        !== $publication->title
) {
    fwrite(STDERR, "Dead-letter список сформирован некорректно.\n");
    exit(1);
}

$theme = ThemeRenderer::fromConfig();
$connections = [];
$adapters = [];
$inboxItems = [];
$importOwners = [];
$outboundFailures = $failures;
$canRetryOutbound = true;
$canLinkExternal = false;
$canImportExternal = false;
$channelStatus = null;

ob_start();
require dirname(__DIR__)
    . '/themes/default/templates/admin/external-channels.php';
$rendered = (string) ob_get_clean();

if (
    !str_contains($rendered, 'Ошибки отправки')
    || !str_contains($rendered, 'Повторить отправку')
    || !str_contains(
        $rendered,
        '&lt;b&gt;внешняя ошибка&lt;/b&gt;',
    )
    || str_contains($rendered, '<b>внешняя ошибка</b>')
) {
    fwrite(STDERR, "Dead-letter UI выводит ошибку небезопасно.\n");
    exit(1);
}

$retried = $service->retry($failed->publicId);
if (
    $retried->status !== 'pending'
    || $retried->attempts !== 0
    || $retried->lastError !== null
    || $retried->queuedAt === null
) {
    fwrite(STDERR, "Ручной retry не сбросил состояние записи.\n");
    exit(1);
}

if ($service->failures() !== []) {
    fwrite(STDERR, "Повторно поставленная запись осталась в dead-letter.\n");
    exit(1);
}

[$withdrawnPublication, $withdrawnFailed] =
    $createFailedPost(
        'Снятая публикация для запрещённого retry',
    );

PublicationService::fromDatabase()->withdraw(
    $withdrawnPublication->publicId,
);

try {
    $service->retry(
        $withdrawnFailed->publicId,
    );
    fwrite(STDERR, "Retry снятой публикации ошибочно разрешён.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$unchanged = SocialPostRepository::fromDatabase()
    ->findByPublicId(
        $withdrawnFailed->publicId,
    );
if (
    $unchanged === null
    || $unchanged->status !== 'failed'
    || $unchanged->attempts !== 2
) {
    fwrite(STDERR, "Запрещённый retry изменил dead-letter запись.\n");
    exit(1);
}

echo "External channels dead-letter UI smoke OK\n";
