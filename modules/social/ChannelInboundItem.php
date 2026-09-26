<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;

final class ChannelInboundItem
{
    /**
     * @param list<array<string,mixed>> $media
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public readonly string $remoteId,
        public readonly string $kind,
        public readonly ?string $title,
        public readonly string $text,
        public readonly ?string $canonicalUrl,
        public readonly array $media = [],
        public readonly ?DateTimeImmutable $publishedAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
        public readonly array $payload = [],
    ) {
    }
}
