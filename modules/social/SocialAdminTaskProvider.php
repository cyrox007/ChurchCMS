<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\App\Services\AdminTaskProvider;

final class SocialAdminTaskProvider implements AdminTaskProvider
{
    public function id(): string
    {
        return 'social';
    }

    public function permission(): string
    {
        return 'social.manage';
    }

    public function tasks(
        int $limit,
        int $userId = 0,
    ): array {
        $outbound = SocialPostRepository::fromDatabase()->failedCount();
        $sync = ChannelSyncStateRepository::fromDatabase()->failedCount();
        $count = $outbound + $sync;

        if ($count < 1) {
            return [];
        }

        $parts = [];
        if ($outbound > 0) {
            $parts[] = 'ошибок публикации: ' . $outbound;
        }
        if ($sync > 0) {
            $parts[] = 'ошибок синхронизации: ' . $sync;
        }

        return [[
            'id' => 'failed-sync',
            'title' => 'Ошибки внешних каналов',
            'description' => implode('; ', $parts) . '.',
            'count' => $count,
            'severity' => 'error',
            'route' => 'admin_external_channels',
            'route_params' => [],
        ]];
    }
}
