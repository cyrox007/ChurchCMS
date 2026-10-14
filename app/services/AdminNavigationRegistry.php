<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use InvalidArgumentException;
use RuntimeException;

final class AdminNavigationRegistry
{
    /** @var list<string> */
    private const EDUCATION_PREFIXES = [
        'education_',
        'education-',
    ];

    /** @var list<string> */
    private const STRUCTURE_IDS = [
        'organizations',
        'organization',
        'federation',
        'relations',
        'connections',
        'structure',
    ];

    /** @var list<string> */
    private const PARISH_ONLY_IDS = [
        'ministries',
        'shrines',
        'saints',
        'sunday_school',
        'sunday-school',
    ];

    /** @var list<string> */
    private const ADVANCED_INTEGRATION_MARKERS = [
        'webhook',
        'syndication',
        'rambler',
        'smi2',
        'news_mail',
        'news-mail',
        'external',
        'channel',
    ];

    /** @var array<string,int> */
    private const GROUP_ORDER = [
        'Контент' => 10,
        'Сайт' => 20,
        'Жизнь организации' => 30,
        'Образование' => 40,
        'Медиа' => 50,
        'Структура' => 60,
        'Интеграции' => 70,
        'Система' => 80,
        'Дополнительно' => 90,
    ];

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
                . $entry['id'],
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
     * Возвращает навигацию, соответствующую назначению установленного сайта.
     * Права пользователя по-прежнему проверяются отдельно в AdminShell.
     *
     * @return list<array{
     *     id:string,
     *     label:string,
     *     route:string,
     *     permission:string,
     *     priority:int,
     *     group:string
     * }>
     */
    public static function entriesForProfile(string $profile): array
    {
        $profile = self::normalizeProfile($profile);
        $result = [];

        foreach (self::entries() as $entry) {
            if (!self::visibleForProfile($entry['id'], $profile)) {
                continue;
            }

            $entry['group'] = self::groupFor($entry['id']);
            $result[] = $entry;
        }

        usort(
            $result,
            static function (array $left, array $right): int {
                $leftGroup = self::GROUP_ORDER[$left['group']] ?? 999;
                $rightGroup = self::GROUP_ORDER[$right['group']] ?? 999;

                return [$leftGroup, $left['priority'], $left['id']]
                    <=> [$rightGroup, $right['priority'], $right['id']];
            },
        );

        return $result;
    }

    public static function profileLabel(string $profile): string
    {
        return match (self::normalizeProfile($profile)) {
            'small-parish' => 'Небольшой приход',
            'parish' => 'Приход',
            'cathedral' => 'Собор',
            'monastery' => 'Монастырь',
            'deanery' => 'Благочиние',
            'diocese' => 'Епархия',
            'metropolia' => 'Митрополия',
            'education' => 'Учебное заведение',
            'mixed' => 'Смешанный профиль',
            default => 'Организация',
        };
    }

    private static function normalizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));

        return in_array(
            $profile,
            [
                'small-parish',
                'parish',
                'cathedral',
                'monastery',
                'deanery',
                'diocese',
                'metropolia',
                'education',
                'mixed',
                'organization',
            ],
            true,
        ) ? $profile : 'organization';
    }

    private static function visibleForProfile(
        string $id,
        string $profile,
    ): bool {
        if (in_array($profile, ['mixed', 'organization'], true)) {
            return true;
        }

        if (self::isEducationId($id)) {
            return $profile === 'education';
        }

        if ($profile === 'education') {
            return !in_array($id, self::PARISH_ONLY_IDS, true)
                && !in_array($id, self::STRUCTURE_IDS, true)
                && !self::isAdvancedIntegrationId($id);
        }

        if (
            in_array(
                $profile,
                ['small-parish', 'parish', 'cathedral', 'monastery'],
                true,
            )
        ) {
            if (in_array($id, self::STRUCTURE_IDS, true)) {
                return false;
            }

            return !self::isAdvancedIntegrationId($id);
        }

        return true;
    }

    private static function isEducationId(string $id): bool
    {
        foreach (self::EDUCATION_PREFIXES as $prefix) {
            if (str_starts_with($id, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function isAdvancedIntegrationId(string $id): bool
    {
        return self::hasMarker(
            $id,
            self::ADVANCED_INTEGRATION_MARKERS,
        );
    }

    /** @param list<string> $markers */
    private static function hasMarker(string $id, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_contains($id, $marker)) {
                return true;
            }
        }

        return false;
    }

    private static function groupFor(string $id): string
    {
        if (self::isEducationId($id)) {
            return 'Образование';
        }

        if (in_array($id, self::STRUCTURE_IDS, true)) {
            return 'Структура';
        }

        if (self::isAdvancedIntegrationId($id)) {
            return 'Интеграции';
        }

        if (
            str_contains($id, 'media')
            || str_contains($id, 'gallery')
            || str_contains($id, 'youtube')
        ) {
            return 'Медиа';
        }

        if (
            in_array(
                $id,
                [
                    'people',
                    'worship',
                    'events',
                    'ministries',
                    'shrines',
                    'saints',
                    'sunday_school',
                    'sunday-school',
                ],
                true,
            )
        ) {
            return 'Жизнь организации';
        }

        if (
            str_contains($id, 'system')
            || str_contains($id, 'operation')
            || str_contains($id, 'state')
        ) {
            return 'Система';
        }

        if (
            str_contains($id, 'page')
            || str_contains($id, 'menu')
            || str_contains($id, 'redirect')
            || str_contains($id, 'document')
        ) {
            return 'Сайт';
        }

        if (
            in_array(
                $id,
                [
                    'publications',
                    'publication_rubrics',
                    'publication-rubrics',
                    'comments',
                    'library',
                ],
                true,
            )
        ) {
            return 'Контент';
        }

        return 'Дополнительно';
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
                'Некорректный идентификатор раздела административной навигации.',
            );
        }

        foreach (['label', 'route', 'permission'] as $field) {
            if ($entry[$field] === '') {
                throw new InvalidArgumentException(
                    'Раздел административной навигации заполнен не полностью.',
                );
            }
        }

        if ($entry['priority'] < 0 || $entry['priority'] > 10000) {
            throw new InvalidArgumentException(
                'Приоритет раздела административной навигации вне допустимого диапазона.',
            );
        }
    }
}
