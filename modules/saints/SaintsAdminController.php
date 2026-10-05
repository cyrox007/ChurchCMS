<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Saints;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class SaintsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'saints.read');
        $userId = self::userId($request);
        $access = SaintOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'saints.manage');

        AdminShell::page(
            $request,
            'admin.saints',
            [
                'title' => 'Святые',
                'saints' => SaintRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'saints.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'saints.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'saints.manage')
                    : '',
                'canManage' => $canManage,
                'saintStatus' => self::status($request),
            ],
            'saints',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'saints.manage');
        $userId = self::userId($request);
        $access = SaintOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'saints.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = SaintService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                displayName: (string) $request->post('display_name', ''),
                saintRank: self::nullable($request->post('saint_rank', null)),
                secularName: self::nullable($request->post('secular_name', null)),
                commemorationText: self::nullable($request->post('commemoration_text', null)),
                summary: (string) $request->post('summary', ''),
                biographyInput: (string) $request->post('biography', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, $publicId, 'saint.created');
            Response::redirectLocal('/admin/saints?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS saint create: ' . $error->getMessage());
            Response::redirectLocal('/admin/saints?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'saints.manage');
        $userId = self::userId($request);
        $record = self::record($publicId);
        $access = SaintOrganizationAccessService::fromDatabase();
        if (!$access->canAccess($userId, 'saints.manage', $record)) {
            Response::text('403 Forbidden', 403);
        }
        $owner = $access->assignableOwner(
            $userId,
            'saints.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            SaintService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                displayName: (string) $request->post('display_name', ''),
                saintRank: self::nullable($request->post('saint_rank', null)),
                secularName: self::nullable($request->post('secular_name', null)),
                commemorationText: self::nullable($request->post('commemoration_text', null)),
                summary: (string) $request->post('summary', ''),
                biographyInput: (string) $request->post('biography', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, $record->publicId, 'saint.updated');
            Response::redirectLocal('/admin/saints?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS saint update: ' . $error->getMessage());
            Response::redirectLocal('/admin/saints?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        SaintService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'saint.published');
        Response::redirectLocal('/admin/saints?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        SaintService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'saint.unpublished');
        Response::redirectLocal('/admin/saints?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): SaintRecord
    {
        AdminAuthorization::requirePermission($request, 'saints.manage');
        $record = self::record($publicId);
        if (!SaintOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'saints.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function record(string $publicId): SaintRecord
    {
        $record = SaintRepository::fromDatabase()->find(trim($publicId));
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
            subjectType: 'saint',
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
            'created' => ['kind' => 'success', 'title' => 'Карточка создана', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Карточка обновлена', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Карточка опубликована', 'message' => 'Материал доступен на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Карточка снята с публикации', 'message' => 'Материал снова находится в черновиках.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте заполнение полей.'],
            default => null,
        };
    }
}
