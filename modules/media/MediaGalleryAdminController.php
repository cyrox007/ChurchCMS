<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class MediaGalleryAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'media.manage',
        );

        $userId = self::requiredUserId($request);
        $access = MediaOrganizationAccessService::fromDatabase();
        $owners = $access->availableOwners($userId);

        if ($owners === []) {
            Response::text('403 Forbidden', 403);
        }

        $visibleOwnerIds = $access->visibleOwnerPublicIds(
            $userId,
        );
        $repository = MediaGalleryRepository::fromDatabase();
        $service = MediaGalleryService::fromDatabase();

        $galleries = [];
        foreach (
            $repository->adminList($visibleOwnerIds)
            as $gallery
        ) {
            $galleries[] = [
                'gallery' => $gallery,
                'items' => $service->items(
                    $gallery->publicId,
                    $gallery->siteKey,
                ),
            ];
        }

        $images = array_values(array_filter(
            MediaRepository::fromDatabase()->adminList(
                $visibleOwnerIds,
            ),
            static fn(MediaAsset $asset): bool =>
                $asset->status !== 'archived'
                && $asset->mediaType === 'image',
        ));

        AdminShell::page(
            $request,
            'admin.media.galleries',
            [
                'title' => 'Галереи',
                'galleries' => $galleries,
                'images' => $images,
                'organizationUnits' => $owners,
                'defaultOwnerPublicId' =>
                    $access->defaultOwnerPublicId($userId),
                'galleryStatus' => self::status($request),
            ],
            'media-galleries',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'media.manage',
        );

        $userId = self::requiredUserId($request);
        $owner = MediaOrganizationAccessService::fromDatabase()
            ->assignableOwner(
                $userId,
                trim((string) $request->post(
                    'owner_organization_public_id',
                    '',
                )),
            );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = MediaGalleryService::fromDatabase()
                ->createDraft(
                    title: (string) $request->post(
                        'title',
                        '',
                    ),
                    ownerOrganizationPublicId:
                        $owner->publicId,
                    description: (string) $request->post(
                        'description',
                        '',
                    ),
                );

            AuditLog::emit(
                eventType: 'media.gallery.created',
                actorUserId: $userId,
                subjectType: 'media_gallery',
                subjectId: $publicId,
                metadata: [
                    'owner_organization_public_id' =>
                        $owner->publicId,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/media/galleries?status=created',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS gallery create: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/media/galleries?status=invalid',
            );
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'media.manage',
        );

        $userId = self::requiredUserId($request);
        $gallery = self::requiredVisibleGallery(
            $userId,
            $publicId,
        );

        try {
            $service = MediaGalleryService::fromDatabase();

            $service->updateDetails(
                $gallery->publicId,
                (string) $request->post('title', ''),
                (string) $request->post(
                    'description',
                    '',
                ),
                $gallery->siteKey,
            );

            $service->replaceItems(
                $gallery->publicId,
                self::orderedImageIds(
                    $request,
                    $userId,
                ),
                $gallery->siteKey,
            );

            AuditLog::emit(
                eventType: 'media.gallery.updated',
                actorUserId: $userId,
                subjectType: 'media_gallery',
                subjectId: $gallery->publicId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/media/galleries?status=updated',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS gallery update: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/media/galleries?status=invalid',
            );
        }
    }

    public function publish(
        Request $request,
        string $publicId,
    ): never {
        $gallery = self::authorizedGallery(
            $request,
            $publicId,
        );

        try {
            $service = MediaGalleryService::fromDatabase();
            $service->publish(
                $gallery->publicId,
                $gallery->siteKey,
            );
            $service->setVisibility(
                $gallery->publicId,
                'public',
                $gallery->siteKey,
            );

            self::auditState(
                $request,
                $gallery,
                'media.gallery.published',
            );

            Response::redirectLocal(
                '/admin/media/galleries?status=published',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS gallery publish: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/media/galleries?status=invalid',
            );
        }
    }

    public function withdraw(
        Request $request,
        string $publicId,
    ): never {
        $gallery = self::authorizedGallery(
            $request,
            $publicId,
        );

        MediaGalleryService::fromDatabase()->withdraw(
            $gallery->publicId,
            $gallery->siteKey,
        );

        self::auditState(
            $request,
            $gallery,
            'media.gallery.withdrawn',
        );

        Response::redirectLocal(
            '/admin/media/galleries?status=withdrawn',
        );
    }

    public function archive(
        Request $request,
        string $publicId,
    ): never {
        $gallery = self::authorizedGallery(
            $request,
            $publicId,
        );

        MediaGalleryService::fromDatabase()->archive(
            $gallery->publicId,
            $gallery->siteKey,
        );

        self::auditState(
            $request,
            $gallery,
            'media.gallery.archived',
        );

        Response::redirectLocal(
            '/admin/media/galleries?status=archived',
        );
    }

    private static function authorizedGallery(
        Request $request,
        string $publicId,
    ): MediaGallery {
        AdminAuthorization::requirePermission(
            $request,
            'media.manage',
        );

        return self::requiredVisibleGallery(
            self::requiredUserId($request),
            $publicId,
        );
    }

    private static function requiredVisibleGallery(
        int $userId,
        string $publicId,
    ): MediaGallery {
        $gallery = MediaGalleryRepository::fromDatabase()
            ->findByPublicId(trim($publicId));

        if ($gallery === null) {
            Response::text('404 Not Found', 404);
        }

        $visible = MediaOrganizationAccessService::fromDatabase()
            ->visibleOwnerPublicIds($userId);

        if (!in_array(
            $gallery->ownerOrganizationPublicId,
            $visible,
            true,
        )) {
            Response::text('403 Forbidden', 403);
        }

        return $gallery;
    }

    /**
     * @return list<string>
     */
    private static function orderedImageIds(
        Request $request,
        int $userId,
    ): array {
        $selected = $request->post(
            'gallery_items',
            [],
        );
        $orders = $request->post(
            'gallery_order',
            [],
        );

        if (!is_array($selected)) {
            throw new InvalidArgumentException(
                'Список изображений заполнен некорректно.'
            );
        }

        $visibleOwners =
            MediaOrganizationAccessService::fromDatabase()
                ->visibleOwnerPublicIds($userId);
        $allowed = [];

        foreach (
            MediaRepository::fromDatabase()->adminList(
                $visibleOwners,
            ) as $asset
        ) {
            if (
                $asset->status !== 'archived'
                && $asset->mediaType === 'image'
            ) {
                $allowed[$asset->publicId] = true;
            }
        }

        $ranked = [];
        foreach ($selected as $index => $publicId) {
            if (!is_string($publicId)) {
                continue;
            }

            $publicId = trim($publicId);
            if (
                $publicId === ''
                || !isset($allowed[$publicId])
            ) {
                throw new InvalidArgumentException(
                    'Выбрано недоступное изображение.'
                );
            }

            $order = is_array($orders)
                ? (int) ($orders[$publicId] ?? 0)
                : 0;

            if ($order <= 0) {
                $order = 100000 + (int) $index;
            }

            $ranked[] = [
                'id' => $publicId,
                'order' => $order,
                'index' => (int) $index,
            ];
        }

        usort(
            $ranked,
            static fn(array $left, array $right): int =>
                [$left['order'], $left['index']]
                <=> [$right['order'], $right['index']],
        );

        return array_map(
            static fn(array $item): string =>
                (string) $item['id'],
            $ranked,
        );
    }

    private static function auditState(
        Request $request,
        MediaGallery $gallery,
        string $eventType,
    ): void {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::requiredUserId($request),
            subjectType: 'media_gallery',
            subjectId: $gallery->publicId,
            request: $request,
        );
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => [
                'kind' => 'success',
                'title' => 'Галерея создана',
                'message' => 'Черновик галереи готов к наполнению.',
            ],
            'updated' => [
                'kind' => 'success',
                'title' => 'Галерея сохранена',
                'message' => 'Карточка, состав и порядок изображений обновлены.',
            ],
            'published' => [
                'kind' => 'success',
                'title' => 'Галерея опубликована',
                'message' => 'Галерея переведена в публичное состояние.',
            ],
            'withdrawn' => [
                'kind' => 'success',
                'title' => 'Галерея снята с публикации',
                'message' => 'Карточка снова находится в приватном черновике.',
            ],
            'archived' => [
                'kind' => 'success',
                'title' => 'Галерея архивирована',
                'message' => 'Ссылки на изображения сняты.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Изменения не сохранены',
                'message' => 'Проверьте карточку, изображения и порядок.',
            ],
            default => null,
        };
    }

    private static function requiredUserId(
        Request $request,
    ): int {
        $user = $request->attribute('admin.user');
        $userId = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

        if ($userId <= 0) {
            Response::text('403 Forbidden', 403);
        }

        return $userId;
    }
}
