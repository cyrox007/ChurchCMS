<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Library;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class LibraryAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'library.read');
        $userId = self::userId($request);
        $access = LibraryOrganizationAccessService::fromDatabase();
        $canManage = AdminAuthorization::can($request, 'library.manage');

        AdminShell::page(
            $request,
            'admin.library',
            [
                'title' => 'Библиотека',
                'items' => LibraryItemRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds($userId, 'library.read'),
                ),
                'organizationUnits' => $canManage
                    ? $access->availableOwners($userId, 'library.manage')
                    : [],
                'defaultOwnerPublicId' => $canManage
                    ? $access->defaultOwnerPublicId($userId, 'library.manage')
                    : '',
                'canManage' => $canManage,
                'libraryStatus' => self::status($request),
            ],
            'library',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'library.manage');
        $owner = LibraryOrganizationAccessService::fromDatabase()->assignableOwner(
            self::userId($request),
            'library.manage',
            (string) $request->post('owner_organization_public_id', ''),
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = LibraryItemService::fromDatabase()->createDraft(
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                authorName: self::nullable($request->post('author_name', null)),
                publisherName: self::nullable($request->post('publisher_name', null)),
                publicationYear: self::nullableInt($request->post('publication_year', null)),
                isbn: self::nullable($request->post('isbn', null)),
                shelfCode: self::nullable($request->post('shelf_code', null)),
                availabilityNote: self::nullable($request->post('availability_note', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
            );
            self::audit($request, $publicId, 'library_item.created');
            Response::redirectLocal('/admin/library?status=created');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS library create: ' . $error->getMessage());
            Response::redirectLocal('/admin/library?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'library.manage');
        $record = self::record($publicId);
        $access = LibraryOrganizationAccessService::fromDatabase();
        $userId = self::userId($request);
        if (!$access->canAccess($userId, 'library.manage', $record)) {
            Response::text('403 Forbidden', 403);
        }
        $owner = $access->assignableOwner(
            $userId,
            'library.manage',
            (string) $request->post('owner_organization_public_id', ''),
            $record->siteKey,
        );
        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            LibraryItemService::fromDatabase()->update(
                publicId: $record->publicId,
                ownerOrganizationPublicId: $owner->publicId,
                title: (string) $request->post('title', ''),
                authorName: self::nullable($request->post('author_name', null)),
                publisherName: self::nullable($request->post('publisher_name', null)),
                publicationYear: self::nullableInt($request->post('publication_year', null)),
                isbn: self::nullable($request->post('isbn', null)),
                shelfCode: self::nullable($request->post('shelf_code', null)),
                availabilityNote: self::nullable($request->post('availability_note', null)),
                summary: (string) $request->post('summary', ''),
                descriptionInput: (string) $request->post('description', ''),
                sortOrder: (int) $request->post('sort_order', 0),
                siteKey: $record->siteKey,
            );
            self::audit($request, $record->publicId, 'library_item.updated');
            Response::redirectLocal('/admin/library?status=updated');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS library update: ' . $error->getMessage());
            Response::redirectLocal('/admin/library?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        LibraryItemService::fromDatabase()->publish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'library_item.published');
        Response::redirectLocal('/admin/library?status=published');
    }

    public function unpublish(Request $request, string $publicId): never
    {
        $record = self::authorized($request, $publicId);
        LibraryItemService::fromDatabase()->unpublish($record->publicId, $record->siteKey);
        self::audit($request, $record->publicId, 'library_item.unpublished');
        Response::redirectLocal('/admin/library?status=unpublished');
    }

    private static function authorized(Request $request, string $publicId): LibraryItemRecord
    {
        AdminAuthorization::requirePermission($request, 'library.manage');
        $record = self::record($publicId);
        if (!LibraryOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            'library.manage',
            $record,
        )) {
            Response::text('403 Forbidden', 403);
        }
        return $record;
    }

    private static function record(string $publicId): LibraryItemRecord
    {
        $record = LibraryItemRepository::fromDatabase()->find(trim($publicId));
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
            subjectType: 'library_item',
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
            'created' => ['kind' => 'success', 'title' => 'Издание создано', 'message' => 'Черновик сохранён.'],
            'updated' => ['kind' => 'success', 'title' => 'Издание обновлено', 'message' => 'Изменения сохранены.'],
            'published' => ['kind' => 'success', 'title' => 'Издание опубликовано', 'message' => 'Карточка доступна в каталоге.'],
            'unpublished' => ['kind' => 'success', 'title' => 'Издание снято с публикации', 'message' => 'Карточка снова находится в черновиках.'],
            'invalid' => ['kind' => 'error', 'title' => 'Изменения не сохранены', 'message' => 'Проверьте заполнение полей.'],
            default => null,
        };
    }
}
