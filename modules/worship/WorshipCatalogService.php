<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

final class WorshipCatalogService
{
    public function __construct(
        private readonly WorshipRepository $worship,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            WorshipRepository::fromDatabase(),
        );
    }

    /** @return list<array<string,mixed>> */
    public function upcoming(
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        return array_map(
            $this->project(...),
            $this->worship->visibleUpcoming(
                $siteKey,
                $limit,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(
        string $publicId,
        string $siteKey = 'default',
    ): ?array {
        $service = $this->worship->findByPublicId(
            trim($publicId),
            $siteKey,
        );

        if (
            $service === null
            || !in_array(
                $service->status,
                ['scheduled', 'cancelled'],
                true,
            )
        ) {
            return null;
        }

        return $this->project($service);
    }

    /** @return array<string,mixed> */
    private function project(WorshipService $service): array
    {
        return [
            'id' => $service->publicId,
            'type' => 'worship',
            'status' => $service->status,
            'title' => $service->title,
            'service_type' => $service->serviceType,
            'starts_at' => $service->startsAt,
            'ends_at' => $service->endsAt,
            'location_name' => $service->locationName,
            'description' => $service->descriptionHtml,
            'organization_owner_id' =>
                $service->ownerOrganizationPublicId,
            'url' => '/worship/' . rawurlencode(
                $service->publicId,
            ),
            'updated_at' => $service->updatedAt,
        ];
    }
}
