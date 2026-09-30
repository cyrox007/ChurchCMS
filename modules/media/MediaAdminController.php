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

final class MediaAdminController
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

        $assets = MediaRepository::fromDatabase()->adminList(
            $access->visibleOwnerPublicIds($userId),
        );

        AdminShell::page(
            $request,
            'admin.media.index',
            [
                'title' => 'Медиатека',
                'assets' => $assets,
                'organizationUnits' => $owners,
                'defaultOwnerPublicId' =>
                    $access->defaultOwnerPublicId($userId),
                'mediaStatus' => self::status($request),
            ],
            'media',
        );
    }

    public function upload(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'media.manage',
        );

        $userId = self::requiredUserId($request);
        $ownerPublicId = trim((string) $request->post(
            'owner_organization_public_id',
            '',
        ));
        $owner = MediaOrganizationAccessService::fromDatabase()
            ->assignableOwner(
                $userId,
                $ownerPublicId,
            );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        $file = $request->files(
            'media_file',
            [],
        );

        if (!is_array($file)) {
            Response::redirectLocal(
                '/admin/media?status=invalid',
            );
        }

        try {
            $publicId = MediaUploadService::fromConfig()
                ->importUploadedFile(
                    file: $file,
                    ownerOrganizationPublicId:
                        $owner->publicId,
                    title: trim((string) $request->post(
                        'title',
                        '',
                    )),
                    altText: trim((string) $request->post(
                        'alt_text',
                        '',
                    )),
                );

            AuditLog::emit(
                eventType: 'media.uploaded',
                actorUserId: $userId,
                subjectType: 'media_asset',
                subjectId: $publicId,
                metadata: [
                    'owner_organization_public_id' =>
                        $owner->publicId,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/media?status=uploaded',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS Media upload: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/media?status=invalid',
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS Media upload failed: '
                . $error->getMessage()
            );

            AuditLog::emit(
                eventType: 'media.upload_failed',
                severity: 'error',
                actorUserId: $userId,
                subjectType: 'media_asset',
                metadata: [
                    'error_class' => $error::class,
                    'owner_organization_public_id' =>
                        $owner->publicId,
                ],
                request: $request,
            );

            Response::redirectLocal(
                '/admin/media?status=failed',
            );
        }
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'uploaded' => [
                'kind' => 'success',
                'title' => 'Файл загружен',
                'message' => 'Blob проверен по содержимому и зарегистрирован в медиатеке.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Файл не принят',
                'message' => 'Проверьте файл, его размер, тип и организацию-владельца.',
            ],
            'failed' => [
                'kind' => 'error',
                'title' => 'Загрузка не завершена',
                'message' => 'Файл не добавлен в медиатеку. Подробность записана в журнал сервера.',
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
