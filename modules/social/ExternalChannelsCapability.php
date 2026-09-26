<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ExternalChannelsCapability
{
    public function connections(): SocialConnectionRepository
    {
        return SocialConnectionRepository::fromDatabase();
    }

    public function inbox(): ExternalChannelItemRepository
    {
        return ExternalChannelItemRepository::fromDatabase();
    }

    public function sync(): ChannelSyncService
    {
        return new ChannelSyncService();
    }

    /** @return array<string,ChannelAdapter> */
    public function adapters(): array
    {
        return ChannelAdapterRegistry::all();
    }
}
