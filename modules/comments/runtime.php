<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\PublicOriginMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Comments\CommentRateLimitMiddleware;
use ChurchCMS\Modules\Comments\CommentsAdminController;
use ChurchCMS\Modules\Comments\CommentsCapability;
use ChurchCMS\Modules\Comments\CommentsController;

$moduleRoot = __DIR__;
foreach ([
    'CommentStatus.php',
    'Comment.php',
    'CommentRepository.php',
    'CommentService.php',
    'CommentsCapability.php',
    'CommentRateLimitMiddleware.php',
    'CommentsController.php',
    'CommentsAdminController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    private CommentsCapability $capability;

    public function moduleId(): string
    {
        return 'comments';
    }

    public function capabilities(): array
    {
        return [
            'comments.publication' => $this->capability,
            'comments.moderation' => $this->capability,
        ];
    }

    public function boot(): void
    {
        $this->capability = new CommentsCapability();

        AdminNavigationRegistry::register(
            id: 'comments',
            label: 'Комментарии',
            route: 'admin_comments',
            permission: 'comments.moderate',
            priority: 30,
        );

        $router = Router::getInstance();

        $router->add(
            'POST',
            '/publications/{slug}/comments',
            [CommentsController::class, 'submit'],
            [PublicOriginMiddleware::class, CommentRateLimitMiddleware::class],
            'comment_submit',
        );

        $router->add(
            'GET',
            '/admin/comments',
            [CommentsAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_comments',
        );

        $router->add(
            'POST',
            '/admin/comments/{publicId}/moderate',
            [CommentsAdminController::class, 'moderate'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_comments_moderate',
        );
    }
};
