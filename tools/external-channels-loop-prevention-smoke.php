<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Social\ChannelInboundItem;
use ChurchCMS\Modules\Social\ExternalChannelItemRepository;
use ChurchCMS\Modules\Social\SocialConnectionRepository;
use DateTimeImmutable;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход loop prevention',
    'parish',
);

$connection = SocialConnectionRepository::fromDatabase()
    ->create(
        provider: 'loop-smoke',
        name: 'Источник входящих материалов',
        targetRef: 'remote-source',
        tokenEncrypted: SecretVault::encrypt(
            'loop-smoke-token',
        ),
        outboundEnabled: true,
        inboundEnabled: true,
    );

$inbox = ExternalChannelItemRepository::fromDatabase();
$original = new ChannelInboundItem(
    remoteId: 'remote-object-1',
    kind: 'post',
    title: 'Исходный внешний материал',
    text: 'Первая версия текста',
    canonicalUrl: 'https://remote.example/1',
    publishedAt: new DateTimeImmutable(
        '2026-09-29 10:00:00 UTC',
    ),
    payload: [
        'source' => 'loop-smoke',
        'revision' => 1,
    ],
);

$inbox->store($connection->id, $original);
$inbox->store($connection->id, $original);

$pdo = DatabaseManager::getInstance()->connection();
$countStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM external_channel_items
     WHERE connection_id = :connection_id
       AND remote_id = :remote_id'
);
$countStatement->execute([
    'connection_id' => $connection->id,
    'remote_id' => 'remote-object-1',
]);

if ((int) $countStatement->fetchColumn() !== 1) {
    fwrite(
        STDERR,
        "Повторный inbound создал дубликат remote object.\n",
    );
    exit(1);
}

$pending = $inbox->pending();
if (count($pending) !== 1) {
    fwrite(STDERR, "Inbound объект не найден в review queue.\n");
    exit(1);
}

$stored = $pending[0];
$publicationPublicId = PublicationService::fromDatabase()
    ->createDraft(
        title: 'Связанная публикация',
        ownerOrganizationPublicId: $root->publicId,
    );
$publication = PublicationRepository::fromDatabase()
    ->findByPublicId($publicationPublicId);

if ($publication === null) {
    fwrite(STDERR, "Тестовая публикация не найдена.\n");
    exit(1);
}

$inbox->linkToPublication(
    $stored->publicId,
    $publication->id,
);

$linked = $inbox->findByPublicId($stored->publicId);
if (
    $linked === null
    || $linked->status !== 'imported'
    || $linked->linkedPublicationId !== $publication->id
) {
    fwrite(
        STDERR,
        "Связь inbound объекта с публикацией не сохранена.\n",
    );
    exit(1);
}

$updated = new ChannelInboundItem(
    remoteId: 'remote-object-1',
    kind: 'post',
    title: 'Обновлённый внешний материал',
    text: 'Вторая версия текста',
    canonicalUrl: 'https://remote.example/1',
    publishedAt: new DateTimeImmutable(
        '2026-09-29 10:00:00 UTC',
    ),
    updatedAt: new DateTimeImmutable(
        '2026-09-29 11:00:00 UTC',
    ),
    payload: [
        'source' => 'loop-smoke',
        'revision' => 2,
    ],
);

$inbox->store($connection->id, $updated);

$afterUpdate = $inbox->findByPublicId(
    $stored->publicId,
);
if (
    $afterUpdate === null
    || $afterUpdate->status !== 'imported'
    || $afterUpdate->linkedPublicationId !== $publication->id
    || $afterUpdate->title !== 'Обновлённый внешний материал'
    || $afterUpdate->bodyText !== 'Вторая версия текста'
) {
    fwrite(
        STDERR,
        "Remote update потерял review/link состояние.\n",
    );
    exit(1);
}

$countStatement->execute([
    'connection_id' => $connection->id,
    'remote_id' => 'remote-object-1',
]);
if ((int) $countStatement->fetchColumn() !== 1) {
    fwrite(
        STDERR,
        "Remote update создал второй inbound объект.\n",
    );
    exit(1);
}

$outboxCount = $pdo->prepare(
    'SELECT COUNT(*)
     FROM publication_social_posts
     WHERE publication_id = :publication_id'
);
$outboxCount->execute([
    'publication_id' => $publication->id,
]);

if ((int) $outboxCount->fetchColumn() !== 0) {
    fwrite(
        STDERR,
        "Inbound link автоматически создал обратную outbound отправку.\n",
    );
    exit(1);
}

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
) {
    fwrite(
        STDERR,
        "Capability внешних публикаций недоступен.\n",
    );
    exit(1);
}

$channels->savePublicationSelection(
    $publication->id,
    [
        $connection->publicId,
        $connection->publicId,
    ],
);

$outboxCount->execute([
    'publication_id' => $publication->id,
]);
if ((int) $outboxCount->fetchColumn() !== 1) {
    fwrite(
        STDERR,
        "Повторный явный выбор канала создал дубликат outbox.\n",
    );
    exit(1);
}

echo "External channels loop prevention smoke OK\n";
