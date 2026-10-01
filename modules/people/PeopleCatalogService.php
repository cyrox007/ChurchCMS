<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

final class PeopleCatalogService
{
    public function __construct(
        private readonly PeopleRepository $people,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            PeopleRepository::fromDatabase(),
        );
    }

    /** @return list<array<string,mixed>> */
    public function index(
        string $siteKey = 'default',
        int $limit = 200,
    ): array {
        $result = [];

        foreach (
            $this->people->activePublic(
                $siteKey,
                $limit,
            ) as $person
        ) {
            $result[] = $this->project($person);
        }

        return $result;
    }

    /** @return array<string,mixed>|null */
    public function detail(
        string $publicId,
        string $siteKey = 'default',
    ): ?array {
        $person = $this->people->findPersonByPublicId(
            trim($publicId),
            $siteKey,
        );

        if ($person === null || $person->status !== 'active') {
            return null;
        }

        return $this->project($person);
    }

    /** @return array<string,mixed> */
    private function project(Person $person): array
    {
        $appointments = array_values(array_filter(
            $this->people->appointmentsForPerson(
                $person->publicId,
                $person->siteKey,
            ),
            static fn(PersonAppointment $appointment): bool =>
                $appointment->status === 'active',
        ));

        return [
            'id' => $person->publicId,
            'type' => 'person',
            'display_name' => $person->displayName,
            'first_name' => $person->firstName,
            'middle_name' => $person->middleName,
            'last_name' => $person->lastName,
            'biography' => $person->biographyHtml,
            'organization_owner_id' =>
                $person->ownerOrganizationPublicId,
            'appointments' => array_map(
                static fn(PersonAppointment $appointment): array => [
                    'id' => $appointment->publicId,
                    'organization_id' =>
                        $appointment->organizationPublicId,
                    'title' => $appointment->title,
                    'type' => $appointment->type,
                    'started_on' => $appointment->startedOn,
                    'ended_on' => $appointment->endedOn,
                    'sort_order' => $appointment->sortOrder,
                ],
                $appointments,
            ),
            'url' => '/people/' . rawurlencode($person->publicId),
            'updated_at' => $person->updatedAt,
        ];
    }
}
