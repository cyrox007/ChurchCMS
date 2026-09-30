<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\App\Services\AdminTaskRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Social\ChannelAdapterRegistry;
use ChurchCMS\Modules\Social\ExternalChannelsCapability;
use ChurchCMS\Modules\Social\TelegramChannelAdapter;
use ChurchCMS\Modules\Social\VkChannelAdapter;
use ChurchCMS\Modules\Social\MaxChannelAdapter;
use ChurchCMS\Modules\Social\SocialAdminController;
use ChurchCMS\Modules\Social\SocialAdminTaskProvider;
use ChurchCMS\Modules\Social\SocialWebhookController;

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
    'ChannelConnectionTestResult.php',
    'ChannelConnectionTester.php',
    'ChannelConnectionActivator.php',
    'ChannelWebhookRequest.php',
    'ChannelWebhookResult.php',
    'ChannelWebhookAdapter.php',
    'ChannelWebhookSignature.php',
    'ChannelWebhookException.php',
    'ChannelAdapterRegistry.php',
    'ChannelHttpClient.php',
    'NativeHttpClient.php',
    'TelegramChannelAdapter.php',
    'VkChannelAdapter.php',
    'MaxChannelAdapter.php',
    'SocialConnectionRepository.php',
    'SocialConnectionService.php',
    'SocialPostRepository.php',
    'SocialPublicationChannelService.php',
    'ExternalChannelItem.php',
    'ExternalChannelItemRepository.php',
    'ChannelSyncStateRepository.php',
    'ChannelSyncService.php',
    'ChannelWebhookService.php',
    'ChannelOutboundDispatcher.php',
    'SocialOutboundFailureService.php',
    'ExternalChannelsCapability.php',
    'SocialAdminTaskProvider.php',
    'SocialAdminController.php',
    'SocialWebhookController.php',
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

        // Встроенные и сторонние провайдеры используют общий реестр.
        // Хранилище остаётся независимым от конкретной платформы.
        ChannelAdapterRegistry::register(
            new TelegramChannelAdapter(),
        );
        ChannelAdapterRegistry::register(
            new VkChannelAdapter(),
        );
        ChannelAdapterRegistry::register(
            new MaxChannelAdapter(),
        );

        AdminNavigationRegistry::register(
            id: 'external-channels',
            label: 'Внешние каналы',
            route: 'admin_external_channels',
            permission: 'social.manage',
            priority: 50,
        );

        AdminTaskRegistry::register(
            new SocialAdminTaskProvider(),
        );

        $router = Router::getInstance();

        $router->add(
            'POST',
            '/api/v1/external-channels/{connectionPublicId}/webhook',
            [SocialWebhookController::class, 'receive'],
            [],
            'external_channels_webhook',
        );

        $router->add(
            'GET',
            '/admin/external-channels',
            [SocialAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_external_channels',
        );

        $router->add(
            'POST',
            '/admin/external-channels',
            [SocialAdminController::class, 'create'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_external_channels_create',
        );

        $router->add(
            'POST',
            '/admin/external-channels/inbox/{publicId}/ignore',
            [SocialAdminController::class, 'ignoreInboxItem'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_external_channels_inbox_ignore',
        );

        $router->add(
            'POST',
            '/admin/external-channels/inbox/{publicId}/link',
            [SocialAdminController::class, 'linkInboxItem'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_external_channels_inbox_link',
        );

        $router->add(
            'POST',
            '/admin/external-channels/inbox/{publicId}/import',
            [SocialAdminController::class, 'importInboxItem'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_external_channels_inbox_import',
        );

        $router->add(
            'POST',
            '/admin/external-channels/outbox/{publicId}/retry',
            [SocialAdminController::class, 'retryOutbound'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_external_channels_outbox_retry',
        );
    }
};
