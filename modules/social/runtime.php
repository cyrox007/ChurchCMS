<?php

declare(strict_types=1);

use ChurchCMS\App\Services\AdminTaskRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Modules\Social\ExternalChannelsCapability;
use ChurchCMS\Modules\Social\SocialAdminTaskProvider;

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
    'SocialAdminTaskProvider.php',
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

        // Конкретные провайдеры регистрируют адаптеры здесь или из другого модуля.
        // Хранилище остаётся независимым от провайдера, поэтому новые сети
        // и видеохостинги не требуют переработки ядра или схемы БД.

        AdminTaskRegistry::register(
            new SocialAdminTaskProvider(),
        );
    }
};
