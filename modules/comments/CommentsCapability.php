<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\Config;
use ChurchCMS\Modules\Publications\Publication;

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

    /**
     * @return array{
     *     available:bool,
     *     open:bool,
     *     comments:list<Comment>
     * }
     */
    public function discussionForPublication(
        Publication $publication,
    ): array {
        if (!$this->enabled()) {
            return [
                'available' => false,
                'open' => false,
                'comments' => [],
            ];
        }

        $comments = $this->approvedForPublication(
            $publication->id,
        );
        $open = $publication->commentsEnabled;

        return [
            'available' => $open || $comments !== [],
            'open' => $open,
            'comments' => $comments,
        ];
    }

    public function pendingCount(): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        return CommentRepository::fromDatabase()->countPending();
    }
}
