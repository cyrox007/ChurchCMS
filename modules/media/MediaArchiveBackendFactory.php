<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\Config;
use RuntimeException;

final class MediaArchiveBackendFactory
{
    public static function fromConfig(): ?MediaArchiveBackend
    {
        $driver = strtolower(trim((string) Config::get(
            'media.archive.driver',
            'disabled',
        )));

        if ($driver === '' || $driver === 'disabled') {
            return null;
        }

        if ($driver !== 's3') {
            throw new RuntimeException(
                'Неподдерживаемый Media archive driver: ' . $driver
            );
        }

        return new MediaS3ArchiveBackend(
            endpoint: trim((string) Config::get('media.archive.endpoint', '')),
            bucket: trim((string) Config::get('media.archive.bucket', '')),
            region: trim((string) Config::get('media.archive.region', 'us-east-1')),
            accessKey: trim((string) Config::get('media.archive.access_key', '')),
            secretKey: (string) Config::get('media.archive.secret_key', ''),
            prefix: trim((string) Config::get('media.archive.prefix', 'churchcms-media')),
        );
    }
}
