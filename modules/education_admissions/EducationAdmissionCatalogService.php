<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

final class EducationAdmissionCatalogService
{
    public function __construct(private readonly EducationAdmissionRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(EducationAdmissionRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            fn(EducationAdmissionRecord $record): array => $this->project($record),
            $this->repository->published($siteKey),
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(string $publicId, string $siteKey = 'default'): ?array
    {
        $record = $this->repository->findPublished(trim($publicId), $siteKey);
        return $record === null ? null : $this->project($record, true);
    }

    private function project(EducationAdmissionRecord $record, bool $detail = false): array
    {
        $data = [
            'public_id' => $record->publicId,
            'program_public_id' => $record->programPublicId,
            'program_title' => $record->programTitle,
            'title' => $record->title,
            'academic_year' => $record->academicYear,
            'starts_on' => $record->startsOn,
            'ends_on' => $record->endsOn,
            'budget_seats' => $record->budgetSeats,
            'paid_seats' => $record->paidSeats,
            'tuition_note' => $record->tuitionNote,
            'contact_note' => $record->contactNote,
            'url' => '/education/admissions/' . rawurlencode($record->publicId),
            'program_url' => '/education/programs/' . rawurlencode($record->programPublicId),
            'updated_at' => $record->updatedAt,
        ];

        if ($detail) {
            $data['requirements'] = $record->requirements;
            $data['entrance_tests'] = $record->entranceTests;
        }

        return $data;
    }
}
