<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationScience;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use Throwable;

final class EducationScienceAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_science.read');
        $userId = self::userId($request);
        $access = EducationScienceOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'education_science.manage');

        AdminShell::page(
            $request,
            'admin.education_science',
            [
                'title' => 'Научная деятельность',
                'activities' => EducationScienceRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'education_science.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'education_science.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'education_science.manage')
                    : '',
                'canManage' => $canManage,
                'educationScienceStatus' => self::status($request),
            ],
            'education_science',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_science.manage');
        $owner = EducationScienceOrganizationAccessService::fromDatabase()->assignableOwner(
            self::userId($request),
            'education_science.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }
        try {
            $publicId = EducationScienceService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                activityType: (string) $request->post('activity_type', ''),
                title: (string) $request->post('title', ''),
                startsOn: self::nullable($request->post('starts_on', null)),
                endsOn: self::nullable($request->post('ends_on', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                externalUrl: self::nullable($request->post('external_url', null)),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, 'education_science.created', $publicId);
            Response::redirectLocal('/admin/education/science?status=created');
        } catch (Throwable $error) {
            self::log('create', $error);
            Response::redirectLocal('/admin/education/science?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        $owner = EducationScienceOrganizationAccessService::fromDatabase()->assignableOwner(
            self::userId($request),
            'education_science.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }
        try {
            EducationScienceService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                activityType: (string) $request->post('activity_type', ''),
                title: (string) $request->post('title', ''),
                startsOn: self::nullable($request->post('starts_on', null)),
                endsOn: self::nullable($request->post('ends_on', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                externalUrl: self::nullable($request->post('external_url', null)),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, 'education_science.updated', $record->publicId);
            Response::redirectLocal('/admin/education/science?status=updated');
        } catch (Throwable $error) {
            self::log('update', $error);
            Response::redirectLocal('/admin/education/science?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationScienceService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, 'education_science.published', $record->publicId);
        Response::redirectLocal('/admin/education/science?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationScienceService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, 'education_science.unpublished', $record->publicId);
        Response::redirectLocal('/admin/education/science?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): EducationScienceRecord
    {
        AdminAuthorization::requirePermission($request, 'education_science.manage');
        $record = EducationScienceRepository::fromDatabase()->find(trim($publicId));
        if ($record === null) {
            Response::text('404 Not Found', 404);
        }
        if (!EducationScienceOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'education_science.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function audit(Request $request, string $eventType, string $subjectId): void
    {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: 'education_science_activity',
            subjectId: $subjectId,
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

    private static function log(string $operation, Throwable $error): void
    {
        error_log('ChurchCMS education science ' . $operation . ': ' . $error->getMessage());
    }

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => ['kind' => 'success', 'title' => 'Научная активность создана', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Научная активность обновлена', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Материал опубликован', 'message' => 'Запись доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Материал снят с публикации', 'message' => 'Запись скрыта с сайта.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте тип, даты, ссылку и обязательные поля.'],
            default => null,
        };
    }
}
