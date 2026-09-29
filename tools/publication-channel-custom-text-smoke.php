<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Social\ChannelAdapter;
use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelOutboundDispatcher;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\ChannelPublishResult;
use ChurchCMS\Modules\Social\ChannelPullBatch;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionRepository;
use ChurchCMS\Modules\Social\SocialPostRepository;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход custom text',
    'parish',
);

$publicationPublicId = PublicationService::fromDatabase()
    ->createDraft(
        title: 'Материал с индивидуальным текстом',
        excerpt: 'Общее описание публикации',
        ownerOrganizationPublicId: $root->publicId,
    );
$publication = PublicationRepository::fromDatabase()
    ->findByPublicId($publicationPublicId);

if ($publication === null) {
    fwrite(STDERR, "Публикация custom text smoke не найдена.\n");
    exit(1);
}

$connections = SocialConnectionRepository::fromDatabase();
$token = SecretVault::encrypt('custom-text-token');

$first = $connections->create(
    provider: 'custom-text-smoke',
    name: 'Канал со своим текстом',
    targetRef: 'first',
    tokenEncrypted: $token,
    outboundEnabled: true,
    inboundEnabled: false,
);
$second = $connections->create(
    provider: 'custom-text-smoke',
    name: 'Невыбранный канал',
    targetRef: 'second',
    tokenEncrypted: $token,
    outboundEnabled: true,
    inboundEnabled: false,
);

$capability = ModuleRuntimeLoader::capability(
    'social',
    'social.publication',
);
if (
    $capability === null
    || !method_exists(
        $capability,
        'validatePublicationCustomTexts',
    )
    || !method_exists(
        $capability,
        'savePublicationSelection',
    )
) {
    fwrite(STDERR, "Capability custom text недоступен.\n");
    exit(1);
}

$customText = "  Отдельный текст для первого канала.\nВторая строка.  ";
$validated = $capability->validatePublicationCustomTexts(
    [$first->publicId],
    [
        $first->publicId => $customText,
        $second->publicId => 'Этот текст не должен сохраниться',
    ],
);

if (
    ($validated[$first->publicId] ?? null)
        !== trim($customText)
    || array_key_exists($second->publicId, $validated)
) {
    fwrite(STDERR, "Валидация custom text нарушила выбранные каналы.\n");
    exit(1);
}

try {
    $capability->validatePublicationCustomTexts(
        [$first->publicId],
        [
            $first->publicId => str_repeat('я', 5001),
        ],
    );
    fwrite(STDERR, "Текст длиннее 5000 символов ошибочно принят.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$capability->savePublicationSelection(
    $publication->id,
    [$first->publicId],
    [
        $first->publicId => $customText,
        $second->publicId => 'Невыбранный текст',
    ],
);

$posts = SocialPostRepository::fromDatabase()
    ->forPublication($publication->id);

if (
    count($posts) !== 1
    || $posts[0]->connectionId !== $first->id
    || $posts[0]->customText !== trim($customText)
) {
    fwrite(STDERR, "Индивидуальный текст сохранён некорректно.\n");
    exit(1);
}

$editorConnections = $capability->publicationEditorConnections(
    $publication->id,
);
$firstEditor = null;
foreach ($editorConnections as $connection) {
    if (($connection['public_id'] ?? null) === $first->publicId) {
        $firstEditor = $connection;
        break;
    }
}

if (
    !is_array($firstEditor)
    || ($firstEditor['custom_text'] ?? null)
        !== trim($customText)
) {
    fwrite(STDERR, "Редактор не получил сохранённый custom text.\n");
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
            'body' => 'Текст публикации',
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
        'externalChannels' => $editorConnections,
        'scheduleTimezone' => 'UTC',
        'scheduledAtLocal' => '',
    ],
);

foreach ([
    'Текст для этого канала',
    'external_channel_text[' . $first->publicId . ']',
    'Отдельный текст для первого канала.',
] as $expected) {
    if (!str_contains($rendered, $expected)) {
        fwrite(
            STDERR,
            "Редактор не содержит custom text: {$expected}\n",
        );
        exit(1);
    }
}

$adapter = new class implements ChannelAdapter {
    /** @var list<string> */
    public array $texts = [];

    public function providerId(): string
    {
        return 'custom-text-smoke';
    }

    public function label(): string
    {
        return 'Custom text smoke';
    }

    public function capabilities(): array
    {
        return ['publish.text'];
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        if ($credentials !== 'custom-text-token') {
            return new ChannelPublishResult(
                false,
                error: 'Неверный тестовый секрет.',
            );
        }

        $this->texts[] = $item->text;

        return new ChannelPublishResult(
            true,
            remoteId: 'custom-text-remote',
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        return new ChannelPullBatch([]);
    }
};

ChannelAdapterRegistry::register($adapter);

PublicationService::fromDatabase()->publish(
    $publication->publicId,
);
$capability->queuePublication($publication->id);

$summary = ChannelOutboundDispatcher::fromDatabase()->dispatch(
    limit: 10,
    maxAttempts: 2,
);

if (
    $summary['sent'] !== 1
    || $adapter->texts !== [trim($customText)]
) {
    fwrite(
        STDERR,
        "Dispatcher не использовал индивидуальный текст: "
        . json_encode(
            $adapter->texts,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        )
        . "\n",
    );
    exit(1);
}

$capability->savePublicationSelection(
    $publication->id,
    [],
    [],
);
$posts = SocialPostRepository::fromDatabase()
    ->forPublication($publication->id);

if (
    count($posts) !== 1
    || $posts[0]->enabled
    || $posts[0]->customText !== trim($customText)
) {
    fwrite(STDERR, "Снятие канала ошибочно уничтожило custom text.\n");
    exit(1);
}

echo "Publication channel custom text smoke OK\n";
