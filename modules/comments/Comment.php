<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use DateTimeImmutable;

final class Comment
{
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $publicationId,
        public readonly CommentStatus $status,
        public readonly string $displayName,
        public readonly ?string $email,
        public readonly string $bodyText,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $moderatedAt,
        public readonly ?int $moderatorUserId,
        public readonly ?string $publicationTitle = null,
        public readonly ?string $publicationSlug = null,
    ) {
    }
}
