<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\SundaySchool;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class SundaySchoolsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'sunday_school.read');

        $userId = self::userId($request);
        $access = SundaySchoolOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'sunday_school.manage');

        AdminShell::page(
            $request,
            'admin.sunday_school',
            [
                'title' => 'Воскресная школа',
                'schools' => SundaySchoolRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'sunday_school.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'sunday_school.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'sunday_school.manage')
                    : '',
                'canManage' => $canManage,
                'schoolStatus' => self::status($request),
            ],
            'sunday_school',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'sunday_school.manage');
        $userId = self::userId($request);
        $access = SundaySchoolOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'sunday_school.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = SundaySchoolService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                leaderName: self::nullable($request->post('leader_name', null)),
                locationName: self::nullable($request->post('location_name', null)),
                contactEmail: self::nullable($request->post('contact_email', null)),
                contactPhone: self::nullable($request->post('contact_phone', null)),
                ageInfo: self::nullable($request->post('age_info', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, $publicId, 'sunday_school.created');
            Response::redirectLocal('/admin/sunday-school?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS Sunday school create: ' . $error->getMessage());
            Response::redirectLocal('/admin/sunday-school?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'sunday_school.manage');
        $userId = self::userId($request);
        $record = self::record($publicId);
        $access = SundaySchoolOrganizationAccessService::fromDatabase();
        if (!$access->canAccess($userId, 'sunday_school.manage', $record)) {
            Response::text('403 Forbidden', 403);
        }
        $owner = $access->assignableOwner(
            $userId,
            'sunday_school.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            SundaySchoolService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                leaderName: self::nullable($request->post('leader_name', null)),
                locationName: self::nullable($request->post('location_name', null)),
                contactEmail: self::nullable($request->post('contact_email', null)),
                contactPhone: self::nullable($request->post('contact_phone', null)),
                ageInfo: self::nullable($request->post('age_info', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, $record->publicId, 'sunday_school.updated');
            Response::redirectLocal('/admin/sunday-school?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS Sunday school update: ' . $error->getMessage());
            Response::redirectLocal('/admin/sunday-school?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        SundaySchoolService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'sunday_school.published');
        Response::redirectLocal('/admin/sunday-school?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        SundaySchoolService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'sunday_school.unpublished');
        Response::redirectLocal('/admin/sunday-school?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): SundaySchoolRecord
    {
        AdminAuthorization::requirePermission($request, 'sunday_school.manage');
        $record = self::record($publicId);
        if (!SundaySchoolOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'sunday_school.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function record(string $publicId): SundaySchoolRecord
    {
        $record = SundaySchoolRepository::fromDatabase()->find(trim($publicId));
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
            subjectType: 'sunday_school',
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
            'created' => ['kind' => 'success', 'title' => 'Школа создана', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Школа обновлена', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Школа опубликована', 'message' => 'Карточка доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Школа снята с публикации', 'message' => 'Карточка снова находится в черновиках.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте заполнение полей.'],
            default => null,
        };
    }
}
