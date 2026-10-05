<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Modules\EducationPrograms\EducationProgramRepository;
use InvalidArgumentException;

final class EducationSchedulesAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_schedules.read');
        $userId = self::userId($request);
        $access = EducationScheduleOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'education_schedules.manage');
        $readOwners = $access->visibleOwnerPublicIds($userId, 'education_schedules.read');
        $manageOwners = $canManage
            ? $access->visibleOwnerPublicIds($userId, 'education_schedules.manage')
            : [];

        AdminShell::page(
            $request,
            'admin.education_schedules',
            [
                'title' => 'Расписание обучения',
                'schedules' => EducationScheduleRepository::fromDatabase()->adminList($readOwners),
                'programs' => $canManage
                    ? EducationProgramRepository::fromDatabase()->adminList($manageOwners)
                    : [],
                'canManage' => $canManage,
                'educationSchedulesStatus' => self::status($request),
            ],
            'education_schedules',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_schedules.manage');
        $programPublicId = trim((string) $request->post('program_public_id', ''));
        self::requireManageableProgram($request, $programPublicId);
        try {
            $publicId = EducationScheduleService::fromDatabase()->createDraft(
                programPublicId: $programPublicId,
                title: (string) $request->post('title', ''),
                startsAtLocal: (string) $request->post('starts_at_local', ''),
                endsAtLocal: (string) $request->post('ends_at_local', ''),
                timezone: (string) $request->post('timezone', ''),
                location: (string) $request->post('location', ''),
                note: (string) $request->post('note', ''),
            );
            self::audit($request, 'education_schedule.created', $publicId);
            Response::redirectLocal('/admin/education/schedules?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS education schedule create: ' . $error->getMessage());
            Response::redirectLocal('/admin/education/schedules?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        $programPublicId = trim((string) $request->post('program_public_id', ''));
        self::requireManageableProgram($request, $programPublicId, $record->siteKey);
        try {
            EducationScheduleService::fromDatabase()->update(
                publicId: $record->publicId,
                programPublicId: $programPublicId,
                title: (string) $request->post('title', ''),
                startsAtLocal: (string) $request->post('starts_at_local', ''),
                endsAtLocal: (string) $request->post('ends_at_local', ''),
                timezone: (string) $request->post('timezone', ''),
                location: (string) $request->post('location', ''),
                note: (string) $request->post('note', ''),
                siteKey: $record->siteKey,
            );
            self::audit($request, 'education_schedule.updated', $record->publicId);
            Response::redirectLocal('/admin/education/schedules?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS education schedule update: ' . $error->getMessage());
            Response::redirectLocal('/admin/education/schedules?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationScheduleService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, 'education_schedule.published', $record->publicId);
        Response::redirectLocal('/admin/education/schedules?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationScheduleService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, 'education_schedule.unpublished', $record->publicId);
        Response::redirectLocal('/admin/education/schedules?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): EducationScheduleRecord
    {
        AdminAuthorization::requirePermission($request, 'education_schedules.manage');
        $record = EducationScheduleRepository::fromDatabase()->find(trim($publicId));
        if ($record === null) {
            Response::text('404 Not Found', 404);
        }
        if (!EducationScheduleOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'education_schedules.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function requireManageableProgram(Request $request, string $programPublicId, string $siteKey = 'default'): void
    {
        $owners = EducationScheduleOrganizationAccessService::fromDatabase()->visibleOwnerPublicIds(
            self::userId($request),
            'education_schedules.manage',
            $siteKey,
        );
        foreach (EducationProgramRepository::fromDatabase()->adminList($owners, $siteKey) as $program) {
            if ($program->publicId === $programPublicId) {
                return;
            }
        }
        Response::text('403 Forbidden', 403);
    }

    private static function audit(Request $request, string $eventType, string $subjectId): void
    {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: 'education_schedule',
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

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => ['kind' => 'success', 'title' => 'Занятие добавлено', 'message' => 'Черновик расписания сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Занятие обновлено', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Занятие опубликовано', 'message' => 'Запись доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Занятие снято с публикации', 'message' => 'Запись скрыта с сайта.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте время, часовой пояс и обязательные поля.'],
            default => null,
        };
    }
}
