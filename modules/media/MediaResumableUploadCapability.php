<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaResumableUploadCapability
{
    public function service(): MediaResumableTransferService
    {
        return MediaResumableTransferService::fromConfig();
    }

    public function source(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): MediaBinarySource {
        return $this->service()->source(
            $mediaPublicId,
            $siteKey,
        );
    }
}
