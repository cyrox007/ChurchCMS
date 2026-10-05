<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationPrograms;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class EducationProgramsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_programs.read');

        $userId = self::userId($request);
        $access = EducationProgramOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'education_programs.manage');

        AdminShell::page(
            $request,
            'admin.education_programs',
            [
                'title' => 'Образовательные программы',
                'programs' => EducationProgramRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'education_programs.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'education_programs.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'education_programs.manage')
                    : '',
                'canManage' => $canManage,
                'programStatus' => self::status($request),
            ],
            'education_programs',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_programs.manage');
        $userId = self::userId($request);
        $access = EducationProgramOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'education_programs.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = EducationProgramService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                educationLevel: (string) $request->post('education_level', ''),
                studyForm: (string) $request->post('study_form', ''),
                durationMonths: self::nullableInt($request->post('duration_months', null)),
                qualification: self::nullable($request->post('qualification', null)),
                admissionNote: (string) $request->post('admission_note', ''),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, $publicId, 'education_program.created');
            Response::redirectLocal('/admin/education/programs?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS education program create: ' . $error->getMessage());
            Response::redirectLocal('/admin/education/programs?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'education_programs.manage');
        $userId = self::userId($request);
        $record = self::record($publicId);
        $access = EducationProgramOrganizationAccessService::fromDatabase();
        if (!$access->canAccess($userId, 'education_programs.manage', $record)) {
            Response::text('403 Forbidden', 403);
        }

        $owner = $access->assignableOwner(
            $userId,
            'education_programs.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            EducationProgramService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                educationLevel: (string) $request->post('education_level', ''),
                studyForm: (string) $request->post('study_form', ''),
                durationMonths: self::nullableInt($request->post('duration_months', null)),
                qualification: self::nullable($request->post('qualification', null)),
                admissionNote: (string) $request->post('admission_note', ''),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, $record->publicId, 'education_program.updated');
            Response::redirectLocal('/admin/education/programs?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS education program update: ' . $error->getMessage());
            Response::redirectLocal('/admin/education/programs?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationProgramService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'education_program.published');
        Response::redirectLocal('/admin/education/programs?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationProgramService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'education_program.unpublished');
        Response::redirectLocal('/admin/education/programs?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): EducationProgramRecord
    {
        AdminAuthorization::requirePermission($request, 'education_programs.manage');
        $record = self::record($publicId);
        if (!EducationProgramOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'education_programs.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function record(string $publicId): EducationProgramRecord
    {
        $record = EducationProgramRepository::fromDatabase()->find(trim($publicId));
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
            subjectType: 'education_program',
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

    private static function nullableInt(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : (int) $value;
    }

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => ['kind' => 'success', 'title' => 'Программа создана', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Программа обновлена', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Программа опубликована', 'message' => 'Карточка доступна на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Программа снята с публикации', 'message' => 'Карточка снова находится в черновиках.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте заполнение полей.'],
            default => null,
        };
    }
}
