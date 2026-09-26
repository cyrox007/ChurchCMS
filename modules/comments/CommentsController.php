<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Modules\Publications\PublicationRepository;
use InvalidArgumentException;

final class CommentsController
{
    public function submit(Request $request, string $slug): never
    {
        if (Config::get('comments.enabled', true) !== true) {
            Response::text('404 Not Found', 404);
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            Response::text('404 Not Found', 404);
        }

        $publication = PublicationRepository::fromDatabase()->findPublishedBySlug($slug);
        if ($publication === null || !$publication->commentsEnabled) {
            Response::text('404 Not Found', 404);
        }

        try {
            CommentService::fromDatabase()->submit(
                publication: $publication,
                displayName: (string) $request->post('display_name', ''),
                email: (string) $request->post('email', ''),
                bodyText: (string) $request->post('body_text', ''),
            );

            $request->setSession('comment.flash', [
                'type' => 'success',
                'message' => Config::get('comments.moderation', 'premoderated') === 'open'
                    ? 'Комментарий опубликован.'
                    : 'Комментарий отправлен и появится после проверки.',
            ]);
        } catch (InvalidArgumentException $e) {
            $request->setSession('comment.flash', [
                'type' => 'error',
                'message' => 'Проверьте имя, email и текст комментария.',
            ]);
        }

        Response::redirectLocal('/publications/' . rawurlencode($slug) . '#comments');
    }
}
