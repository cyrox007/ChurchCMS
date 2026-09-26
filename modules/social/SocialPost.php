<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;

final class SocialPost
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $publicationId,
        public readonly int $connectionId,
        public readonly bool $enabled,
        public readonly ?string $customText,
        public readonly string $status,
        public readonly ?string $remotePostId,
        public readonly int $attempts,
        public readonly ?string $lastError,
        public readonly ?DateTimeImmutable $queuedAt,
        public readonly ?DateTimeImmutable $sentAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }
}
