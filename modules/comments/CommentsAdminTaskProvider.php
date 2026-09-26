<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\App\Services\AdminTaskProvider;

final class CommentsAdminTaskProvider implements AdminTaskProvider
{
    public function id(): string
    {
        return 'comments';
    }

    public function permission(): string
    {
        return 'comments.moderate';
    }

    public function tasks(int $limit): array
    {
        $count = CommentRepository::fromDatabase()->countPending();

        if ($count < 1) {
            return [];
        }

        return [[
            'id' => 'moderation',
            'title' => 'Комментарии ждут проверки',
            'description' => 'Одобрите полезные сообщения или отклоните спам.',
            'count' => $count,
            'severity' => 'warning',
            'route' => 'admin_comments',
            'route_params' => [],
        ]];
    }
}
