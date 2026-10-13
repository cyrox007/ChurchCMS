<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

interface RutubeUploadTransport
{
    public function isReady(): bool;

    public function diagnosticCode(): string;

    public function diagnosticMessage(): string;

    /** @param array<string,mixed> $video */
    public function upload(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
        array $video,
    ): RutubeUploadResult;
}
