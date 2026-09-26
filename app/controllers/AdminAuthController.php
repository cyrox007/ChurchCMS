<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminAuthService;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class AdminAuthController
{
    public function login(Request $request): never
    {
        if (AdminAuthService::fromDatabase()->current($request) !== null) {
            Response::redirectLocal('/admin');
        }

        ThemeRenderer::fromConfig()->page('auth.login', [
            'title' => 'Вход в ChurchCMS',
            'siteName' => 'ChurchCMS',
            'error' => null,
        ]);
    }

    public function authenticate(Request $request): never
    {
        $username = trim((string) $request->post('username', ''));
        $password = (string) $request->post('password', '');

        $user = AdminAuthService::fromDatabase()->authenticate(
            $request,
            $username,
            $password,
        );

        if ($user === null) {
            ThemeRenderer::fromConfig()->page('auth.login', [
                'title' => 'Вход в ChurchCMS',
                'siteName' => 'ChurchCMS',
                'username' => $username,
                'error' => 'Неверное имя пользователя или пароль.',
            ]);
        }

        Response::redirectLocal('/admin');
    }

    public function logout(Request $request): never
    {
        AdminAuthService::fromDatabase()->logout();
        Response::redirectLocal('/admin/login');
    }
}
