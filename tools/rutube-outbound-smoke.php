#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\RutubePublishingChannelAdapter;
use ChurchCMS\Modules\Social\RutubeUploadResult;
use ChurchCMS\Modules\Social\RutubeUploadTransport;
use ChurchCMS\Modules\Social\RutubeVideoPolicy;
use ChurchCMS\Modules\Social\SocialConnection;

require dirname(__DIR__) . '/core.php';

$connection = new SocialConnection(
    id: 1,
    publicId: '01RUTUBEOUTBOUNDTEST00000001',
    provider: 'rutube',
    name: 'Rutube тест',
    targetRef: '12345',
    tokenEncrypted: 'test',
    settings: [],
    enabled: true,
    outboundEnabled: true,
    inboundEnabled: true,
    inboundPolicy: 'review',
    connectionKind: 'social',
    createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    updatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
);

$readyTransport = new class implements RutubeUploadTransport {
    public array $uploads = [];

    public function isReady(): bool
    {
        return true;
    }

    public function diagnosticCode(): string
    {
        return 'rutube_test_ready';
    }

    public function diagnosticMessage(): string
    {
        return 'Тестовый транспорт готов.';
    }

    public function upload(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
        array $video,
    ): RutubeUploadResult {
        $this->uploads[] = [
            'connection' => $connection->publicId,
            'source' => $item->sourceId,
            'video' => $video['public_id'] ?? null,
        ];

        return new RutubeUploadResult(
            success: true,
            remoteId: 'rutube-video-id',
            remoteUrl: 'https://rutube.ru/video/0123456789abcdef0123456789abcdef/',
        );
    }
};

$adapter = new RutubePublishingChannelAdapter(
    upload: $readyTransport,
);

if (!in_array(
    ChannelCapability::PUBLISH_VIDEO,
    $adapter->capabilities(),
    true,
)) {
    fwrite(STDERR, "Rutube не объявляет исходящую публикацию видео.\n");
    exit(1);
}

$status = $adapter->operationalStatus();
if (
    ($status['outbound_ready'] ?? false) !== true
    || ($status['inbound_ready'] ?? false) !== true
) {
    fwrite(STDERR, "Готовый транспорт Rutube не отражён в диагностике.\n");
    exit(1);
}

$validItem = new ChannelOutboundItem(
    sourceId: 'publication-public-id',
    kind: 'news',
    title: 'Видео для Rutube',
    text: 'Описание',
    canonicalUrl: 'https://example.test/publications/video',
    media: [[
        'type' => 'video',
        'public_id' => 'media-video-id',
        'site_key' => 'default',
        'mime_type' => 'video/mp4',
        'bytes' => 1024,
        'sha256' => str_repeat('a', 64),
        'duration_seconds' => 600,
    ]],
);

$result = $adapter->publish(
    $connection,
    '',
    $validItem,
);
if (
    !$result->success
    || $result->remoteId !== 'rutube-video-id'
    || count($readyTransport->uploads) !== 1
) {
    fwrite(STDERR, "Валидное видео не прошло исходящий Rutube transport.\n");
    exit(1);
}

$invalidCases = [
    new ChannelOutboundItem(
        'missing-video',
        'news',
        'Без видео',
        '',
        'https://example.test/1',
        [],
    ),
    new ChannelOutboundItem(
        'too-large',
        'news',
        'Слишком большое видео',
        '',
        'https://example.test/2',
        [[
            'type' => 'video',
            'public_id' => 'media-large',
            'mime_type' => 'video/mp4',
            'bytes' => RutubeVideoPolicy::MAX_BYTES + 1,
        ]],
    ),
    new ChannelOutboundItem(
        'bad-format',
        'news',
        'Неверный формат',
        '',
        'https://example.test/3',
        [[
            'type' => 'video',
            'public_id' => 'media-bad-format',
            'mime_type' => 'application/octet-stream',
            'bytes' => 1024,
        ]],
    ),
    new ChannelOutboundItem(
        'too-long',
        'news',
        'Слишком длинное видео',
        '',
        'https://example.test/4',
        [[
            'type' => 'video',
            'public_id' => 'media-too-long',
            'mime_type' => 'video/mp4',
            'bytes' => 1024,
            'duration_seconds' => RutubeVideoPolicy::MAX_DURATION_SECONDS + 1,
        ]],
    ),
];

foreach ($invalidCases as $case) {
    $invalid = $adapter->publish($connection, '', $case);
    if ($invalid->success || $invalid->retryable) {
        fwrite(
            STDERR,
            "Некорректное видео Rutube не было остановлено как неретраебельное.\n",
        );
        exit(1);
    }
}

$defaultAdapter = new RutubePublishingChannelAdapter();
$defaultStatus = $defaultAdapter->operationalStatus();
if (
    ($defaultStatus['outbound_ready'] ?? true) !== false
    || ($defaultStatus['code'] ?? '') !== 'rutube_partner_upload_api_required'
) {
    fwrite(
        STDERR,
        "Отсутствие официального upload API Rutube скрыто от диагностики.\n",
    );
    exit(1);
}

$blocked = $defaultAdapter->publish(
    $connection,
    '',
    $validItem,
);
if (
    $blocked->success
    || $blocked->retryable
    || !str_contains(
        (string) $blocked->error,
        'партнёрский контракт Rutube',
    )
) {
    fwrite(
        STDERR,
        "Rutube без партнёрского транспорта не возвращает безопасный блокирующий статус.\n",
    );
    exit(1);
}

echo "Исходящий контур Rutube: smoke OK\n";
