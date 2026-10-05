<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\PrayerRequests;

use InvalidArgumentException;

final class PrayerRequestProviderRegistry
{
    /** @var array<string,PrayerRequestProvider> */
    private static array $providers = [];

    public static function register(PrayerRequestProvider $provider): void
    {
        $id = trim($provider->id());
        $label = trim($provider->label());
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $id) !== 1) {
            throw new InvalidArgumentException('Некорректный идентификатор провайдера записок.');
        }
        if ($label === '' || mb_strlen($label) > 120) {
            throw new InvalidArgumentException('Некорректное название провайдера записок.');
        }
        if (isset(self::$providers[$id])) {
            throw new InvalidArgumentException('Провайдер записок уже зарегистрирован: ' . $id);
        }

        self::validateServices($provider->services());
        self::$providers[$id] = $provider;
    }

    public static function provider(string $id): ?PrayerRequestProvider
    {
        return self::$providers[trim($id)] ?? null;
    }

    /** @return list<array{id:string,label:string,services:list<array{code:string,label:string}>}> */
    public static function available(): array
    {
        $result = [];
        foreach (self::$providers as $provider) {
            $result[] = [
                'id' => $provider->id(),
                'label' => $provider->label(),
                'services' => $provider->services(),
            ];
        }
        usort($result, static fn(array $left, array $right): int => strcmp($left['label'], $right['label']));
        return $result;
    }

    /** @param list<array{code:string,label:string}> $services */
    private static function validateServices(array $services): void
    {
        $seen = [];
        if ($services === []) {
            throw new InvalidArgumentException('Провайдер записок не объявил доступные виды заявок.');
        }

        foreach ($services as $service) {
            $code = trim((string) ($service['code'] ?? ''));
            $label = trim((string) ($service['label'] ?? ''));
            if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $code) !== 1 || $label === '' || mb_strlen($label) > 120) {
                throw new InvalidArgumentException('Провайдер записок объявил некорректный вид заявки.');
            }
            if (isset($seen[$code])) {
                throw new InvalidArgumentException('Провайдер записок объявил дублирующий вид заявки.');
            }
            $seen[$code] = true;
        }
    }
}
