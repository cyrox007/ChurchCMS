<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationStaff;

final class EducationStaffCatalogService
{
    public function __construct(private readonly EducationStaffRepository $repository)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(EducationStaffRepository::fromDatabase());
    }

    /** @return list<array<string,mixed>> */
    public function index(string $siteKey = 'default'): array
    {
        return array_map(
            function (EducationChairRecord $chair): array {
                $teachers = $this->repository->teachers($chair->publicId, true, $chair->siteKey);
                return $this->chairProjection($chair, count($teachers));
            },
            $this->repository->publishedChairs($siteKey),
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(string $publicId, string $siteKey = 'default'): ?array
    {
        $chair = $this->repository->findChair($publicId, $siteKey);
        if ($chair === null || $chair->status !== 'published') {
            return null;
        }

        $teachers = array_map(
            static fn(EducationTeacherAssignmentRecord $assignment): array => [
                'public_id' => $assignment->publicId,
                'person_public_id' => $assignment->personPublicId,
                'display_name' => $assignment->personDisplayName,
                'position_title' => $assignment->positionTitle,
                'academic_degree' => $assignment->academicDegree,
                'academic_title' => $assignment->academicTitle,
                'disciplines' => $assignment->disciplines,
                'url' => '/people/' . rawurlencode($assignment->personPublicId),
            ],
            $this->repository->teachers($chair->publicId, true, $chair->siteKey),
        );

        return $this->chairProjection($chair, count($teachers)) + [
            'description_html' => $chair->descriptionHtml,
            'teachers' => $teachers,
        ];
    }

    private function chairProjection(EducationChairRecord $chair, int $teacherCount): array
    {
        return [
            'public_id' => $chair->publicId,
            'organization_owner_id' => $chair->ownerOrganizationPublicId,
            'name' => $chair->name,
            'short_name' => $chair->shortName,
            'sort_order' => $chair->sortOrder,
            'teacher_count' => $teacherCount,
            'url' => '/education/chairs/' . rawurlencode($chair->publicId),
            'updated_at' => $chair->updatedAt,
        ];
    }
}
