<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaStoredBlob
{
    public function __construct(
        public string $sha256,
        public int $bytes,
        public string $mimeType,
        public string $mediaType,
        public bool $created,
    ) {
    }
}
