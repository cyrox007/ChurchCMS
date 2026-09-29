<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

/**
 * Дополнительный контракт адаптера для проверки настроек до сохранения секрета.
 */
interface ChannelConnectionTester
{
    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult;
}
