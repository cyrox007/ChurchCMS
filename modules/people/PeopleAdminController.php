<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class PeopleAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'people.read',
        );

        $userId = self::userId($request);
        $access = PeopleOrganizationAccessService::fromDatabase();
        $ownerIds = $access->visibleOwnerPublicIds(
            $userId,
            'people.read',
        );
        $repository = PeopleRepository::fromDatabase();
        $rows = [];

        foreach ($repository->adminList($ownerIds) as $person) {
            $rows[] = [
                'person' => $person,
                'appointments' =>
                    $repository->appointmentsForPerson(
                        $person->publicId,
                        $person->siteKey,
                    ),
            ];
        }

        $canCreate = AdminAuthorization::can(
            $request,
            'people.create',
        );
        $canEdit = AdminAuthorization::can(
            $request,
            'people.edit',
        );

        AdminShell::page(
            $request,
            'admin.people',
            [
                'title' => 'Люди и духовенство',
                'peopleRows' => $rows,
                'canCreate' => $canCreate,
                'canEdit' => $canEdit,
                'createOrganizationUnits' => $canCreate
                    ? $access->availableOwners(
                        $userId,
                        'people.create',
                    )
                    : [],
                'editOrganizationUnits' => $canEdit
                    ? $access->availableOwners(
                        $userId,
                        'people.edit',
                    )
                    : [],
                'defaultOwnerPublicId' =>
                    $access->defaultOwnerPublicId(
                        $userId,
                        'people.create',
                    ),
            ],
            'people',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'people.create',
        );

        $userId = self::userId($request);
        $access = PeopleOrganizationAccessService::fromDatabase();
        $owner = $access->assignableOwner(
            $userId,
            'people.create',
            (string) $request->post(
                'owner_organization_public_id',
                '',
            ),
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $publicId = PeopleService::fromDatabase()->createPerson(
                displayName: (string) $request->post(
                    'display_name',
                    '',
                ),
                ownerOrganizationPublicId: $owner->publicId,
                firstName: self::nullable(
                    $request->post('first_name', null),
                ),
                middleName: self::nullable(
                    $request->post('middle_name', null),
                ),
                lastName: self::nullable(
                    $request->post('last_name', null),
                ),
                biographyHtml: (string) $request->post(
                    'biography',
                    '',
                ),
            );

            AuditLog::emit(
                eventType: 'person.created',
                actorUserId: $userId,
                subjectType: 'person',
                subjectId: $publicId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/people?status=created',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS person create: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/people?status=invalid',
            );
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'people.edit',
        );

        $userId = self::userId($request);
        $repository = PeopleRepository::fromDatabase();
        $person = $repository->findPersonByPublicId(
            trim($publicId),
        );

        if ($person === null) {
            Response::text('404 Not Found', 404);
        }

        $access = PeopleOrganizationAccessService::fromDatabase();
        if (!$access->canAccess(
            $userId,
            'people.edit',
            $person,
        )) {
            Response::text('403 Forbidden', 403);
        }

        $owner = $access->assignableOwner(
            $userId,
            'people.edit',
            (string) $request->post(
                'owner_organization_public_id',
                '',
            ),
            $person->siteKey,
        );

        if ($owner === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $service = PeopleService::fromDatabase();
            $service->updatePerson(
                $person->publicId,
                (string) $request->post(
                    'display_name',
                    '',
                ),
                self::nullable(
                    $request->post('first_name', null),
                ),
                self::nullable(
                    $request->post('middle_name', null),
                ),
                self::nullable(
                    $request->post('last_name', null),
                ),
                (string) $request->post(
                    'biography',
                    '',
                ),
                $person->siteKey,
            );

            if (
                $owner->publicId
                !== $person->ownerOrganizationPublicId
            ) {
                $service->assignOrganizationOwner(
                    $person->publicId,
                    $owner->publicId,
                    $person->siteKey,
                );
            }

            AuditLog::emit(
                eventType: 'person.updated',
                actorUserId: $userId,
                subjectType: 'person',
                subjectId: $person->publicId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/people?status=updated',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS person update: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/people?status=invalid',
            );
        }
    }

    public function createAppointment(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'people.edit',
        );

        $userId = self::userId($request);
        $repository = PeopleRepository::fromDatabase();
        $person = $repository->findPersonByPublicId(
            trim($publicId),
        );

        if ($person === null) {
            Response::text('404 Not Found', 404);
        }

        $access = PeopleOrganizationAccessService::fromDatabase();
        if (!$access->canAccess(
            $userId,
            'people.edit',
            $person,
        )) {
            Response::text('403 Forbidden', 403);
        }

        $organization = $access->assignableOwner(
            $userId,
            'people.edit',
            (string) $request->post(
                'organization_public_id',
                '',
            ),
            $person->siteKey,
        );

        if ($organization === null) {
            Response::text('403 Forbidden', 403);
        }

        try {
            $appointmentId = PeopleService::fromDatabase()
                ->createAppointment(
                    personPublicId: $person->publicId,
                    organizationPublicId:
                        $organization->publicId,
                    title: (string) $request->post(
                        'title',
                        '',
                    ),
                    type: (string) $request->post(
                        'appointment_type',
                        'position',
                    ),
                    startedOn: self::nullable(
                        $request->post('started_on', null),
                    ),
                    endedOn: self::nullable(
                        $request->post('ended_on', null),
                    ),
                    sortOrder: (int) $request->post(
                        'sort_order',
                        0,
                    ),
                    siteKey: $person->siteKey,
                );

            AuditLog::emit(
                eventType: 'person.appointment_created',
                actorUserId: $userId,
                subjectType: 'person_appointment',
                subjectId: $appointmentId,
                request: $request,
            );

            Response::redirectLocal(
                '/admin/people?status=appointment-created',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS appointment create: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/people?status=invalid',
            );
        }
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
