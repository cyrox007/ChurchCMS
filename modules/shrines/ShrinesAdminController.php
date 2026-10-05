<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Shrines;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class ShrinesAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'shrines.read');
        $userId = self::userId($request);
        $access = ShrineOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'shrines.manage');

        AdminShell::page(
            $request,
            'admin.shrines',
            [
                'title' => 'Святыни',
                'shrines' => ShrineRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'shrines.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'shrines.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'shrines.manage')
                    : '',
                'canManage' => $canManage,
                'shrineStatus' => self::status($request),
            ],
            'shrines',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'shrines.manage');
        $userId = self::userId($request);
        $access = ShrineOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'shrines.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = ShrineService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                shrineType: (string) $request->post('shrine_type', 'other'),
                title: (string) $request->post('title', ''),
                subtitle: self::nullable($request->post('subtitle', null)),
                locationName: self::nullable($request->post('location_name', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, $publicId, 'shrine.created');
            Response::redirectLocal('/admin/shrines?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS shrine create: ' . $error->getMessage());
            Response::redirectLocal('/admin/shrines?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'shrines.manage');
        $userId = self::userId($request);
        $record = self::record($publicId);
        $access = ShrineOrganizationAccessService::fromDatabase();
        if (!$access->canAccess($userId, 'shrines.manage', $record)) {
            Response::text('403 Forbidden', 403);
        }
        $owner = $access->assignableOwner(
            $userId,
            'shrines.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            ShrineService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                shrineType: (string) $request->post('shrine_type', 'other'),
                title: (string) $request->post('title', ''),
                subtitle: self::nullable($request->post('subtitle', null)),
                locationName: self::nullable($request->post('location_name', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, $record->publicId, 'shrine.updated');
            Response::redirectLocal('/admin/shrines?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS shrine update: ' . $error->getMessage());
            Response::redirectLocal('/admin/shrines?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        ShrineService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'shrine.published');
        Response::redirectLocal('/admin/shrines?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        ShrineService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'shrine.unpublished');
        Response::redirectLocal('/admin/shrines?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): ShrineRecord
    {
        AdminAuthorization::requirePermission($request, 'shrines.manage');
        $record = self::record($publicId);
        if (!ShrineOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'shrines.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function record(string $publicId): ShrineRecord
    {
        $record = ShrineRepository::fromDatabase()->find(trim($publicId));
        if ($record === null) {
            Response::text('404 Not Found', 404);
        }
        return $record;
    }

    private static function audit(Request $request, string $publicId, string $eventType): void
    {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: 'shrine',
            subjectId: $publicId,
            request: $request,
        );
    }

    private static function userId(Request $request): int
    {
        $user = $request->attribute('admin.user');
        $id = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if ($id <= 0) {
            Response::text('403 Forbidden', 403);
        }
        return $id;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => ['kind' => 'success', 'title' => 'Святыня создана', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Святыня обновлена', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Святыня опубликована', 'message' => 'Карточка доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Святыня снята с публикации', 'message' => 'Карточка снова находится в черновиках.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте заполнение полей.'],
            default => null,
        };
    }
}
