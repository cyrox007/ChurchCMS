<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

enum SocialProvider: string
{
    case Telegram = 'telegram';
    case Vk = 'vk';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Vk => 'ВКонтакте',
            self::Max => 'MAX',
        };
    }
}
