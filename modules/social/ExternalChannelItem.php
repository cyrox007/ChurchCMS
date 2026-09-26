<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;

final class ExternalChannelItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $connectionId,
        public readonly string $remoteId,
        public readonly string $kind,
        public readonly ?string $title,
        public readonly string $bodyText,
        public readonly ?string $canonicalUrl,
        public readonly array $media,
        public readonly string $status,
        public readonly ?int $linkedPublicationId,
        public readonly ?DateTimeImmutable $remotePublishedAt,
        public readonly ?DateTimeImmutable $remoteUpdatedAt,
        public readonly DateTimeImmutable $discoveredAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }
}
