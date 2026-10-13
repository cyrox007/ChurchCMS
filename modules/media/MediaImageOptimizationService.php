<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\Config;
use InvalidArgumentException;
use RuntimeException;

final class MediaImageOptimizationService
{
    public function __construct(
        private readonly MediaBlobStorage $storage,
        private readonly MediaRepository $media,
        private readonly MediaDerivativeRepository $derivatives,
        private readonly int $maxSide,
        private readonly int $quality,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaBlobStorage::fromConfig(),
            MediaRepository::fromDatabase(),
            MediaDerivativeRepository::fromDatabase(),
            max(640, min(8192, (int) Config::get('media.image_web_max_side', 2560))),
            max(50, min(95, (int) Config::get('media.image_web_quality', 82))),
        );
    }

    public function generateWeb(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): MediaDerivative {
        $asset = $this->media->findByPublicId(trim($mediaPublicId), $siteKey);
        if ($asset === null || $asset->status === 'archived') {
            throw new InvalidArgumentException('Исходное изображение недоступно.');
        }
        if (
            $asset->mediaType !== 'image'
            || $asset->pixelWidth === null
            || $asset->pixelHeight === null
            || !in_array($asset->mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)
        ) {
            throw new InvalidArgumentException(
                'Веб-оптимизация доступна для JPEG, PNG и WebP с известными размерами.'
            );
        }
        if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Для веб-оптимизации Media требуется PHP GD с WebP.');
        }

        $path = MediaArchiveService::fromConfig()->ensureLocal($asset->sha256, $asset->bytes);
        if ($path === null) {
            $path = $this->storage->readablePath($asset->sha256, $asset->bytes);
        }
        if ($path === null) {
            throw new RuntimeException('Исходный Media blob отсутствует.');
        }

        $source = match ($asset->mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$source instanceof \GdImage) {
            throw new RuntimeException('Не удалось декодировать изображение для оптимизации.');
        }

        [$width, $height] = self::fit(
            $asset->pixelWidth,
            $asset->pixelHeight,
            $this->maxSide,
        );
        $target = imagecreatetruecolor($width, $height);
        if (!$target instanceof \GdImage) {
            imagedestroy($source);
            throw new RuntimeException('Не удалось создать веб-версию изображения.');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $width, $height, $transparent);

        try {
            if (!imagecopyresampled(
                $target,
                $source,
                0,
                0,
                0,
                0,
                $width,
                $height,
                $asset->pixelWidth,
                $asset->pixelHeight,
            )) {
                throw new RuntimeException('Не удалось уменьшить изображение для веб-версии.');
            }

            $temporary = tempnam(sys_get_temp_dir(), 'churchcms-web-image-');
            if (!is_string($temporary)) {
                throw new RuntimeException('Не удалось создать временный файл веб-версии.');
            }

            try {
                if (!imagewebp($target, $temporary, $this->quality)) {
                    throw new RuntimeException('Не удалось записать WebP веб-версию.');
                }
                $blob = $this->storage->storeFile($temporary);
            } finally {
                @unlink($temporary);
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }

        return $this->derivatives->save(
            $asset->publicId,
            'web',
            $blob,
            $width,
            $height,
            $siteKey,
        );
    }

    /** @return array{int,int} */
    private static function fit(int $width, int $height, int $maxSide): array
    {
        if ($width <= $maxSide && $height <= $maxSide) {
            return [$width, $height];
        }

        $scale = $maxSide / max($width, $height);
        return [
            max(1, (int) floor($width * $scale)),
            max(1, (int) floor($height * $scale)),
        ];
    }
}
