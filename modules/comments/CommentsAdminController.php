<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\App\Services\AdminShell;

final class CommentsAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'comments.moderate');

        AdminShell::page($request, 'admin.comments', [
            'title' => 'Комментарии',
            'comments' => CommentRepository::fromDatabase()->moderationQueue(),
        ], 'comments');
    }

    public function moderate(Request $request, string $publicId): never
    {
        AdminAuthorization::requirePermission($request, 'comments.moderate');

        $status = CommentStatus::tryFrom((string) $request->post('status', ''));
        if (
            $status === null
            || !in_array($status, [CommentStatus::Approved, CommentStatus::Rejected, CommentStatus::Spam], true)
        ) {
            Response::text('400 Bad Request', 400);
        }

        $comment = CommentRepository::fromDatabase()->findByPublicId($publicId);
        if ($comment === null) {
            Response::text('404 Not Found', 404);
        }

        $user = $request->attribute('admin.user');
        $moderatorUserId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        CommentService::fromDatabase()->moderate(
            publicId: $publicId,
            status: $status,
            moderatorUserId: $moderatorUserId,
        );

        AuditLog::emit(
            eventType: 'comment.moderated',
            actorUserId: $moderatorUserId,
            subjectType: 'publication_comment',
            subjectId: $publicId,
            metadata: ['status' => $status->value],
            request: $request,
        );

        Response::redirectLocal('/admin/comments');
    }
}
