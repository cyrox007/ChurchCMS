<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use RuntimeException;

final class UnavailablePasswordResetTransport implements
    PasswordResetTransport
{
    public function deliver(
        PasswordResetMessage $message,
    ): void {
        throw new RuntimeException(
            'Канал доставки восстановления пароля не настроен.'
        );
    }
}
