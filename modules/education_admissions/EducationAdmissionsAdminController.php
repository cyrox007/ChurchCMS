<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Modules\EducationPrograms\EducationProgramRepository;
use InvalidArgumentException;

final class EducationAdmissionsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_admissions.read');
        $userId = self::userId($request);
        $access = EducationAdmissionOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'education_admissions.manage');
        $readOwners = $access->visibleOwnerPublicIds($userId, 'education_admissions.read');
        $manageOwners = $canManage
            ? $access->visibleOwnerPublicIds($userId, 'education_admissions.manage')
            : [];

        AdminShell::page(
            $request,
            'admin.education_admissions',
            [
                'title' => 'Приёмная кампания',
                'admissions' => EducationAdmissionRepository::fromDatabase()->adminList($readOwners),
                'programs' => $canManage
                    ? EducationProgramRepository::fromDatabase()->adminList($manageOwners)
                    : [],
                'canManage' => $canManage,
                'educationAdmissionsStatus' => self::status($request),
            ],
            'education_admissions',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_admissions.manage');
        $programPublicId = trim((string) $request->post('program_public_id', ''));
        self::requireManageableProgram($request, $programPublicId);

        try {
            $publicId = EducationAdmissionService::fromDatabase()->createDraft(
                programPublicId: $programPublicId,
                title: (string) $request->post('title', ''),
                academicYear: (string) $request->post('academic_year', ''),
                startsOn: self::nullable($request->post('starts_on', null)),
                endsOn: self::nullable($request->post('ends_on', null)),
                budgetSeats: (int) $request->post('budget_seats', 0),
                paidSeats: (int) $request->post('paid_seats', 0),
                tuitionNote: (string) $request->post('tuition_note', ''),
                requirements: (string) $request->post('requirements', ''),
                entranceTests: (string) $request->post('entrance_tests', ''),
                contactNote: (string) $request->post('contact_note', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, 'education_admission.created', $publicId);
            Response::redirectLocal('/admin/education/admissions?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS education admission create: ' . $error->getMessage());
            Response::redirectLocal('/admin/education/admissions?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        $programPublicId = trim((string) $request->post('program_public_id', ''));
        self::requireManageableProgram($request, $programPublicId, $record->siteKey);

        try {
            EducationAdmissionService::fromDatabase()->update(
                publicId: $record->publicId,
                programPublicId: $programPublicId,
                title: (string) $request->post('title', ''),
                academicYear: (string) $request->post('academic_year', ''),
                startsOn: self::nullable($request->post('starts_on', null)),
                endsOn: self::nullable($request->post('ends_on', null)),
                budgetSeats: (int) $request->post('budget_seats', 0),
                paidSeats: (int) $request->post('paid_seats', 0),
                tuitionNote: (string) $request->post('tuition_note', ''),
                requirements: (string) $request->post('requirements', ''),
                entranceTests: (string) $request->post('entrance_tests', ''),
                contactNote: (string) $request->post('contact_note', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, 'education_admission.updated', $record->publicId);
            Response::redirectLocal('/admin/education/admissions?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS education admission update: ' . $error->getMessage());
            Response::redirectLocal('/admin/education/admissions?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationAdmissionService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, 'education_admission.published', $record->publicId);
        Response::redirectLocal('/admin/education/admissions?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationAdmissionService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, 'education_admission.unpublished', $record->publicId);
        Response::redirectLocal('/admin/education/admissions?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): EducationAdmissionRecord
    {
        AdminAuthorization::requirePermission($request, 'education_admissions.manage');
        $record = EducationAdmissionRepository::fromDatabase()->find(trim($publicId));
        if ($record === null) {
            Response::text('404 Not Found', 404);
        }
        if (!EducationAdmissionOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'education_admissions.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function requireManageableProgram(Request $request, string $programPublicId, string $siteKey = 'default'): void
    {
        $owners = EducationAdmissionOrganizationAccessService::fromDatabase()->visibleOwnerPublicIds(
            self::userId($request),
            'education_admissions.manage',
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
            subjectType: 'education_admission',
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

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => ['kind' => 'success', 'title' => 'Кампания создана', 'message' => 'Черновик приёмной кампании сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Кампания обновлена', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Кампания опубликована', 'message' => 'Информация доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Кампания снята с публикации', 'message' => 'Публичная карточка скрыта.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте даты, места и обязательные поля.'],
            default => null,
        };
    }
}
