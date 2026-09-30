<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\HttpByteRange;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class MediaPublicController
{
    public function file(
        Request $request,
        string $mediaPublicId,
        string $sha256,
        string $variant,
    ): never {
        try {
            $file = MediaPublicFileService::fromConfig()
                ->resolve(
                    $mediaPublicId,
                    $sha256,
                    $variant,
                );
        } catch (InvalidArgumentException) {
            Response::text(
                '404 Not Found',
                404,
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS public Media: '
                . $error->getMessage()
            );

            Response::text(
                '404 Not Found',
                404,
            );
        }

        if ($file === null) {
            Response::text(
                '404 Not Found',
                404,
            );
        }

        $etag = '"' . $file->sha256 . '"';
        $ifNoneMatch = trim(
            (string) $request->header(
                'If-None-Match',
                '',
            )
        );

        if (
            $ifNoneMatch !== ''
            && hash_equals(
                $etag,
                $ifNoneMatch,
            )
        ) {
            Response::notModified(
                $etag,
                immutable: true,
            );
        }

        $rangeHeader = trim(
            (string) $request->header(
                'Range',
                '',
            )
        );
        $ifRange = trim(
            (string) $request->header(
                'If-Range',
                '',
            )
        );

        if (
            $rangeHeader !== ''
            && $ifRange !== ''
            && !hash_equals($etag, $ifRange)
        ) {
            $rangeHeader = '';
        }

        try {
            $range = HttpByteRange::fromHeader(
                $rangeHeader,
                $file->bytes,
            );
        } catch (InvalidArgumentException) {
            Response::rangeNotSatisfiable(
                $file->bytes,
                $etag,
                immutable: true,
            );
        }

        Response::rangedFile(
            path: $file->path,
            contentType: $file->mimeType,
            bytes: $file->bytes,
            etag: $etag,
            range: $range,
            sendBody: $request->method() !== 'HEAD',
            immutable: true,
        );
    }
}
