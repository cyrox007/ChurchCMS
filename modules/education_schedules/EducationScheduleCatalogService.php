<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationSchedules;

use DateTimeImmutable;
use DateTimeZone;

final class EducationScheduleCatalogService
{
    public function __construct(private readonly EducationScheduleRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(EducationScheduleRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(EducationScheduleRecord $record): array => $this->project($record),
            $this->repository->published($siteKey),
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(string $publicId, string $siteKey = 'default'): ?array
    {
        $record = $this->repository->find($publicId, $siteKey);
        if ($record === null || $record->status !== 'published') {
            return null;
        }
        return $this->project($record, true);
    }

    private function project(EducationScheduleRecord $record, bool $includeNote = false): array
    {
        $zone = new DateTimeZone($record->timezone);
        $starts = new DateTimeImmutable($record->startsAtUtc, new DateTimeZone('UTC'));
        $ends = new DateTimeImmutable($record->endsAtUtc, new DateTimeZone('UTC'));
        $data = [
            'public_id' => $record->publicId,
            'program_public_id' => $record->programPublicId,
            'program_title' => $record->programTitle,
            'organization_owner_id' => $record->programOwnerOrganizationPublicId,
            'title' => $record->title,
            'starts_at' => $starts->setTimezone($zone)->format(DATE_ATOM),
            'ends_at' => $ends->setTimezone($zone)->format(DATE_ATOM),
            'timezone' => $record->timezone,
            'location' => $record->location,
            'url' => '/education/schedules/' . rawurlencode($record->publicId),
            'updated_at' => $record->updatedAt,
        ];
        if ($includeNote) {
            $data['note'] = $record->note;
        }
        return $data;
    }
}
