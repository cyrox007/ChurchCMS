<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminPasswordResetService;
use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;
use InvalidArgumentException;
use Throwable;

final class AdminPasswordResetController
{
    public function forgot(Request $request): never
    {
        ThemeRenderer::fromConfig()->page(
            'auth.password_forgot',
            [
                'title' => 'Восстановление доступа',
                'submitted' =>
                    (string) $request->get(
                        'status',
                        '',
                    ) === 'sent',
                'error' => null,
            ],
        );
    }

    public function request(Request $request): never
    {
        $email = trim((string) $request->post(
            'email',
            '',
        ));

        try {
            $delivered = AdminPasswordResetService::fromConfig()
                ->requestReset($email);

            AuditLog::emit(
                eventType: 'admin.password_reset.requested',
                subjectType: 'admin_user',
                metadata: [
                    'delivered' => $delivered,
                ],
                request: $request,
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS восстановление пароля: '
                . $error->getMessage()
            );

            AuditLog::emit(
                eventType: 'admin.password_reset.delivery_failed',
                severity: 'error',
                subjectType: 'admin_user',
                metadata: [
                    'error_class' => $error::class,
                ],
                request: $request,
            );
        }

        Response::redirectLocal(
            '/admin/password/forgot?status=sent',
        );
    }

    public function reset(Request $request): never
    {
        $token = trim((string) $request->get(
            'token',
            '',
        ));

        $valid = AdminPasswordResetService::fromConfig()
            ->tokenIsValid($token);

        ThemeRenderer::fromConfig()->page(
            'auth.password_reset',
            [
                'title' => 'Новый пароль',
                'token' => $token,
                'validToken' => $valid,
                'changed' =>
                    (string) $request->get(
                        'status',
                        '',
                    ) === 'changed',
                'error' => null,
            ],
        );
    }

    public function consume(Request $request): never
    {
        $token = trim((string) $request->post(
            'token',
            '',
        ));
        $password = (string) $request->post(
            'new_password',
            '',
        );
        $confirmation = (string) $request->post(
            'new_password_confirm',
            '',
        );

        try {
            AdminPasswordResetService::fromConfig()
                ->resetPassword(
                    $token,
                    $password,
                    $confirmation,
                );
        } catch (InvalidArgumentException $error) {
            $this->renderError(
                $token,
                $error->getMessage(),
            );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS применение reset-токена: '
                . $error->getMessage()
            );

            $this->renderError(
                $token,
                'Не удалось изменить пароль. Повторите попытку.',
            );
        }

        AuditLog::emit(
            eventType: 'admin.password_reset.completed',
            subjectType: 'admin_user',
            metadata: [
                'all_sessions_revoked' => true,
            ],
            request: $request,
        );

        Response::redirectLocal(
            '/admin/password/reset?status=changed',
        );
    }

    private function renderError(
        string $token,
        string $message,
    ): never {
        ThemeRenderer::fromConfig()->page(
            'auth.password_reset',
            [
                'title' => 'Новый пароль',
                'token' => $token,
                'validToken' =>
                    AdminPasswordResetService::fromConfig()
                        ->tokenIsValid($token),
                'changed' => false,
                'error' => $message,
            ],
        );
    }
}
