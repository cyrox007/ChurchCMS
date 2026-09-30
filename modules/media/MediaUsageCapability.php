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

    /**
     * @param list<string>|null $ownerPublicIds
     * @return list<array<string,mixed>>
     */
    public function selectableAssets(
        ?array $ownerPublicIds,
        string $mediaType,
        string $siteKey = 'default',
    ): array {
        $mediaType = trim($mediaType);
        $result = [];

        foreach (
            MediaRepository::fromDatabase()->adminList(
                $ownerPublicIds,
                $siteKey,
                500,
            ) as $asset
        ) {
            if (
                $asset->status === 'archived'
                || $asset->mediaType !== $mediaType
            ) {
                continue;
            }

            $result[] = [
                'public_id' => $asset->publicId,
                'media_type' => $asset->mediaType,
                'mime_type' => $asset->mimeType,
                'bytes' => $asset->bytes,
                'title' => $asset->title
                    ?? $asset->originalName,
                'visibility' => $asset->visibility,
                'owner_organization_public_id' =>
                    $asset->ownerOrganizationPublicId,
            ];
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function publicAssetDescriptor(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): ?array {
        $asset = MediaRepository::fromDatabase()
            ->findByPublicId(
                trim($mediaPublicId),
                $siteKey,
            );

        if (
            $asset === null
            || $asset->status === 'archived'
            || $asset->visibility !== 'public'
        ) {
            return null;
        }

        try {
            $file = MediaPublicFileService::fromConfig()
                ->resolve(
                    $asset->publicId,
                    $asset->sha256,
                    'original',
                    $siteKey,
                );
        } catch (\Throwable) {
            return null;
        }

        if ($file === null) {
            return null;
        }

        return [
            'public_id' => $asset->publicId,
            'media_type' => $asset->mediaType,
            'mime_type' => $asset->mimeType,
            'bytes' => $asset->bytes,
            'sha256' => $asset->sha256,
            'title' => $asset->title
                ?? $asset->originalName,
            'url' => MediaPublicFileService::url(
                $asset->publicId,
                $asset->sha256,
                'original',
            ),
        ];
    }

    /**
     * @param list<string>|null $ownerPublicIds
     * @return list<array<string,mixed>>
     */
    public function structuredSeoAssets(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
    ): array {
        return MediaStructuredSeoService::fromDatabase()
            ->availableAssets(
                $ownerPublicIds,
                $siteKey,
            );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function structuredSeoDescriptor(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): ?array {
        return MediaStructuredSeoService::fromDatabase()
            ->descriptor(
                $mediaPublicId,
                $siteKey,
            );
    }
}
