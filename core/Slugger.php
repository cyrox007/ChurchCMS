<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class Slugger
{
    private const RU = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];

    public static function fromText(string $value, string $fallback = 'publication'): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $value = strtr($value, self::RU);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        if ($value === '') {
            $value = $fallback;
        }

        return substr($value, 0, 180);
    }
}
