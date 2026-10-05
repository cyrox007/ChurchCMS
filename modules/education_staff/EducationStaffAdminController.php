<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use Throwable;

final class EducationStaffAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_staff.read');

        $userId = self::userId($request);
        $access = EducationStaffOrganizationAccessService::fromDatabase();
        $repository = EducationStaffRepository::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'education_staff.manage');
        $readOwners = $access->visibleOwnerPublicIds($userId, 'education_staff.read');
        $manageOwners = $canManage
            ? $access->visibleOwnerPublicIds($userId, 'education_staff.manage')
            : [];
        $chairs = $repository->adminChairs($readOwners);
        $teachersByChair = [];
        foreach ($chairs as $chair) {
            $teachersByChair[$chair->publicId] = $repository->teachers(
                $chair->publicId,
                false,
                $chair->siteKey,
            );
        }

        AdminShell::page(
            $request,
            'admin.education_staff',
            [
                'title' => 'Кафедры и преподаватели',
                'chairs' => $chairs,
                'teachersByChair' => $teachersByChair,
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'education_staff.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'education_staff.manage')
                    : '',
                'people' => $canManage
                    ? $repository->activePeople($manageOwners)
                    : [],
                'canManage' => $canManage,
                'educationStaffStatus' => self::status($request),
            ],
            'education_staff',
        );
    }

    public function createChair(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_staff.manage');
        $userId = self::userId($request);
        $access = EducationStaffOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'education_staff.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = EducationStaffService::fromDatabase()->createChair(
                ownerOrganizationPublicId: $owner->publicId,
                name: (string) $request->post('name', ''),
                shortName: self::nullable($request->post('short_name', null)),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, 'education_chair.created', 'education_chair', $publicId);
            Response::redirectLocal('/admin/education/staff?status=chair-created');
        } catch (Throwable $error) {
            self::log('create chair', $error);
            Response::redirectLocal('/admin/education/staff?status=invalid');
        }
    }

    public function updateChair(Request $request, string $publicId): never
    {
        $chair = self::authorizedChair($request, $publicId);
        $access = EducationStaffOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            self::userId($request),
            'education_staff.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $chair->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            EducationStaffService::fromDatabase()->updateChair(
                publicId: $chair->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                name: (string) $request->post('name', ''),
                shortName: self::nullable($request->post('short_name', null)),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $chair->siteKey,
            );
            self::audit($request, 'education_chair.updated', 'education_chair', $chair->publicId);
            Response::redirectLocal('/admin/education/staff?status=chair-updated');
        } catch (Throwable $error) {
            self::log('update chair', $error);
            Response::redirectLocal('/admin/education/staff?status=invalid');
        }
    }

    public function publishChair(Request $request, string $publicId): never
    {
        $chair = self::authorizedChair($request, $publicId);
        EducationStaffService::fromDatabase()->publishChair($chair->publicId, $chair->siteKey);
        self::audit($request, 'education_chair.published', 'education_chair', $chair->publicId);
        Response::redirectLocal('/admin/education/staff?status=chair-published');
    }

    public function unpublishChair(Request $request, string $publicId): never
    {
        $chair = self::authorizedChair($request, $publicId);
        EducationStaffService::fromDatabase()->unpublishChair($chair->publicId, $chair->siteKey);
        self::audit($request, 'education_chair.unpublished', 'education_chair', $chair->publicId);
        Response::redirectLocal('/admin/education/staff?status=chair-unpublished');
    }

    public function assignTeacher(Request $request, string $chairPublicId): never
    {
        $chair = self::authorizedChair($request, $chairPublicId);
        $personPublicId = trim((string) $request->post('person_public_id', ''));
        self::requireAssignablePerson($request, $personPublicId, $chair->siteKey);

        try {
            $publicId = EducationStaffService::fromDatabase()->assignTeacher(
                chairPublicId: $chair->publicId,
                personPublicId: $personPublicId,
                positionTitle: (string) $request->post('position_title', ''),
                academicDegree: self::nullable($request->post('academic_degree', null)),
                academicTitle: self::nullable($request->post('academic_title', null)),
                disciplines: (string) $request->post('disciplines', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $chair->siteKey,
            );
            self::audit($request, 'education_teacher.assigned', 'education_teacher_assignment', $publicId);
            Response::redirectLocal('/admin/education/staff?status=teacher-assigned');
        } catch (Throwable $error) {
            self::log('assign teacher', $error);
            Response::redirectLocal('/admin/education/staff?status=invalid');
        }
    }

    public function updateTeacher(Request $request, string $assignmentPublicId): never
    {
        $assignment = self::authorizedTeacher($request, $assignmentPublicId);
        $personPublicId = trim((string) $request->post('person_public_id', ''));
        self::requireAssignablePerson($request, $personPublicId, $assignment->siteKey);

        try {
            EducationStaffService::fromDatabase()->updateTeacher(
                assignmentPublicId: $assignment->publicId,
                personPublicId: $personPublicId,
                positionTitle: (string) $request->post('position_title', ''),
                academicDegree: self::nullable($request->post('academic_degree', null)),
                academicTitle: self::nullable($request->post('academic_title', null)),
                disciplines: (string) $request->post('disciplines', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $assignment->siteKey,
            );
            self::audit($request, 'education_teacher.updated', 'education_teacher_assignment', $assignment->publicId);
            Response::redirectLocal('/admin/education/staff?status=teacher-updated');
        } catch (Throwable $error) {
            self::log('update teacher', $error);
            Response::redirectLocal('/admin/education/staff?status=invalid');
        }
    }

    public function deactivateTeacher(Request $request, string $assignmentPublicId): never
    {
        $assignment = self::authorizedTeacher($request, $assignmentPublicId);
        EducationStaffService::fromDatabase()->deactivateTeacher($assignment->publicId, $assignment->siteKey);
        self::audit($request, 'education_teacher.deactivated', 'education_teacher_assignment', $assignment->publicId);
        Response::redirectLocal('/admin/education/staff?status=teacher-deactivated');
    }

    public function reactivateTeacher(Request $request, string $assignmentPublicId): never
    {
        $assignment = self::authorizedTeacher($request, $assignmentPublicId);
        EducationStaffService::fromDatabase()->reactivateTeacher($assignment->publicId, $assignment->siteKey);
        self::audit($request, 'education_teacher.reactivated', 'education_teacher_assignment', $assignment->publicId);
        Response::redirectLocal('/admin/education/staff?status=teacher-reactivated');
    }

    private static function authorizedChair(Request $request, string $publicId): EducationChairRecord
    {
        AdminAuthorization::requirePermission($request, 'education_staff.manage');
        $chair = EducationStaffRepository::fromDatabase()->findChair(trim($publicId));
        if ($chair === null) {
            Response::text('404 Not Found', 404);
        }
        if (!EducationStaffOrganizationAccessService::fromDatabase()->canAccessChair(
            self::userId($request),
            'education_staff.manage',
            $chair,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $chair;
    }

    private static function authorizedTeacher(Request $request, string $publicId): EducationTeacherAssignmentRecord
    {
        AdminAuthorization::requirePermission($request, 'education_staff.manage');
        $repository = EducationStaffRepository::fromDatabase();
        $assignment = $repository->findTeacher(trim($publicId));
        if ($assignment === null) {
            Response::text('404 Not Found', 404);
        }
        $chair = $repository->findChair($assignment->chairPublicId, $assignment->siteKey);
        if ($chair === null) {
            Response::text('404 Not Found', 404);
        }
        if (!EducationStaffOrganizationAccessService::fromDatabase()->canAccessChair(
            self::userId($request),
            'education_staff.manage',
            $chair,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $assignment;
    }

    private static function requireAssignablePerson(Request $request, string $personPublicId, string $siteKey): void
    {
        $userId = self::userId($request);
        $access = EducationStaffOrganizationAccessService::fromDatabase();
        $owners = $access->visibleOwnerPublicIds($userId, 'education_staff.manage', $siteKey);
        $people = EducationStaffRepository::fromDatabase()->activePeople($owners, $siteKey);
        foreach ($people as $person) {
            if ($person['public_id'] === $personPublicId) {
                return;
            }
        }
        Response::text('403 Forbidden', 403);
    }

    private static function audit(Request $request, string $eventType, string $subjectType, string $subjectId): void
    {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: $subjectType,
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
        error_log('ChurchCMS education staff ' . $operation . ': ' . $error->getMessage());
    }

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'chair-created' => ['kind' => 'success', 'title' => 'Кафедра создана', 'message' => 'Черновик кафедры сохранён.'],
            'chair-updated' => ['kind' => 'success', 'title' => 'Кафедра обновлена', 'message' => 'Изменения сохранены.'],
            'chair-published' => ['kind' => 'success', 'title' => 'Кафедра опубликована', 'message' => 'Кафедра доступна на сайте.'],
            'chair-unpublished' => ['kind' => 'success', 'title' => 'Кафедра снята с публикации', 'message' => 'Публичная карточка скрыта.'],
            'teacher-assigned' => ['kind' => 'success', 'title' => 'Преподаватель назначен', 'message' => 'Назначение добавлено на кафедру.'],
            'teacher-updated' => ['kind' => 'success', 'title' => 'Назначение обновлено', 'message' => 'Данные преподавателя сохранены.'],
            'teacher-deactivated' => ['kind' => 'success', 'title' => 'Назначение завершено', 'message' => 'Преподаватель скрыт из публичной кафедры.'],
            'teacher-reactivated' => ['kind' => 'success', 'title' => 'Назначение восстановлено', 'message' => 'Преподаватель снова доступен публично.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте данные и отсутствие дублирующего назначения.'],
            default => null,
        };
    }
}
