<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

interface ChannelAdapter
{
    public function providerId(): string;

    public function label(): string;

    /** @return list<string> */
    public function capabilities(): array;

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult;

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch;
}
