<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class SiteProfileCatalog
{
    /**
     * @return array<string,array{label:string,organization_type:string}>
     */
    public static function all(): array
    {
        return [
            'small-parish' => [
                'label' => 'Небольшой приход',
                'organization_type' => 'parish',
            ],
            'parish' => [
                'label' => 'Приход / храм',
                'organization_type' => 'parish',
            ],
            'cathedral' => [
                'label' => 'Кафедральный собор',
                'organization_type' => 'cathedral',
            ],
            'monastery' => [
                'label' => 'Монастырь',
                'organization_type' => 'monastery',
            ],
            'deanery' => [
                'label' => 'Благочиние',
                'organization_type' => 'deanery',
            ],
            'diocese' => [
                'label' => 'Епархия',
                'organization_type' => 'diocese',
            ],
            'metropolia' => [
                'label' => 'Митрополия',
                'organization_type' => 'metropolia',
            ],
            'education' => [
                'label' => 'Духовная школа / семинария',
                'organization_type' => 'educational_institution',
            ],
            'mixed' => [
                'label' => 'Смешанный церковный сайт',
                'organization_type' => 'church_organization',
            ],
            'organization' => [
                'label' => 'Иная церковная организация',
                'organization_type' => 'church_organization',
            ],
        ];
    }

    public static function exists(string $profile): bool
    {
        return isset(self::all()[$profile]);
    }

    public static function organizationType(
        string $profile,
    ): ?string {
        $entry = self::all()[$profile] ?? null;

        return is_array($entry)
            ? (string) $entry['organization_type']
            : null;
    }

    public static function label(string $profile): ?string
    {
        $entry = self::all()[$profile] ?? null;

        return is_array($entry)
            ? (string) $entry['label']
            : null;
    }
}
