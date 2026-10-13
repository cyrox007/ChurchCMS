<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

/**
 * Проверяет техническое качество полнотекстового RSS до передачи Mail.
 * Проверка не означает, что формат уже подтверждён партнёром.
 */
final class NewsMailFeedValidator
{
    /**
     * @return list<array{level:string,code:string,message:string,item:?int}>
     */
    public function validate(
        string $xml,
        string $contentType = 'application/rss+xml; charset=utf-8',
    ): array {
        $issues = (new Smi2FeedValidator())->validate(
            $xml,
            $contentType,
        );

        return array_map(
            static function (array $issue): array {
                $issue['message'] = str_replace(
                    'партнёрского контура СМИ2',
                    'технического фида Новости Mail',
                    (string) ($issue['message'] ?? ''),
                );
                return $issue;
            },
            $issues,
        );
    }
}
