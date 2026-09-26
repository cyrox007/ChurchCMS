<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use InvalidArgumentException;

/**
 * Provider IDs are open-ended. Constants below are only built-in adapters,
 * not a whitelist. Third-party modules may register any valid provider ID.
 */
final class SocialProvider
{
    public const TELEGRAM = 'telegram';
    public const VK = 'vk';
    public const MAX = 'max';
    public const YOUTUBE = 'youtube';
    public const RUTUBE = 'rutube';
    public const DZEN = 'dzen';
    public const OK = 'ok';

    public static function normalize(string $providerId): string
    {
        $providerId = strtolower(trim($providerId));

        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $providerId) !== 1) {
            throw new InvalidArgumentException('Invalid external channel provider id.');
        }

        return $providerId;
    }
}
