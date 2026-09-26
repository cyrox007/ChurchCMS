<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use InvalidArgumentException;
use RuntimeException;

final class AdminNavigationRegistry
{
    /**
     * @var array<string,array{
     *     id:string,
     *     label:string,
     *     route:string,
     *     permission:string,
     *     priority:int
     * }>
     */
    private static array $entries = [];

    public static function register(
        string $id,
        string $label,
        string $route,
        string $permission,
        int $priority = 100,
    ): void {
        $entry = [
            'id' => trim($id),
            'label' => trim($label),
            'route' => trim($route),
            'permission' => trim($permission),
            'priority' => $priority,
        ];

        self::validate($entry);

        $existing = self::$entries[$entry['id']] ?? null;
        if ($existing !== null) {
            if ($existing === $entry) {
                return;
            }

            throw new RuntimeException(
                'Раздел административной навигации уже зарегистрирован: '
                . $entry['id']
            );
        }

        self::$entries[$entry['id']] = $entry;
    }

    /**
     * @return list<array{
     *     id:string,
     *     label:string,
     *     route:string,
     *     permission:string,
     *     priority:int
     * }>
     */
    public static function entries(): array
    {
        $entries = array_values(self::$entries);

        usort(
            $entries,
            static fn(array $left, array $right): int =>
                [$left['priority'], $left['id']]
                <=> [$right['priority'], $right['id']],
        );

        return $entries;
    }

    /**
     * @param array{
     *     id:string,
     *     label:string,
     *     route:string,
     *     permission:string,
     *     priority:int
     * } $entry
     */
    private static function validate(array $entry): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $entry['id']) !== 1) {
            throw new InvalidArgumentException(
                'Некорректный идентификатор раздела административной навигации.'
            );
        }

        foreach (['label', 'route', 'permission'] as $field) {
            if ($entry[$field] === '') {
                throw new InvalidArgumentException(
                    'Раздел административной навигации заполнен не полностью.'
                );
            }
        }

        if ($entry['priority'] < 0 || $entry['priority'] > 10000) {
            throw new InvalidArgumentException(
                'Приоритет раздела административной навигации вне допустимого диапазона.'
            );
        }
    }
}
