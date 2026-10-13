<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class RutubeUnavailableUploadTransport implements RutubeUploadTransport
{
    public function isReady(): bool
    {
        return false;
    }

    public function diagnosticCode(): string
    {
        return 'rutube_partner_upload_api_required';
    }

    public function diagnosticMessage(): string
    {
        return 'Исходящий контур ChurchCMS готов, но официальный машинный upload API Rutube не опубликован. Для автоматической отправки требуется подтверждённый партнёрский контракт Rutube.';
    }

    public function upload(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
        array $video,
    ): RutubeUploadResult {
        return new RutubeUploadResult(
            success: false,
            error: $this->diagnosticMessage(),
            retryable: false,
        );
    }
}
