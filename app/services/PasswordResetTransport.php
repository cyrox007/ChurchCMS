<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

interface PasswordResetTransport
{
    public function deliver(
        PasswordResetMessage $message,
    ): void;
}
