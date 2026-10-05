<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Donations;

use InvalidArgumentException;

final class DonationProviderRegistry
{
    /** @var array<string,DonationProvider> */
    private static array $providers = [];

    public static function register(DonationProvider $provider): void
    {
        $id = trim($provider->id());
        $label = trim($provider->label());
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Некорректный идентификатор провайдера пожертвований.');
        }
        if ($label === '' || mb_strlen($label) > 120) {
            throw new InvalidArgumentException('Некорректное название провайдера пожертвований.');
        }
        if (isset(self::$providers[$id])) {
            throw new InvalidArgumentException('Провайдер пожертвований уже зарегистрирован: ' . $id);
        }

        self::$providers[$id] = $provider;
    }

    public static function provider(string $id): ?DonationProvider
    {
        return self::$providers[trim($id)] ?? null;
    }

    /** @return list<array{id:string,label:string}> */
    public static function available(): array
    {
        $result = [];
        foreach (self::$providers as $provider) {
            $result[] = [
                'id' => $provider->id(),
                'label' => $provider->label(),
            ];
        }

        usort($result, static fn(array $left, array $right): int => strcmp($left['label'], $right['label']));
        return $result;
    }
}
