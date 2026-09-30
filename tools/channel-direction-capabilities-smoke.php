<?php

declare(strict_types=1);

use ChurchCMS\Modules\Social\ChannelAdapter;
use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ChannelCapability;
use ChurchCMS\Modules\Social\ChannelConnectionTestResult;
use ChurchCMS\Modules\Social\ChannelConnectionTester;
use ChurchCMS\Modules\Social\ChannelInboundItem;
use ChurchCMS\Modules\Social\ChannelOutboundItem;
use ChurchCMS\Modules\Social\ChannelPublishResult;
use ChurchCMS\Modules\Social\ChannelPullBatch;
use ChurchCMS\Modules\Social\SocialConnection;
use ChurchCMS\Modules\Social\SocialConnectionService;

require dirname(__DIR__) . '/core.php';

$inboundOnly = new class implements
    ChannelAdapter,
    ChannelConnectionTester
{
    public function providerId(): string
    {
        return 'direction-inbound-only';
    }

    public function label(): string
    {
        return 'Только входящие';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::IMPORT_VIDEO,
            ChannelCapability::POLLING,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        return new ChannelConnectionTestResult(true);
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

$outboundOnly = new class implements
    ChannelAdapter,
    ChannelConnectionTester
{
    public function providerId(): string
    {
        return 'direction-outbound-only';
    }

    public function label(): string
    {
        return 'Только исходящие';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::PUBLISH_TEXT,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        return new ChannelConnectionTestResult(true);
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        return new ChannelPublishResult(true);
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

ChannelAdapterRegistry::register($inboundOnly);
ChannelAdapterRegistry::register($outboundOnly);

$service = SocialConnectionService::fromDatabase();
$available = [];

foreach ($service->availableAdapters() as $adapter) {
    $available[$adapter['id']] = $adapter;
}

if (
    ($available['direction-inbound-only']['can_publish'] ?? true)
    !== false
    || ($available['direction-inbound-only']['can_import'] ?? false)
    !== true
    || ($available['direction-outbound-only']['can_publish'] ?? false)
    !== true
    || ($available['direction-outbound-only']['can_import'] ?? true)
    !== false
) {
    fwrite(
        STDERR,
        "Метаданные направлений адаптеров сформированы неверно.\n",
    );
    exit(1);
}

try {
    $service->create(
        provider: 'direction-inbound-only',
        name: 'Неверный outbound',
        targetRef: 'target',
        credentials: 'secret',
        outboundEnabled: true,
        inboundEnabled: false,
    );

    fwrite(
        STDERR,
        "Inbound-only адаптер ошибочно разрешил outbound.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

try {
    $service->create(
        provider: 'direction-outbound-only',
        name: 'Неверный inbound',
        targetRef: 'target',
        credentials: 'secret',
        outboundEnabled: false,
        inboundEnabled: true,
    );

    fwrite(
        STDERR,
        "Outbound-only адаптер ошибочно разрешил inbound.\n",
    );
    exit(1);
} catch (\InvalidArgumentException) {
}

$inbound = $service->create(
    provider: 'direction-inbound-only',
    name: 'Корректный inbound',
    targetRef: 'target',
    credentials: 'secret',
    outboundEnabled: false,
    inboundEnabled: true,
);

$outbound = $service->create(
    provider: 'direction-outbound-only',
    name: 'Корректный outbound',
    targetRef: 'target',
    credentials: 'secret',
    outboundEnabled: true,
    inboundEnabled: false,
);

if (
    !$inbound->inboundEnabled
    || $inbound->outboundEnabled
    || !$outbound->outboundEnabled
    || $outbound->inboundEnabled
) {
    fwrite(
        STDERR,
        "Валидные направления подключения сохранены неверно.\n",
    );
    exit(1);
}

echo "Channel direction capabilities smoke OK\n";
