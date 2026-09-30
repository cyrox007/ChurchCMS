<?php

declare(strict_types=1);

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\SecretVault;
use ChurchCMS\Modules\Social\ChannelAdapter;
use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelInboundItem;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\ChannelPublishResult;
use ChurchCMS\Modules\Social\ChannelPullBatch;
use ChurchCMS\Modules\Social\ChannelWebhookAdapter;
use ChurchCMS\Modules\Social\ChannelWebhookException;
use ChurchCMS\Modules\Social\ChannelWebhookRequest;
use ChurchCMS\Modules\Social\ChannelWebhookResult;
use ChurchCMS\Modules\Social\ChannelWebhookService;
use ChurchCMS\Modules\Social\ChannelWebhookSignature;
use ChurchCMS\Modules\Social\ExternalChannelItemRepository;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionRepository;

require dirname(__DIR__) . '/core.php';

$secret = 'webhook-smoke-secret';
$raw = '{"remote_id":"event-1","title":"Тестовый webhook","text":"Точное тело"}';
$signature = 'sha256=' . hash_hmac(
    'sha256',
    $raw,
    $secret,
);

$request = new Request(
    server: [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CHURCHCMS_SIGNATURE' => $signature,
    ],
    rawBody: $raw,
);

if (
    $request->rawBody() !== $raw
    || $request->header('X-ChurchCMS-Signature') !== $signature
    || ($request->headers()['x-churchcms-signature'] ?? null) !== $signature
    || $request->json('remote_id') !== 'event-1'
) {
    fwrite(
        STDERR,
        "Request не сохранил точное webhook-тело или заголовки.\n",
    );
    exit(1);
}

$oversized = new Request(
    rawBody: str_repeat('x', 1048577),
);
if (
    $oversized->rawBody() !== null
    || $oversized->bodyError() !== 'request_body_too_large'
) {
    fwrite(STDERR, "Лимит webhook-тела не сработал.\n");
    exit(1);
}

$connection = SocialConnectionRepository::fromDatabase()
    ->create(
        provider: 'webhook-smoke',
        name: 'Webhook smoke',
        targetRef: 'remote-channel',
        tokenEncrypted: SecretVault::encrypt($secret),
        outboundEnabled: false,
        inboundEnabled: true,
    );

$adapter = new class implements ChannelAdapter, ChannelWebhookAdapter {
    public int $verified = 0;
    public int $received = 0;

    public function providerId(): string
    {
        return 'webhook-smoke';
    }

    public function label(): string
    {
        return 'Webhook smoke';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::WEBHOOK,
        ];
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(
            false,
            error: 'Этот smoke не публикует исходящие материалы.',
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

    public function verifyWebhook(
        SocialConnection $connection,
        string $credentials,
        ChannelWebhookRequest $request,
    ): bool {
        $this->verified++;

        return ChannelWebhookSignature::verifyHmacSha256(
            (string) $request->header(
                'X-ChurchCMS-Signature',
                '',
            ),
            $credentials,
            $request->rawBody,
        );
    }

    public function receiveWebhook(
        SocialConnection $connection,
        string $credentials,
        ChannelWebhookRequest $request,
    ): ChannelWebhookResult {
        $this->received++;

        $payload = json_decode(
            $request->rawBody,
            true,
            64,
            JSON_THROW_ON_ERROR,
        );

        return new ChannelWebhookResult(
            items: [
                new ChannelInboundItem(
                    remoteId: (string) ($payload['remote_id'] ?? ''),
                    kind: 'post',
                    title: (string) ($payload['title'] ?? ''),
                    text: (string) ($payload['text'] ?? ''),
                    canonicalUrl: null,
                    payload: $payload,
                ),
            ],
            status: 202,
            body: '{"accepted":true}',
            contentType: 'application/json; charset=utf-8',
        );
    }
};

ChannelAdapterRegistry::register($adapter);
$service = ChannelWebhookService::fromDatabase();
$inbox = ExternalChannelItemRepository::fromDatabase();

$badRequest = new ChannelWebhookRequest(
    rawBody: $raw,
    headers: [
        'x-churchcms-signature' => 'sha256=invalid',
    ],
);

try {
    $service->receive(
        $connection->publicId,
        $badRequest,
    );
    fwrite(STDERR, "Webhook с неверной подписью ошибочно принят.\n");
    exit(1);
} catch (ChannelWebhookException $error) {
    if ($error->httpStatus !== 401) {
        throw $error;
    }
}

if (
    $adapter->received !== 0
    || $inbox->pending() !== []
) {
    fwrite(
        STDERR,
        "Payload обработан до успешной проверки подписи.\n",
    );
    exit(1);
}

$validRequest = new ChannelWebhookRequest(
    rawBody: $raw,
    headers: [
        'x-churchcms-signature' => $signature,
    ],
);

$result = $service->receive(
    $connection->publicId,
    $validRequest,
);

if (
    $result->status !== 202
    || $result->body !== '{"accepted":true}'
    || $result->contentType !== 'application/json; charset=utf-8'
    || $adapter->received !== 1
) {
    fwrite(STDERR, "Webhook acknowledgement сформирован некорректно.\n");
    exit(1);
}

$pending = $inbox->pending();
if (
    count($pending) !== 1
    || $pending[0]->remoteId !== 'event-1'
    || $pending[0]->title !== 'Тестовый webhook'
    || $pending[0]->bodyText !== 'Точное тело'
) {
    fwrite(STDERR, "Webhook item не попал в review queue.\n");
    exit(1);
}

$tampered = new ChannelWebhookRequest(
    rawBody: str_replace(
        'Точное тело',
        'Подменённое тело',
        $raw,
    ),
    headers: [
        'x-churchcms-signature' => $signature,
    ],
);

try {
    $service->receive(
        $connection->publicId,
        $tampered,
    );
    fwrite(STDERR, "Подменённое webhook-тело прошло старую подпись.\n");
    exit(1);
} catch (ChannelWebhookException $error) {
    if ($error->httpStatus !== 401) {
        throw $error;
    }
}

$service->receive(
    $connection->publicId,
    $validRequest,
);
if (count($inbox->pending()) !== 1) {
    fwrite(STDERR, "Повтор webhook создал дубликат inbound item.\n");
    exit(1);
}

if (
    !ChannelWebhookSignature::verifySecret(
        'shared-secret',
        'shared-secret',
    )
    || ChannelWebhookSignature::verifySecret(
        'shared-secret',
        'another-secret',
    )
) {
    fwrite(STDERR, "Constant-time secret helper работает некорректно.\n");
    exit(1);
}

$route = Router::getInstance()->url(
    'external_channels_webhook',
    [
        'connectionPublicId' => $connection->publicId,
    ],
);
$expectedRoute = '/api/v1/external-channels/'
    . rawurlencode($connection->publicId)
    . '/webhook';

if ($route !== $expectedRoute) {
    fwrite(STDERR, "Webhook route зарегистрирован некорректно.\n");
    exit(1);
}

echo "External channels webhook contract smoke OK\n";
