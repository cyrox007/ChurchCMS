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

    /**
     * @return array{
     *     type:string,
     *     public_id:string,
     *     site_key:string,
     *     mime_type:string,
     *     bytes:int,
     *     sha256:string
     * }|null
     */
    public function publicationVideo(
        string $publicationPublicId,
        string $siteKey = 'default',
    ): ?array {
        $usage = MediaUsageService::fromDatabase();
        $mediaPublicId = null;

        foreach (
            $usage->forConsumer(
                'publication-seo',
                $publicationPublicId,
                $siteKey,
            ) as $reference
        ) {
            if ($reference->usageKey === 'video') {
                $mediaPublicId = $reference->mediaPublicId;
                break;
            }
        }

        if ($mediaPublicId === null) {
            return null;
        }

        $source = $this->source(
            $mediaPublicId,
            $siteKey,
        );

        return [
            'type' => 'video',
            'public_id' => $source->mediaPublicId,
            'site_key' => $source->siteKey,
            'mime_type' => $source->mimeType,
            'bytes' => $source->bytes,
            'sha256' => $source->sha256,
        ];
    }
}
