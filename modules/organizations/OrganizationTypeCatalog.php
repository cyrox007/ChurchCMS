<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

final class OrganizationTypeCatalog
{
    /**
     * @return array<string,string>
     */
    public static function all(): array
    {
        return [
            'metropolia' => 'Митрополия',
            'diocese' => 'Епархия',
            'deanery' => 'Благочиние',
            'parish' => 'Приход / храм',
            'cathedral' => 'Кафедральный собор',
            'monastery' => 'Монастырь',
            'chapel' => 'Часовня',
            'department' => 'Отдел',
            'commission' => 'Комиссия',
            'council' => 'Совет',
            'educational_institution' => 'Духовное учебное заведение',
            'church_organization' => 'Иная церковная организация',
        ];
    }

    public static function label(string $type): string
    {
        return self::all()[$type] ?? $type;
    }
}
