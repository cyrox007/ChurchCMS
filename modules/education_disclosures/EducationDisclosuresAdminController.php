<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationDisclosures;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use Throwable;

final class EducationDisclosuresAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_disclosures.read');
        $userId = self::userId($request);
        $access = EducationDisclosureOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'education_disclosures.manage');

        AdminShell::page(
            $request,
            'admin.education_disclosures',
            [
                'title' => 'Обязательные сведения',
                'disclosures' => EducationDisclosureRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'education_disclosures.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'education_disclosures.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'education_disclosures.manage')
                    : '',
                'canManage' => $canManage,
                'educationDisclosuresStatus' => self::status($request),
            ],
            'education_disclosures',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'education_disclosures.manage');
        $owner = EducationDisclosureOrganizationAccessService::fromDatabase()->assignableOwner(
            self::userId($request),
            'education_disclosures.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = EducationDisclosureService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                sectionKey: (string) $request->post('section_key', ''),
                title: (string) $request->post('title', ''),
                summary: (string) $request->post('summary', ''),
                bodyInput: (string) $request->post('body', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, 'education_disclosure.created', $publicId);
            Response::redirectLocal('/admin/education/disclosures?status=created');
        } catch (Throwable $error) {
            self::log('create', $error);
            Response::redirectLocal('/admin/education/disclosures?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        $owner = EducationDisclosureOrganizationAccessService::fromDatabase()->assignableOwner(
            self::userId($request),
            'education_disclosures.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            EducationDisclosureService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                sectionKey: (string) $request->post('section_key', ''),
                title: (string) $request->post('title', ''),
                summary: (string) $request->post('summary', ''),
                bodyInput: (string) $request->post('body', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, 'education_disclosure.updated', $record->publicId);
            Response::redirectLocal('/admin/education/disclosures?status=updated');
        } catch (Throwable $error) {
            self::log('update', $error);
            Response::redirectLocal('/admin/education/disclosures?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationDisclosureService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, 'education_disclosure.published', $record->publicId);
        Response::redirectLocal('/admin/education/disclosures?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        EducationDisclosureService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, 'education_disclosure.unpublished', $record->publicId);
        Response::redirectLocal('/admin/education/disclosures?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): EducationDisclosureRecord
    {
        AdminAuthorization::requirePermission($request, 'education_disclosures.manage');
        $record = EducationDisclosureRepository::fromDatabase()->find(trim($publicId));
        if ($record === null) {
            Response::text('404 Not Found', 404);
        }
        if (!EducationDisclosureOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'education_disclosures.manage',
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
            subjectType: 'education_disclosure',
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

    private static function log(string $operation, Throwable $error): void
    {
        error_log('ChurchCMS education disclosures ' . $operation . ': ' . $error->getMessage());
    }

    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'created' => ['kind' => 'success', 'title' => 'Раздел создан', 'message' => 'Черновик обязательных сведений сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Раздел обновлён', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Раздел опубликован', 'message' => 'Сведения доступны на сайте.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Раздел снят с публикации', 'message' => 'Публичная карточка скрыта.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте ключ, название и содержимое раздела.'],
            default => null,
        };
    }
}
