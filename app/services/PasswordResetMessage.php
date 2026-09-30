<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

final readonly class PasswordResetMessage
{
    public function __construct(
        public string $email,
        public string $displayName,
        public string $resetUrl,
        public string $expiresAt,
    ) {
    }
}
