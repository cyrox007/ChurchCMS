<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\ApiResource;
use DateTimeImmutable;
use DateTimeZone;

final class MediaApiResource implements ApiResource
{
    public function __construct(
        private readonly MediaAsset $asset,
    ) {
    }

    /**
     * Безопасная projection метаданных для federation/partner API.
     *
     * В ChurchCMS пока нет доверенного blob/storage pipeline, поэтому
     * projection не содержит пути или файла и явно сообщает metadata-only
     * состояние.
     *
     * @return array<string,mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->asset->publicId,
            'type' => 'media',
            'media_type' => $this->asset->mediaType,
            'mime_type' => $this->asset->mimeType,
            'bytes' => $this->asset->bytes,
            'sha256' => $this->asset->sha256,
            'title' => $this->asset->title,
            'alt_text' => $this->asset->altText,
            'organization_owner_id' =>
                $this->asset->ownerOrganizationPublicId,
            'blob_available' => false,
            'updated_at' => self::timestamp(
                $this->asset->updatedAt,
            ),
            'url' => null,
        ];
    }

    private static function timestamp(string $value): string
    {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC'),
        ))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }
}
