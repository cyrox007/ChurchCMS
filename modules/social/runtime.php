<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Modules\Social\ExternalChannelsCapability;

$moduleRoot = __DIR__;
foreach ([
    'SocialProvider.php',
    'SocialConnection.php',
    'SocialPost.php',
    'ChannelCapability.php',
    'ChannelOutboundItem.php',
    'ChannelInboundItem.php',
    'ChannelPullBatch.php',
    'ChannelPublishResult.php',
    'ChannelAdapter.php',
    'ChannelAdapterRegistry.php',
    'NativeHttpClient.php',
    'SocialConnectionRepository.php',
    'SocialPostRepository.php',
    'ExternalChannelItem.php',
    'ExternalChannelItemRepository.php',
    'ChannelSyncStateRepository.php',
    'ChannelSyncService.php',
    'ExternalChannelsCapability.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    private ExternalChannelsCapability $capability;

    public function moduleId(): string
    {
        return 'social';
    }

    public function capabilities(): array
    {
        return [
            'channels.connections' => $this->capability,
            'channels.publish' => $this->capability,
            'channels.import' => $this->capability,
            'social.connections' => $this->capability,
            'social.publication' => $this->capability,
        ];
    }

    public function boot(): void
    {
        $this->capability = new ExternalChannelsCapability();

        // Concrete providers register their adapters here or from another module.
        // The storage/schema stays provider-agnostic, so new networks/video hosts
        // do not require ChurchCMS core/database redesign.
    }
};
