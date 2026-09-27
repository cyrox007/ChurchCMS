<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use DateTimeImmutable;

final class Publication
{
    /**
     * @param list<string> $syndicationTargets
     */
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly string $siteKey,
        public readonly ?string $ownerOrganizationPublicId,
        public readonly PublicationType $type,
        public readonly PublicationStatus $status,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly string $bodyHtml,
        public readonly ?string $authorName,
        public readonly ?DateTimeImmutable $publishedAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly array $syndicationTargets,
        public readonly ?string $syndicationTitle,
        public readonly ?string $syndicationExcerpt,
        public readonly bool $commentsEnabled,
    ) {
    }

    public function isPublicNow(DateTimeImmutable $now): bool
    {
        return $this->status === PublicationStatus::Published
            && $this->publishedAt !== null
            && $this->publishedAt <= $now;
    }
}
