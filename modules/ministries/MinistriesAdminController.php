<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Ministries;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class MinistriesAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'ministries.read');

        $userId = self::userId($request);
        $access = MinistryOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'ministries.manage');

        AdminShell::page(
            $request,
            'admin.ministries',
            [
                'title' => 'Служения и отделы',
                'ministries' => MinistryRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'ministries.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'ministries.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'ministries.manage')
                    : '',
                'canManage' => $canManage,
                'ministryStatus' => self::status($request),
            ],
            'ministries',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'ministries.manage');
        $userId = self::userId($request);
        $access = MinistryOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'ministries.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = MinistryService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                shortTitle: self::nullable($request->post('short_title', null)),
                leaderName: self::nullable($request->post('leader_name', null)),
                contactEmail: self::nullable($request->post('contact_email', null)),
                contactPhone: self::nullable($request->post('contact_phone', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, $publicId, 'ministry.created');
            Response::redirectLocal('/admin/ministries?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS ministry create: ' . $error->getMessage());
            Response::redirectLocal('/admin/ministries?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'ministries.manage');
        $userId = self::userId($request);
        $record = self::record($publicId);
        $access = MinistryOrganizationAccessService::fromDatabase();
        if (!$access->canAccess($userId, 'ministries.manage', $record)) {
            Response::text('403 Forbidden', 403);
        }
        $owner = $access->assignableOwner(
            $userId,
            'ministries.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            MinistryService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                shortTitle: self::nullable($request->post('short_title', null)),
                leaderName: self::nullable($request->post('leader_name', null)),
                contactEmail: self::nullable($request->post('contact_email', null)),
                contactPhone: self::nullable($request->post('contact_phone', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, $record->publicId, 'ministry.updated');
            Response::redirectLocal('/admin/ministries?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS ministry update: ' . $error->getMessage());
            Response::redirectLocal('/admin/ministries?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        MinistryService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'ministry.published');
        Response::redirectLocal('/admin/ministries?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        MinistryService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'ministry.unpublished');
        Response::redirectLocal('/admin/ministries?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): MinistryRecord
    {
        AdminAuthorization::requirePermission($request, 'ministries.manage');
        $record = self::record($publicId);
        if (!MinistryOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'ministries.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function record(string $publicId): MinistryRecord
    {
        $record = MinistryRepository::fromDatabase()->find(trim($publicId));
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
            subjectType: 'ministry',
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
            'created' => ['kind' => 'success', 'title' => 'Служение создано', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Служение обновлено', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Служение опубликовано', 'message' => 'Карточка доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Служение снято с публикации', 'message' => 'Карточка снова находится в черновиках.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте заполнение полей.'],
            default => null,
        };
    }
}
