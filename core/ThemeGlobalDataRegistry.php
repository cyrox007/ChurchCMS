<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class ThemeGlobalDataRegistry
{
    /** @var array<string,ThemeGlobalDataProvider> */
    private static array $providers = [];

    public static function register(
        string $key,
        ThemeGlobalDataProvider $provider,
    ): void {
        $key = trim($key);

        if (
            preg_match(
                '/^[a-z][a-z0-9_.-]{1,63}$/D',
                $key,
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Некорректный ключ глобальных данных темы.'
            );
        }

        self::$providers[$key] = $provider;
    }

    /**
     * @return array<string,mixed>
     */
    public static function data(): array
    {
        $result = [];

        foreach (self::$providers as $provider) {
            try {
                $data = $provider->data();

                if (is_array($data)) {
                    $result = array_replace(
                        $result,
                        $data,
                    );
                }
            } catch (\Throwable $error) {
                error_log(
                    'ChurchCMS theme global data: '
                    . $error->getMessage()
                );
            }
        }

        return $result;
    }
}
