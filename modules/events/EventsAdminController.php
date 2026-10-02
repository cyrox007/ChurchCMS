<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class EventsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'events.read');

        $userId = self::userId($request);
        $access = EventOrganizationAccessService::fromDatabase();
        $canCreate = AdminAuthorization::can($request, 'events.create');
        $canEdit = AdminAuthorization::can($request, 'events.edit');
        $canPublish = AdminAuthorization::can($request, 'events.publish');

        AdminShell::page(
            $request,
            'admin.events',
            [
                'title' => 'События',
                'events' => EventRepository::fromDatabase()->adminList(
                    $access->visibleOwnerPublicIds(
                        $userId,
                        'events.read',
                    ),
                ),
                'canCreate' => $canCreate,
                'canEdit' => $canEdit,
                'canPublish' => $canPublish,
                'createOrganizationUnits' => $canCreate
                    ? $access->availableOwners(
                        $userId,
                        'events.create',
                    )
                    : [],
                'editOrganizationUnits' => $canEdit
                    ? $access->availableOwners(
                        $userId,
                        'events.edit',
                    )
                    : [],
                'defaultOwnerPublicId' =>
                    $access->defaultOwnerPublicId(
                        $userId,
                        'events.create',
                    ),
            ],
            'events',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'events.create');
        $userId = self::userId($request);
        $access = EventOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'events.create',
            (string) $request->post('owner_organization_public_id', ''),
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $id = EventService::fromDatabase()->create(
                title: (string) $request->post('title', ''),
                startsAt: (string) $request->post('starts_at', ''),
                ownerOrganizationPublicId: $owner->publicId,
                endsAt: self::nullable($request->post('ends_at', null)),
                allDay: $request->post('all_day', null) !== null,
                locationName: self::nullable(
                    $request->post('location_name', null),
                ),
                excerpt: (string) $request->post('excerpt', ''),
                descriptionHtml: (string) $request->post(
                    'description',
                    '',
                ),
            );

            AuditLog::emit(
                eventType: 'event.created',
                actorUserId: $userId,
                subjectType: 'event',
                subjectId: $id,
                request: $request,
            );

            Response::redirectLocal('/admin/events');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS event create: ' . $error->getMessage());
            Response::redirectLocal('/admin/events?status=invalid');
        }
    }

    public function update(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'events.edit');
        $userId = self::userId($request);
        $event = self::event($publicId);
        $access = EventOrganizationAccessService::fromDatabase();

        if (!$access->canAccess($userId, 'events.edit', $event)) {
            Response::text('403 Forbidden', 403);
        }

        $owner = $access->assignableOwner(
            $userId,
            'events.edit',
            (string) $request->post('owner_organization_public_id', ''),
            $event->siteKey,
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $service = EventService::fromDatabase();
            $service->update(
                $event->publicId,
                (string) $request->post('title', ''),
                (string) $request->post('starts_at', ''),
                self::nullable($request->post('ends_at', null)),
                $request->post('all_day', null) !== null,
                self::nullable($request->post('location_name', null)),
                (string) $request->post('excerpt', ''),
                (string) $request->post('description', ''),
                $event->siteKey,
            );

            if ($owner->publicId !== $event->ownerOrganizationPublicId) {
                $service->assignOrganizationOwner(
                    $event->publicId,
                    $owner->publicId,
                    $event->siteKey,
                );
            }

            self::audit($request, $event, 'event.updated');
            Response::redirectLocal('/admin/events');
        } catch (InvalidArgumentException $error) {
            error_log('ChurchCMS event update: ' . $error->getMessage());
            Response::redirectLocal('/admin/events?status=invalid');
        }
    }

    public function publish(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'events.publish');
        $event = self::authorized($request, $publicId, 'events.publish');
        EventService::fromDatabase()->publish(
            $event->publicId,
            $event->siteKey,
        );
        self::audit($request, $event, 'event.published');
        Response::redirectLocal('/admin/events');
    }

    public function withdraw(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'events.publish');
        $event = self::authorized($request, $publicId, 'events.publish');
        EventService::fromDatabase()->withdraw(
            $event->publicId,
            $event->siteKey,
        );
        self::audit($request, $event, 'event.withdrawn');
        Response::redirectLocal('/admin/events');
    }

    public function cancel(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'events.edit');
        $event = self::authorized($request, $publicId, 'events.edit');
        EventService::fromDatabase()->cancel(
            $event->publicId,
            $event->siteKey,
        );
        self::audit($request, $event, 'event.cancelled');
        Response::redirectLocal('/admin/events');
    }

    private static function authorized(
        Request $request,
        string $publicId,
        string $permission,
    ): Event {
        $event = self::event($publicId);

        if (!EventOrganizationAccessService::fromDatabase()->canAccess(
            self::userId($request),
            $permission,
            $event,
        )) {
            Response::text('403 Forbidden', 403);
        }

        return $event;
    }

    private static function event(string $publicId): Event
    {
        $event = EventRepository::fromDatabase()
            ->findByPublicId(trim($publicId));

        if ($event === null) {
            Response::text('404 Not Found', 404);
        }

        return $event;
    }

    private static function audit(
        Request $request,
        Event $event,
        string $type,
    ): void {
        AuditLog::emit(
            eventType: $type,
            actorUserId: self::userId($request),
            subjectType: 'event',
            subjectId: $event->publicId,
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

        return $value !== '' ? $value : null;
    }
}
