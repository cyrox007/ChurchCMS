<?php

declare(strict_types=1);

use ChurchCMS\Core\SecretVault;
use ChurchCMS\Modules\Social\ChannelAdapter;
use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelConnectionActivator;
use ChurchCMS\Modules\Social\ChannelConnectionTestResult;
use ChurchCMS\Modules\Social\ChannelConnectionTester;
use ChurchCMS\Modules\Social\ChannelHttpClient;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\ChannelPublishResult;
use ChurchCMS\Modules\Social\ChannelPullBatch;
use ChurchCMS\Modules\Social\ChannelWebhookException;
use ChurchCMS\Modules\Social\ChannelWebhookRequest;
use ChurchCMS\Modules\Social\ChannelWebhookService;
use ChurchCMS\Modules\Social\ExternalChannelItemRepository;
use ChurchCMS\Modules\Social\MaxChannelAdapter;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionRepository;
use ChurchCMS\Modules\Social\SocialConnectionService;

require dirname(__DIR__) . '/core.php';

$successAdapter = new class implements
    ChannelAdapter,
    ChannelConnectionTester,
    ChannelConnectionActivator
{
    public ?string $publicId = null;
    public ?string $webhookUrl = null;
    public bool $sawDisabled = false;

    public function providerId(): string
    {
        return 'activation-smoke-success';
    }

    public function label(): string
    {
        return 'Activation smoke success';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::IMPORT_POSTS,
            ChannelCapability::WEBHOOK,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        return new ChannelConnectionTestResult(true);
    }

    public function activateConnection(
        SocialConnection $connection,
        string $credentials,
        string $webhookUrl,
    ): void {
        $this->publicId = $connection->publicId;
        $this->webhookUrl = $webhookUrl;
        $this->sawDisabled = !$connection->enabled;

        if ($credentials !== 'activation-secret') {
            throw new \RuntimeException(
                'Lifecycle получил неверный credentials.'
            );
        }
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(false);
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

$failedAdapter = new class implements
    ChannelAdapter,
    ChannelConnectionTester,
    ChannelConnectionActivator
{
    public ?string $publicId = null;

    public function providerId(): string
    {
        return 'activation-smoke-fail';
    }

    public function label(): string
    {
        return 'Activation smoke fail';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::IMPORT_POSTS,
            ChannelCapability::WEBHOOK,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        return new ChannelConnectionTestResult(true);
    }

    public function activateConnection(
        SocialConnection $connection,
        string $credentials,
        string $webhookUrl,
    ): void {
        $this->publicId = $connection->publicId;

        throw new \RuntimeException(
            'Искусственная ошибка удалённой активации.'
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(false);
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

ChannelAdapterRegistry::register($successAdapter);
ChannelAdapterRegistry::register($failedAdapter);

$service = SocialConnectionService::fromDatabase();
$repository = SocialConnectionRepository::fromDatabase();

$activated = $service->create(
    provider: 'activation-smoke-success',
    name: 'Lifecycle success',
    targetRef: 'target',
    credentials: 'activation-secret',
    outboundEnabled: false,
    inboundEnabled: true,
);

if (
    !$successAdapter->sawDisabled
    || !$activated->enabled
    || $successAdapter->publicId !== $activated->publicId
    || $successAdapter->webhookUrl
        !== 'https://church.example/api/v1/external-channels/'
            . rawurlencode($activated->publicId)
            . '/webhook'
) {
    fwrite(
        STDERR,
        "Lifecycle webhook активирован в неверном порядке или с неверным URL.\n",
    );
    exit(1);
}

try {
    $service->create(
        provider: 'activation-smoke-fail',
        name: 'Lifecycle fail',
        targetRef: 'target',
        credentials: 'activation-secret',
        outboundEnabled: false,
        inboundEnabled: true,
    );

    fwrite(
        STDERR,
        "Ошибка удалённой активации не остановила создание подключения.\n",
    );
    exit(1);
} catch (\RuntimeException) {
}

if (
    $failedAdapter->publicId === null
    || $repository->findByPublicId(
        $failedAdapter->publicId,
    ) !== null
) {
    fwrite(
        STDERR,
        "Неуспешная удалённая активация оставила локальное подключение.\n",
    );
    exit(1);
}

final class MaxWebhookSmokeHttpClient implements ChannelHttpClient
{
    /** @var array<string,mixed>|null */
    public ?array $subscription = null;

    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'MAX webhook smoke не должен использовать GET.'
        );
    }

    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        if (
            $url !== 'https://platform-api2.max.ru/subscriptions'
            || ($headers['Authorization'] ?? null)
                !== 'max-production-token'
        ) {
            throw new \RuntimeException(
                'MAX webhook subscription использует неверный endpoint или auth.'
            );
        }

        if (
            ($payload['update_types'] ?? null)
                !== ['message_created', 'message_edited']
            || !is_string($payload['url'] ?? null)
            || !is_string($payload['secret'] ?? null)
        ) {
            throw new \RuntimeException(
                'MAX webhook subscription сформирована некорректно.'
            );
        }

        if (
            preg_match(
                '/^[A-Za-z0-9_-]{5,256}$/D',
                (string) $payload['secret'],
            ) !== 1
        ) {
            throw new \RuntimeException(
                'MAX webhook secret не соответствует требованиям API.'
            );
        }

        $this->subscription = $payload;

        return self::response([
            'success' => true,
        ]);
    }

    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array {
        throw new \RuntimeException(
            'MAX webhook smoke не должен использовать form POST.'
        );
    }

    /**
     * @param array<string,mixed> $json
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    private static function response(array $json): array
    {
        return [
            'status' => 200,
            'body' => json_encode(
                $json,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
            'json' => $json,
        ];
    }
}

$registeredMax = ChannelAdapterRegistry::get('max');
if (!$registeredMax instanceof MaxChannelAdapter) {
    fwrite(STDERR, "Production MAX adapter не зарегистрирован.\n");
    exit(1);
}

if (
    !in_array(
        ChannelCapability::WEBHOOK,
        $registeredMax->capabilities(),
        true,
    )
    || in_array(
        ChannelCapability::POLLING,
        $registeredMax->capabilities(),
        true,
    )
) {
    fwrite(
        STDERR,
        "Production MAX capabilities не переключены на webhook.\n",
    );
    exit(1);
}

$maxConnection = $repository->create(
    provider: 'max',
    name: 'MAX production webhook',
    targetRef: '777',
    tokenEncrypted: SecretVault::encrypt(
        'max-production-token',
    ),
    enabled: true,
    outboundEnabled: true,
    inboundEnabled: true,
);

$http = new MaxWebhookSmokeHttpClient();
$max = new MaxChannelAdapter($http);
$webhookUrl = 'https://church.example/api/v1/external-channels/'
    . rawurlencode($maxConnection->publicId)
    . '/webhook';

$max->activateConnection(
    $maxConnection,
    'max-production-token',
    $webhookUrl,
);

$subscription = $http->subscription;
if (
    !is_array($subscription)
    || ($subscription['url'] ?? null) !== $webhookUrl
) {
    fwrite(
        STDERR,
        "MAX webhook URL не зарегистрирован.\n",
    );
    exit(1);
}

try {
    $max->activateConnection(
        $maxConnection,
        'max-production-token',
        'https://church.example:8443/webhook',
    );

    fwrite(
        STDERR,
        "MAX принял webhook на нестандартном порту.\n",
    );
    exit(1);
} catch (\RuntimeException) {
}

$raw = json_encode(
    [
        'update_type' => 'message_created',
        'timestamp' => 1790761000000,
        'message' => [
            'recipient' => [
                'chat_id' => 777,
                'chat_type' => 'channel',
                'user_id' => null,
                'post_id' => null,
            ],
            'timestamp' => 1790760999000,
            'url' => 'https://max.ru/channel/webhook-post',
            'body' => [
                'mid' => 'webhook-mid',
                'seq' => 20,
                'text' => 'MAX webhook post',
                'attachments' => [],
                'link' => null,
            ],
        ],
    ],
    JSON_THROW_ON_ERROR
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES,
);

$webhookService = ChannelWebhookService::fromDatabase();

try {
    $webhookService->receive(
        $maxConnection->publicId,
        new ChannelWebhookRequest(
            rawBody: $raw,
            headers: [
                'x-max-bot-api-secret' => 'wrong-secret',
            ],
        ),
    );

    fwrite(
        STDERR,
        "MAX webhook с неверным secret ошибочно принят.\n",
    );
    exit(1);
} catch (ChannelWebhookException $error) {
    if ($error->httpStatus !== 401) {
        throw $error;
    }
}

$result = $webhookService->receive(
    $maxConnection->publicId,
    new ChannelWebhookRequest(
        rawBody: $raw,
        headers: [
            'x-max-bot-api-secret' =>
                (string) $subscription['secret'],
        ],
    ),
);

if (
    $result->status !== 200
    || $result->body !== 'OK'
) {
    fwrite(
        STDERR,
        "MAX webhook acknowledgement сформирован неверно.\n",
    );
    exit(1);
}

$pending = ExternalChannelItemRepository::fromDatabase()
    ->pending();

$matched = array_values(array_filter(
    $pending,
    static fn($item): bool =>
        $item->connectionId === $maxConnection->id,
));

if (
    count($matched) !== 1
    || $matched[0]->remoteId !== '777:webhook-mid'
    || $matched[0]->bodyText !== 'MAX webhook post'
) {
    fwrite(
        STDERR,
        "MAX webhook не попал в review-очередь.\n",
    );
    exit(1);
}

echo "MAX production webhook smoke OK\n";
