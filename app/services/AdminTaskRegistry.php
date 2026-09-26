<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use RuntimeException;

final class AdminTaskRegistry
{
    /** @var array<string,AdminTaskProvider> */
    private static array $providers = [];

    public static function register(AdminTaskProvider $provider): void
    {
        $id = trim($provider->id());

        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $id) !== 1) {
            throw new RuntimeException(
                'Некорректный идентификатор провайдера задач административной панели.'
            );
        }

        if (trim($provider->permission()) === '') {
            throw new RuntimeException(
                'Провайдер задач административной панели не указал право доступа.'
            );
        }

        $existing = self::$providers[$id] ?? null;
        if ($existing !== null) {
            if ($existing::class === $provider::class) {
                return;
            }

            throw new RuntimeException(
                'Провайдер задач административной панели уже зарегистрирован: '
                . $id
            );
        }

        self::$providers[$id] = $provider;
    }

    /** @return list<AdminTaskProvider> */
    public static function providers(): array
    {
        $providers = self::$providers;
        ksort($providers, SORT_STRING);

        return array_values($providers);
    }
}
