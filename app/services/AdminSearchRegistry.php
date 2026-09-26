<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use RuntimeException;

final class AdminSearchRegistry
{
    /** @var array<string,AdminSearchProvider> */
    private static array $providers = [];

    public static function register(AdminSearchProvider $provider): void
    {
        $id = trim($provider->id());

        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $id) !== 1) {
            throw new RuntimeException(
                'Некорректный идентификатор провайдера административного поиска.'
            );
        }

        if (
            trim($provider->label()) === ''
            || trim($provider->permission()) === ''
        ) {
            throw new RuntimeException(
                'Провайдер административного поиска заполнен не полностью.'
            );
        }

        $existing = self::$providers[$id] ?? null;
        if ($existing !== null) {
            if ($existing::class === $provider::class) {
                return;
            }

            throw new RuntimeException(
                'Провайдер административного поиска уже зарегистрирован: '
                . $id
            );
        }

        self::$providers[$id] = $provider;
    }

    /**
     * @return list<AdminSearchProvider>
     */
    public static function providers(): array
    {
        $providers = self::$providers;
        ksort($providers, SORT_STRING);

        return array_values($providers);
    }
}
