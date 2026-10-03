<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class PeopleAppointmentAdminController
{
    public function update(
        Request $request,
        string $appointmentPublicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'people.edit',
        );

        [$appointment, $person, $access, $userId] =
            $this->context($request, $appointmentPublicId);

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
            PersonAppointmentLifecycleService::fromDatabase()->update(
                $appointment->publicId,
                $organization->publicId,
                (string) $request->post('title', ''),
                (string) $request->post(
                    'appointment_type',
                    'position',
                ),
                self::nullable($request->post('started_on', null)),
                self::nullable($request->post('ended_on', null)),
                (int) $request->post('sort_order', 0),
                $person->siteKey,
            );

            self::audit(
                $request,
                $appointment->publicId,
                'person.appointment_updated',
                $userId,
            );

            Response::redirectLocal(
                '/admin/people?status=appointment-updated',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS appointment update: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/people?status=invalid',
            );
        }
    }

    public function end(
        Request $request,
        string $appointmentPublicId,
    ): never {
        [$appointment, $person, , $userId] =
            $this->context($request, $appointmentPublicId);

        try {
            PersonAppointmentLifecycleService::fromDatabase()->end(
                $appointment->publicId,
                self::nullable($request->post('ended_on', null)),
                $person->siteKey,
            );

            self::audit(
                $request,
                $appointment->publicId,
                'person.appointment_ended',
                $userId,
            );

            Response::redirectLocal(
                '/admin/people?status=appointment-ended',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS appointment end: '
                . $error->getMessage()
            );
            Response::redirectLocal(
                '/admin/people?status=invalid',
            );
        }
    }

    public function reactivate(
        Request $request,
        string $appointmentPublicId,
    ): never {
        [$appointment, $person, , $userId] =
            $this->context($request, $appointmentPublicId);

        PersonAppointmentLifecycleService::fromDatabase()->reactivate(
            $appointment->publicId,
            $person->siteKey,
        );

        self::audit(
            $request,
            $appointment->publicId,
            'person.appointment_reactivated',
            $userId,
        );

        Response::redirectLocal(
            '/admin/people?status=appointment-reactivated',
        );
    }

    /**
     * @return array{
     *   0:PersonAppointment,
     *   1:Person,
     *   2:PeopleOrganizationAccessService,
     *   3:int
     * }
     */
    private function context(
        Request $request,
        string $appointmentPublicId,
    ): array {
        AdminAuthorization::requirePermission(
            $request,
            'people.edit',
        );

        $repository = PeopleRepository::fromDatabase();
        $appointment = $repository->findAppointmentByPublicId(
            trim($appointmentPublicId),
        );

        if ($appointment === null) {
            Response::text('404 Not Found', 404);
        }

        $person = $repository->findPersonByPublicId(
            $appointment->personPublicId,
            $appointment->siteKey,
        );

        if ($person === null) {
            Response::text('404 Not Found', 404);
        }

        $userId = self::userId($request);
        $access = PeopleOrganizationAccessService::fromDatabase();

        if (!$access->canAccess(
            $userId,
            'people.edit',
            $person,
        )) {
            Response::text('403 Forbidden', 403);
        }

        return [$appointment, $person, $access, $userId];
    }

    private static function audit(
        Request $request,
        string $appointmentPublicId,
        string $eventType,
        int $userId,
    ): void {
        AuditLog::emit(
            eventType: $eventType,
            actorUserId: $userId,
            subjectType: 'person_appointment',
            subjectId: $appointmentPublicId,
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
