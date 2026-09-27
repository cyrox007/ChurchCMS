<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminAuthService;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use InvalidArgumentException;
use Throwable;

final class AdminAccountController
{
    public function password(Request $request): never
    {
        AdminShell::page(
            $request,
            'admin.account_password',
            [
                'title' => 'Смена пароля',
                'passwordStatus' => self::status($request),
                'passwordError' => null,
            ],
            'account',
        );
    }

    public function updatePassword(Request $request): never
    {
        $currentPassword = (string) $request->post(
            'current_password',
            '',
        );
        $newPassword = (string) $request->post(
            'new_password',
            '',
        );
        $confirmation = (string) $request->post(
            'new_password_confirm',
            '',
        );

        if (
            strlen($currentPassword) > 4096
            || strlen($newPassword) > 4096
            || strlen($confirmation) > 4096
        ) {
            $this->renderError(
                $request,
                'Пароль слишком длинный.',
            );
        }

        try {
            AdminAuthService::fromDatabase()->rotatePassword(
                $request,
                $currentPassword,
                $newPassword,
                $confirmation,
            );
        } catch (InvalidArgumentException $e) {
            $this->renderError(
                $request,
                $e->getMessage(),
            );
        } catch (Throwable $e) {
            error_log(
                'ChurchCMS смена пароля: '
                . $e->getMessage()
            );

            $this->renderError(
                $request,
                'Не удалось изменить пароль. Повторите попытку.',
            );
        }

        $user = $request->attribute('admin.user');
        $userId = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : 0;
        $publicId = is_array($user)
            ? (string) ($user['public_id'] ?? '')
            : '';

        AuditLog::emit(
            eventType: 'admin.password.rotated',
            actorUserId: $userId > 0 ? $userId : null,
            subjectType: 'admin_user',
            subjectId: $publicId !== '' ? $publicId : null,
            metadata: [
                'other_sessions_revoked' => true,
            ],
            request: $request,
        );

        Response::redirectLocal(
            '/admin/account/password?status=changed',
        );
    }

    private function renderError(
        Request $request,
        string $message,
    ): never {
        AdminShell::page(
            $request,
            'admin.account_password',
            [
                'title' => 'Смена пароля',
                'passwordStatus' => null,
                'passwordError' => $message,
            ],
            'account',
        );
    }

    /**
     * @return array{kind:string,title:string,message:string}|null
     */
    private static function status(Request $request): ?array
    {
        return match ((string) $request->get('status', '')) {
            'changed' => [
                'kind' => 'success',
                'title' => 'Пароль изменён',
                'message' => 'Старые административные сессии отозваны. Текущая сессия продолжает работу.',
            ],
            default => null,
        };
    }
}
