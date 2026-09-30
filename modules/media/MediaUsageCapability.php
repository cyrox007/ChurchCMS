<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaUsageCapability
{
    public function usage(): MediaUsageService
    {
        return MediaUsageService::fromDatabase();
    }

    /**
     * @param array<string,string> $references usage_key => media_public_id
     */
    public function replaceConsumerReferences(
        string $consumerType,
        string $consumerPublicId,
        array $references,
        string $siteKey = 'default',
    ): void {
        $this->usage()->replaceConsumerReferences(
            $consumerType,
            $consumerPublicId,
            $references,
            $siteKey,
        );
    }

    /**
     * @return list<MediaUsageReference>
     */
    public function referencesForAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): array {
        return $this->usage()->forAsset(
            $mediaPublicId,
            $siteKey,
        );
    }

    /**
     * @return list<MediaUsageReference>
     */
    public function referencesForConsumer(
        string $consumerType,
        string $consumerPublicId,
        string $siteKey = 'default',
    ): array {
        return $this->usage()->forConsumer(
            $consumerType,
            $consumerPublicId,
            $siteKey,
        );
    }

    /**
     * @return array{
     *     public_id:string,
     *     media_type:string,
     *     mime_type:string,
     *     status:string
     * }|null
     */
    public function assetDescriptor(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): ?array {
        return $this->usage()->assetDescriptor(
            $mediaPublicId,
            $siteKey,
        );
    }
}
