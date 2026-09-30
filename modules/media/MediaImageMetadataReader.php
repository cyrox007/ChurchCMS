<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use InvalidArgumentException;
use RuntimeException;

final class MediaImageMetadataReader
{
    public function read(
        string $path,
        string $mimeType,
    ): ?MediaImageMetadata {
        $mimeType = strtolower(trim($mimeType));

        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        if (
            $path === ''
            || is_link($path)
            || !is_file($path)
            || !is_readable($path)
        ) {
            throw new InvalidArgumentException(
                'Media blob недоступен для чтения размеров изображения.'
            );
        }

        $info = @getimagesize($path);
        if (!is_array($info)) {
            // Fileinfo уже подтвердил разрешённый image MIME. Если текущая
            // сборка PHP не умеет извлечь геометрию конкретного формата,
            // загрузка остаётся допустимой, а размеры сохраняются как NULL.
            return null;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        if (
            $width <= 0
            || $height <= 0
            || $width > 1000000
            || $height > 1000000
        ) {
            throw new InvalidArgumentException(
                'Размеры изображения выходят за безопасный диапазон.'
            );
        }

        $detectedMime = strtolower(
            trim((string) ($info['mime'] ?? ''))
        );
        if (
            $detectedMime !== ''
            && $detectedMime !== $mimeType
        ) {
            throw new RuntimeException(
                'MIME изображения не совпал с ранее проверенным типом blob.'
            );
        }

        return new MediaImageMetadata(
            $width,
            $height,
        );
    }
}
