<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ChannelOutboundItem
{
    /**
     * @param list<array<string,mixed>> $media
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $kind,
        public readonly string $title,
        public readonly string $text,
        public readonly string $canonicalUrl,
        public readonly array $media = [],
    ) {
    }
}
