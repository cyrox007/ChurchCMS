<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaDerivativeCapability
{
    public function service(): MediaImageDerivativeService
    {
        return MediaImageDerivativeService::fromConfig();
    }

    /**
     * @return list<string>
     */
    public function variants(): array
    {
        return $this->service()->variants();
    }

    public function generate(
        string $mediaPublicId,
        string $variant,
        string $siteKey = 'default',
    ): MediaDerivative {
        return $this->service()->generate(
            $mediaPublicId,
            $variant,
            $siteKey,
        );
    }

    /**
     * @return list<MediaDerivative>
     */
    public function forAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): array {
        return $this->service()->forAsset(
            $mediaPublicId,
            $siteKey,
        );
    }
}
