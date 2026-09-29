<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationScheduleWorker;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Social\SocialConnectionRepository;
use ChurchCMS\Modules\Social\SocialPostRepository;
use DateTimeImmutable;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход selector',
    'parish',
);

$publicationPublicId = PublicationService::fromDatabase()
    ->createDraft(
        title: 'Материал с внешними каналами',
        ownerOrganizationPublicId: $root->publicId,
    );
$publication = PublicationRepository::fromDatabase()
    ->findByPublicId($publicationPublicId);

if ($publication === null) {
    fwrite(STDERR, "Публикация selector smoke не найдена.\n");
    exit(1);
}

$connections = SocialConnectionRepository::fromDatabase();
$token = SecretVault::encrypt('selector-smoke-token');

$first = $connections->create(
    provider: 'selector',
    name: 'Первый внешний канал',
    targetRef: 'first',
    tokenEncrypted: $token,
    outboundEnabled: true,
    inboundEnabled: false,
);
$second = $connections->create(
    provider: 'selector',
    name: 'Второй внешний канал',
    targetRef: 'second',
    tokenEncrypted: $token,
    outboundEnabled: true,
    inboundEnabled: false,
);
$inboundOnly = $connections->create(
    provider: 'selector',
    name: 'Только входящие',
    targetRef: 'inbound-only',
    tokenEncrypted: $token,
    outboundEnabled: false,
    inboundEnabled: true,
);

$capability = ModuleRuntimeLoader::capability(
    'social',
    'social.publication',
);
if (
    $capability === null
    || !method_exists(
        $capability,
        'publicationEditorConnections',
    )
    || !method_exists(
        $capability,
        'validatePublicationSelection',
    )
    || !method_exists(
        $capability,
        'savePublicationSelection',
    )
    || !method_exists(
        $capability,
        'queuePublication',
    )
) {
    fwrite(STDERR, "Capability selector внешних каналов недоступен.\n");
    exit(1);
}

$editorConnections = $capability->publicationEditorConnections(
    $publication->id,
);
$editorIds = array_column(
    $editorConnections,
    'public_id',
);

if (
    !in_array($first->publicId, $editorIds, true)
    || !in_array($second->publicId, $editorIds, true)
    || in_array($inboundOnly->publicId, $editorIds, true)
) {
    fwrite(STDERR, "Редактор показал некорректный набор outbound-каналов.\n");
    exit(1);
}

try {
    $capability->validatePublicationSelection([
        $first->publicId,
        'поддельный-id',
    ]);
    fwrite(STDERR, "Поддельный ID внешнего канала принят.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$capability->savePublicationSelection(
    $publication->id,
    [$first->publicId],
);

$posts = SocialPostRepository::fromDatabase()
    ->forPublication($publication->id);

if (
    count($posts) !== 1
    || !$posts[0]->enabled
    || $posts[0]->connectionId !== $first->id
    || $posts[0]->status !== 'idle'
) {
    fwrite(STDERR, "Выбор внешнего канала сохранён некорректно.\n");
    exit(1);
}

$rendered = ThemeRenderer::fromConfig()->capture(
    'admin.publications.editor',
    [
        'publication' => $publication,
        'form' => [
            'type' => $publication->type->value,
            'title' => $publication->title,
            'excerpt' => $publication->excerpt,
            'body' => 'Текст',
            'author_name' => '',
            'owner_organization_public_id' => $root->publicId,
            'slug' => $publication->slug,
            'comments_enabled' => false,
            'categories' => '',
            'tags' => '',
        ],
        'organizationUnits' => [$root],
        'error' => null,
        'success' => null,
        'canPublish' => true,
        'canSyndicate' => false,
        'canExternalPublish' => true,
        'externalChannels' =>
            $capability->publicationEditorConnections(
                $publication->id,
            ),
        'scheduleTimezone' => 'UTC',
        'scheduledAtLocal' => '',
    ],
);

foreach ([
    'Внешние каналы',
    'name="external_channel_ids[]"',
    $first->publicId,
    'Первый внешний канал',
] as $expected) {
    if (!str_contains($rendered, $expected)) {
        fwrite(
            STDERR,
            "Редактор не содержит selector: {$expected}\n",
        );
        exit(1);
    }
}

PublicationService::fromDatabase()->publish(
    $publication->publicId,
);
$queued = $capability->queuePublication(
    $publication->id,
);
$posts = SocialPostRepository::fromDatabase()
    ->forPublication($publication->id);

if (
    $queued !== 1
    || $posts[0]->status !== 'pending'
    || $posts[0]->queuedAt === null
) {
    fwrite(STDERR, "Ручная публикация не поставила канал в очередь.\n");
    exit(1);
}

$capability->savePublicationSelection(
    $publication->id,
    [],
);
$posts = SocialPostRepository::fromDatabase()
    ->forPublication($publication->id);
if ($posts[0]->enabled) {
    fwrite(STDERR, "Снятый selector не отключил outbox-запись.\n");
    exit(1);
}

$scheduledPublicId = PublicationService::fromDatabase()
    ->createDraft(
        title: 'Отложенный материал с внешним каналом',
        ownerOrganizationPublicId: $root->publicId,
    );
$scheduled = PublicationRepository::fromDatabase()
    ->findByPublicId($scheduledPublicId);
if ($scheduled === null) {
    exit(1);
}

$capability->savePublicationSelection(
    $scheduled->id,
    [$second->publicId],
);
PublicationService::fromDatabase()->schedule(
    $scheduled->publicId,
    new DateTimeImmutable('+10 minutes'),
);

$summary = PublicationScheduleWorker::fromDatabase()->run(
    now: new DateTimeImmutable('+20 minutes'),
);
if (
    $summary['published'] < 1
    || !in_array(
        $scheduled->publicId,
        $summary['public_ids'],
        true,
    )
) {
    fwrite(STDERR, "Schedule worker не опубликовал тестовый материал.\n");
    exit(1);
}

$scheduledPosts = SocialPostRepository::fromDatabase()
    ->forPublication($scheduled->id);
if (
    count($scheduledPosts) !== 1
    || $scheduledPosts[0]->status !== 'pending'
    || $scheduledPosts[0]->queuedAt === null
) {
    fwrite(STDERR, "Schedule worker не поставил внешний канал в очередь.\n");
    exit(1);
}

echo "Publication external channel selector smoke OK\n";
