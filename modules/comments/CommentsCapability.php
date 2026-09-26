<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\Config;

final class CommentsCapability
{
    public function enabled(): bool
    {
        return Config::get('comments.enabled', true) === true;
    }

    /**
     * @return list<Comment>
     */
    public function approvedForPublication(int $publicationId): array
    {
        if (!$this->enabled()) {
            return [];
        }

        return CommentRepository::fromDatabase()->approvedForPublication($publicationId);
    }

    public function pendingCount(): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        return CommentRepository::fromDatabase()->countPending();
    }
}
