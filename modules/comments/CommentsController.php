<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\PublicFormToken;
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

        if (!PublicFormToken::validate(
            'comments.submit',
            $request->post('public_form_token'),
        )) {
            Response::redirectLocal(
                '/publications/' . rawurlencode($slug) . '?comment=invalid#comments'
            );
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

            $state = Config::get('comments.moderation', 'premoderated') === 'open'
                ? 'published'
                : 'queued';

            Response::redirectLocal(
                '/publications/' . rawurlencode($slug) . '?comment=' . $state . '#comments'
            );
        } catch (InvalidArgumentException) {
            Response::redirectLocal(
                '/publications/' . rawurlencode($slug) . '?comment=invalid#comments'
            );
        }
    }
}
