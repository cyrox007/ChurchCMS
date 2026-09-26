<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ChannelPullBatch
{
    /**
     * @param list<ChannelInboundItem> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor = null,
    ) {
    }
}
