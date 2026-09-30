<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaImageDerivativeService
{
    /**
     * @var array<string,array{width:int,height:int}>
     */
    private const VARIANTS = [
        'thumbnail' => [
            'width' => 320,
            'height' => 320,
        ],
        'medium' => [
            'width' => 1280,
            'height' => 1280,
        ],
    ];

    public function __construct(
        private readonly MediaBlobStorage $storage,
        private readonly MediaRepository $media,
        private readonly MediaDerivativeRepository $derivatives,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            MediaBlobStorage::fromConfig(),
            MediaRepository::fromDatabase(),
            MediaDerivativeRepository::fromDatabase(),
        );
    }

    /**
     * @return list<string>
     */
    public function variants(): array
    {
        return array_keys(self::VARIANTS);
    }

    public function generate(
        string $mediaPublicId,
        string $variant,
        string $siteKey = 'default',
    ): MediaDerivative {
        $variant = trim($variant);
        $preset = self::VARIANTS[$variant] ?? null;

        if ($preset === null) {
            throw new InvalidArgumentException(
                'Неизвестный вариант производного изображения.'
            );
        }

        $asset = $this->media->findByPublicId(
            trim($mediaPublicId),
            $siteKey,
        );

        if (
            $asset === null
            || $asset->status === 'archived'
        ) {
            throw new InvalidArgumentException(
                'Исходный Media-материал недоступен.'
            );
        }

        if (
            $asset->mediaType !== 'image'
            || $asset->pixelWidth === null
            || $asset->pixelHeight === null
        ) {
            throw new InvalidArgumentException(
                'Derivatives доступны только для изображений с известными размерами.'
            );
        }

        if (
            !in_array(
                $asset->mimeType,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
                true,
            )
        ) {
            throw new InvalidArgumentException(
                'Формат изображения пока не поддерживает безопасный derivative.'
            );
        }

        if (!$this->storage->exists($asset->sha256)) {
            throw new RuntimeException(
                'Исходный Media blob отсутствует.'
            );
        }

        [$width, $height] = self::targetSize(
            $asset->pixelWidth,
            $asset->pixelHeight,
            $preset['width'],
            $preset['height'],
        );

        if (
            $width === $asset->pixelWidth
            && $height === $asset->pixelHeight
        ) {
            $blob = new MediaStoredBlob(
                sha256: $asset->sha256,
                bytes: $asset->bytes,
                mimeType: $asset->mimeType,
                mediaType: 'image',
                created: false,
            );

            return $this->derivatives->save(
                $asset->publicId,
                $variant,
                $blob,
                $width,
                $height,
                $siteKey,
            );
        }

        self::assertGd($asset->mimeType);

        $sourcePath = $this->storage->pathForHash(
            $asset->sha256,
        );
        $source = self::openImage(
            $sourcePath,
            $asset->mimeType,
        );

        if (!$source instanceof \GdImage) {
            throw new RuntimeException(
                'Не удалось открыть исходное изображение.'
            );
        }

        $target = imagecreatetruecolor(
            $width,
            $height,
        );
        if (!$target instanceof \GdImage) {
            imagedestroy($source);

            throw new RuntimeException(
                'Не удалось создать производное изображение.'
            );
        }

        self::prepareCanvas(
            $target,
            $asset->mimeType,
        );

        try {
            if (
                !imagecopyresampled(
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
                )
            ) {
                throw new RuntimeException(
                    'Не удалось изменить размер изображения.'
                );
            }

            $temporary = tempnam(
                sys_get_temp_dir(),
                'churchcms-derivative-',
            );
            if (!is_string($temporary)) {
                throw new RuntimeException(
                    'Не удалось создать временный derivative-файл.'
                );
            }

            try {
                self::writeImage(
                    $target,
                    $temporary,
                    $asset->mimeType,
                );

                $blob = $this->storage->storeFile(
                    $temporary,
                );
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }

        return $this->derivatives->save(
            $asset->publicId,
            $variant,
            $blob,
            $width,
            $height,
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
        return $this->derivatives->forAsset(
            $mediaPublicId,
            $siteKey,
        );
    }

    /**
     * @return array{int,int}
     */
    private static function targetSize(
        int $sourceWidth,
        int $sourceHeight,
        int $maxWidth,
        int $maxHeight,
    ): array {
        if (
            $sourceWidth <= $maxWidth
            && $sourceHeight <= $maxHeight
        ) {
            return [
                $sourceWidth,
                $sourceHeight,
            ];
        }

        $scale = min(
            $maxWidth / $sourceWidth,
            $maxHeight / $sourceHeight,
        );

        return [
            max(1, (int) floor($sourceWidth * $scale)),
            max(1, (int) floor($sourceHeight * $scale)),
        ];
    }

    private static function assertGd(string $mimeType): void
    {
        $required = [
            'imagecreatetruecolor',
            'imagecopyresampled',
        ];

        $required[] = match ($mimeType) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            default => '',
        };

        $required[] = match ($mimeType) {
            'image/jpeg' => 'imagejpeg',
            'image/png' => 'imagepng',
            'image/webp' => 'imagewebp',
            default => '',
        };

        foreach ($required as $function) {
            if (
                $function === ''
                || !function_exists($function)
            ) {
                throw new RuntimeException(
                    'Для derivatives изображения требуется PHP GD с поддержкой исходного формата.'
                );
            }
        }
    }

    private static function openImage(
        string $path,
        string $mimeType,
    ): \GdImage {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };

        if (!$image instanceof \GdImage) {
            throw new RuntimeException(
                'Не удалось декодировать исходное изображение.'
            );
        }

        return $image;
    }

    private static function prepareCanvas(
        \GdImage $image,
        string $mimeType,
    ): void {
        if (
            $mimeType !== 'image/png'
            && $mimeType !== 'image/webp'
        ) {
            return;
        }

        imagealphablending(
            $image,
            false,
        );
        imagesavealpha(
            $image,
            true,
        );

        $transparent = imagecolorallocatealpha(
            $image,
            0,
            0,
            0,
            127,
        );
        imagefilledrectangle(
            $image,
            0,
            0,
            imagesx($image),
            imagesy($image),
            $transparent,
        );
    }

    private static function writeImage(
        \GdImage $image,
        string $path,
        string $mimeType,
    ): void {
        $written = match ($mimeType) {
            'image/jpeg' => imagejpeg(
                $image,
                $path,
                85,
            ),
            'image/png' => imagepng(
                $image,
                $path,
                6,
            ),
            'image/webp' => imagewebp(
                $image,
                $path,
                82,
            ),
            default => false,
        };

        if (!$written) {
            throw new RuntimeException(
                'Не удалось записать производное изображение.'
            );
        }
    }
}
