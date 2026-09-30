<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Redirects;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;

final class RedirectAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'redirects.manage',
        );

        AdminShell::page(
            $request,
            'admin.redirects',
            [
                'title' => 'Редиректы',
                'redirectRules' =>
                    RedirectRepository::fromDatabase()->all(),
                'redirectStatus' => self::status($request),
            ],
            'redirects',
        );
    }

    public function create(Request $request): never
    {
        AdminAuthorization::requirePermission(
            $request,
            'redirects.manage',
        );

        try {
            $rule = RedirectService::fromDatabase()->create(
                sourcePath: (string) $request->post(
                    'source_path',
                    '',
                ),
                targetPath: (string) $request->post(
                    'target_path',
                    '',
                ),
                statusCode: (int) $request->post(
                    'status_code',
                    301,
                ),
                enabled: $request->post(
                    'enabled',
                    null,
                ) !== null,
            );

            self::audit(
                $request,
                'redirect.created',
                $rule,
            );

            Response::redirectLocal(
                '/admin/redirects?status=created',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS создание редиректа: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/redirects?status=invalid',
            );
        }
    }

    public function update(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'redirects.manage',
        );

        try {
            $rule = RedirectService::fromDatabase()->update(
                publicId: $publicId,
                sourcePath: (string) $request->post(
                    'source_path',
                    '',
                ),
                targetPath: (string) $request->post(
                    'target_path',
                    '',
                ),
                statusCode: (int) $request->post(
                    'status_code',
                    301,
                ),
                enabled: $request->post(
                    'enabled',
                    null,
                ) !== null,
            );

            self::audit(
                $request,
                'redirect.updated',
                $rule,
            );

            Response::redirectLocal(
                '/admin/redirects?status=updated',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS обновление редиректа: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/redirects?status=invalid',
            );
        }
    }

    public function delete(
        Request $request,
        string $publicId,
    ): never {
        AdminAuthorization::requirePermission(
            $request,
            'redirects.manage',
        );

        try {
            $repository = RedirectRepository::fromDatabase();
            $rule = $repository->findByPublicId($publicId);

            if ($rule === null) {
                Response::text('404 Not Found', 404);
            }

            RedirectService::fromDatabase()->delete(
                $rule->publicId,
                $rule->siteKey,
            );

            self::audit(
                $request,
                'redirect.deleted',
                $rule,
            );

            Response::redirectLocal(
                '/admin/redirects?status=deleted',
            );
        } catch (InvalidArgumentException $error) {
            error_log(
                'ChurchCMS удаление редиректа: '
                . $error->getMessage()
            );

            Response::redirectLocal(
                '/admin/redirects?status=invalid',
            );
        }
    }

    private static function audit(
        Request $request,
        string $eventType,
        RedirectRule $rule,
    ): void {
        $user = $request->attribute('admin.user');
        $actorUserId = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : null;

        AuditLog::emit(
            eventType: $eventType,
            actorUserId: $actorUserId,
            subjectType: 'redirect_rule',
            subjectId: $rule->publicId,
            metadata: [
                'source_path' => $rule->sourcePath,
                'target_path' => $rule->targetPath,
                'status_code' => $rule->statusCode,
                'enabled' => $rule->enabled,
            ],
            request: $request,
        );
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(
        Request $request,
    ): ?array {
        return match (
            (string) $request->get('status', '')
        ) {
            'created' => [
                'kind' => 'success',
                'title' => 'Редирект создан',
                'message' => 'Правило готово к использованию.',
            ],
            'updated' => [
                'kind' => 'success',
                'title' => 'Редирект сохранён',
                'message' => 'Изменения правила применены.',
            ],
            'deleted' => [
                'kind' => 'success',
                'title' => 'Редирект удалён',
                'message' => 'Правило больше не применяется.',
            ],
            'invalid' => [
                'kind' => 'error',
                'title' => 'Правило не сохранено',
                'message' => 'Проверьте пути, HTTP-код и отсутствие цепочки редиректов.',
            ],
            default => null,
        };
    }
}
