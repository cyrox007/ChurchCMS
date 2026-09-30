<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

/**
 * Дополнительный контракт для провайдера, которому после локальной проверки
 * нужна удалённая активация входящего webhook.
 */
interface ChannelConnectionActivator
{
    public function activateConnection(
        SocialConnection $connection,
        string $credentials,
        string $webhookUrl,
    ): void;
}
