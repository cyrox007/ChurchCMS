<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class MediaGalleryApiController
{
    public function index(Request $request): never
    {
        $limit = max(
            1,
            min(50, (int) $request->get('limit', 24)),
        );

        $items = MediaGalleryPublicService::fromConfig()
            ->index(
                'default',
                $limit,
            );

        ApiResponse::success(
            $items,
            [
                'pagination' => [
                    'limit' => $limit,
                    'count' => count($items),
                ],
            ],
            cacheSeconds: 60,
        );
    }

    public function show(
        Request $request,
        string $publicId,
    ): never {
        $gallery = MediaGalleryPublicService::fromConfig()
            ->detail(
                $publicId,
                'default',
            );

        if ($gallery === null) {
            ApiResponse::error(
                'gallery_not_found',
                'Галерея не найдена.',
                404,
            );
        }

        ApiResponse::success(
            $gallery,
            cacheSeconds: 60,
        );
    }
}
