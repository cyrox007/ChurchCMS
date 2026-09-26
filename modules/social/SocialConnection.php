<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;

final class SocialConnection
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly string $provider,
        public readonly string $name,
        public readonly string $targetRef,
        public readonly string $tokenEncrypted,
        public readonly array $settings,
        public readonly bool $enabled,
        public readonly bool $outboundEnabled,
        public readonly bool $inboundEnabled,
        public readonly string $inboundPolicy,
        public readonly string $connectionKind,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }
}
