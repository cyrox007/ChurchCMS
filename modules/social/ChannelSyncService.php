<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\SecretVault;
use Throwable;

final class ChannelSyncService
{
    public function syncAll(int $limitPerConnection = 50): array
    {
        $connections = SocialConnectionRepository::fromDatabase()->inboundEnabled();
        $inbox = ExternalChannelItemRepository::fromDatabase();
        $state = ChannelSyncStateRepository::fromDatabase();

        $summary = [
            'connections' => 0,
            'items' => 0,
            'errors' => 0,
        ];

        foreach ($connections as $connection) {
            $adapter = ChannelAdapterRegistry::get($connection->provider);
            if (
                $adapter === null
                || !in_array(ChannelCapability::POLLING, $adapter->capabilities(), true)
            ) {
                continue;
            }

            $summary['connections']++;

            try {
                $credentials = SecretVault::decrypt($connection->tokenEncrypted);
                $batch = $adapter->pull(
                    $connection,
                    $credentials,
                    $state->cursor($connection->id),
                    $limitPerConnection,
                );

                foreach ($batch->items as $item) {
                    $inbox->store($connection->id, $item);
                    $summary['items']++;
                }

                $state->success($connection->id, $batch->nextCursor);
            } catch (Throwable $e) {
                $summary['errors']++;
                $state->failure($connection->id, $e->getMessage());
            }
        }

        return $summary;
    }
}
