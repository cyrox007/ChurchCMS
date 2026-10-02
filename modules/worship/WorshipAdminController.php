<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class WorshipAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'worship.read',
        );

        $userId = self::userId($request);
        $access = WorshipOrganizationAccessService::fromDatabase();
        $ownerIds = $access->visibleOwnerPublicIds(
            $userId,
            'worship.read',
        );
        $canCreate = AdminAuthorization::can(
            $request,
            'worship.create',
        );
        $canEdit = AdminAuthorization::can(
            $request,
            'worship.edit',
        );

        AdminShell::page(
            $request,
            'admin.worship',
            [
                'title' => 'Расписание богослужений',
                'services' => WorshipRepository::fromDatabase()
                    ->adminList($ownerIds),
                'canCreate' => $canCreate,
                'canEdit' => $canEdit,
                'createOrganizationUnits' => $canCreate
                    ? $access->availableOwners(
                        $userId,
                        'worship.create',
                    )
                    : [],
                'editOrganizationUnits' => $canEdit
                    ? $access->availableOwners(
                        $userId,
                        'worship.edit',
                    )
                    : [],
                'defaultOwnerPublicId' =>
                    $access->defaultOwnerPublicId(
                        $userId,
                        'worship.create',
                    ),
            ],
            'worship',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'worship.create',
        );

        $userId = self::userId($request);
        $access = WorshipOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'worship.create',
            (string) $request->post(
                'owner_organization_public_id',
                '',
            ),
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = WorshipScheduleService::fromDatabase()
                ->create(
                    title: (string) $request->post('title', ''),
                    startsAt: (string) $request->post('starts_at', ''),
                    ownerOrganizationPublicId: $owner->publicId,
                    serviceType: (string) $request->post(
                        'service_type',
                        'service',
                    ),
                    endsAt: self::nullable(
                        $request->post('ends_at', null),
                    ),
                    locationName: self::nullable(
                        $request->post('location_name', null),
                    ),
                    descriptionHtml: (string) $request->post(
                        'description',
                        '',
                    ),
                );

            AuditLog::emit(
                eventType: 'worship.created',
                actorUserId: $userId,
                subjectType: 'worship',
                subjectId: $publicId,
                request: $request,
            );

            Response::redirectLocal('/admin/worship');
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS worship create: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/worship?status=invalid',
            );
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'worship.edit',
        );

        $userId = self::userId($request);
        $service = self::service($publicId);
        $access = WorshipOrganizationAccessService::fromDatabase();

        if (!$access->canAccess(
            $userId,
            'worship.edit',
            $service,
        )) {
            Response::text('403 Forbidden', 403);
        }

        $owner = $access->assignableOwner(
            $userId,
            'worship.edit',
            (string) $request->post(
                'owner_organization_public_id',
                '',
            ),
            $service->siteKey,
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $schedule = WorshipScheduleService::fromDatabase();
            $schedule->update(
                $service->publicId,
                (string) $request->post('title', ''),
                (string) $request->post('starts_at', ''),
                (string) $request->post(
                    'service_type',
                    'service',
                ),
                self::nullable(
                    $request->post('ends_at', null),
                ),
                self::nullable(
                    $request->post('location_name', null),
                ),
                (string) $request->post(
                    'description',
                    '',
                ),
                $service->siteKey,
            );

            if (
                $owner->publicId
                !== $service->ownerOrganizationPublicId
            ) {
                $schedule->assignOrganizationOwner(
                    $service->publicId,
                    $owner->publicId,
                    $service->siteKey,
                );
            }

            AuditLog::emit(
                eventType: 'worship.updated',
                actorUserId: $userId,
                subjectType: 'worship',
                subjectId: $service->publicId,
                request: $request,
            );

            Response::redirectLocal('/admin/worship');
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS worship update: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/worship?status=invalid',
            );
        }
    }

    public function cancel(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'worship.edit',
        );
        $service = self::authorized(
            $request,
            $publicId,
        );

        WorshipScheduleService::fromDatabase()->cancel(
            $service->publicId,
            $service->siteKey,
        );

        self::audit($request, $service, 'worship.cancelled');
        Response::redirectLocal('/admin/worship');
    }

    public function schedule(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'worship.edit',
        );
        $service = self::authorized(
            $request,
            $publicId,
        );

        WorshipScheduleService::fromDatabase()->schedule(
            $service->publicId,
            $service->siteKey,
        );

        self::audit($request, $service, 'worship.scheduled');
        Response::redirectLocal('/admin/worship');
    }

    private static function authorized(
        Request $request,
        string $publicId,
    ): WorshipService {
        $service = self::service($publicId);

        if (!WorshipOrganizationAccessService::fromDatabase()
            ->canAccess(
                self::userId($request),
                'worship.edit',
                $service,
            )
        ) {
            Response::text('403 Forbidden', 403);
        }

        return $service;
    }

    private static function service(
        string $publicId,
    ): WorshipService {
        $service = WorshipRepository::fromDatabase()
            ->findByPublicId(trim($publicId));

        if ($service === null) {
            Response::text('404 Not Found', 404);
        }

        return $service;
    }

    private static function audit(
        Request $request,
        WorshipService $service,
        string $eventType,
    ): void {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: self::userId($request),
            subjectType: 'worship',
            subjectId: $service->publicId,
            request: $request,
        );
    }

    private static function userId(Request $request): int
    {
        $user = $request->attribute('admin.user');
        $id = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;

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
