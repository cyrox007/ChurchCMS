<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaResumableTransfer
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public string $mediaPublicId,
        public string $providerId,
        public string $targetKey,
        public string $sessionEncrypted,
        public int $uploadedBytes,
        public int $totalBytes,
        public string $status,
        public ?string $lastError,
        public ?string $expiresAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
