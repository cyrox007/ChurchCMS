<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use InvalidArgumentException;
use Throwable;

final class PublicHomeStreamRegistry
{
    /** @var array<string,PublicHomeStreamProvider> */
    private static array $providers = [];

    public static function register(PublicHomeStreamProvider $provider): void
    {
        $id = trim($provider->id());
        $label = trim($provider->label());

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Некорректный идентификатор потока главной страницы.');
        }
        if ($label === '' || mb_strlen($label) > 120) {
            throw new InvalidArgumentException('Некорректное название потока главной страницы.');
        }
        if (isset(self::$providers[$id])) {
            throw new InvalidArgumentException('Поток главной страницы уже зарегистрирован: ' . $id);
        }

        self::$providers[$id] = $provider;
    }

    /**
     * @return list<array{id:string,label:string,items:list<array<string,mixed>>}>
     */
    public static function collect(string $siteKey = 'default', int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $providers = array_values(self::$providers);
        usort(
            $providers,
            static fn(PublicHomeStreamProvider $left, PublicHomeStreamProvider $right): int =>
                $left->priority() <=> $right->priority(),
        );

        $streams = [];
        foreach ($providers as $provider) {
            try {
                $items = $provider->items($siteKey, $limit);
            } catch (Throwable $error) {
                error_log(
                    'ChurchCMS главная страница: поток '
                    . $provider->id()
                    . ' недоступен: '
                    . $error->getMessage(),
                );
                continue;
            }

            if ($items === []) {
                continue;
            }

            $streams[] = [
                'id' => $provider->id(),
                'label' => $provider->label(),
                'items' => array_values(array_slice($items, 0, $limit)),
            ];
        }

        return $streams;
    }
}
