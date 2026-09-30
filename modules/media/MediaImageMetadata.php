<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final readonly class MediaImageMetadata
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
    }
}
