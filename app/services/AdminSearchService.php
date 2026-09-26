<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Request;

final class AdminSearchService
{
    public const MIN_QUERY_LENGTH = 2;
    public const MAX_QUERY_LENGTH = 100;
    private const RESULTS_PER_PROVIDER = 8;

    /**
     * @return array{
     *     query:string,
     *     valid:bool,
     *     groups:list<array{
     *         id:string,
     *         label:string,
     *         results:list<array{
     *             title:string,
     *             description:string,
     *             route:string,
     *             route_params:array<string,string>
     *         }>
     *     }>
     * }
     */
    public static function search(Request $request): array
    {
        $query = self::query($request);

        if (!self::validQuery($query)) {
            return [
                'query' => $query,
                'valid' => false,
                'groups' => [],
            ];
        }

        $groups = [];

        foreach (AdminSearchRegistry::providers() as $provider) {
            if (!AdminAuthorization::can($request, $provider->permission())) {
                continue;
            }

            $results = $provider->search(
                $query,
                self::RESULTS_PER_PROVIDER,
            );

            if ($results === []) {
                continue;
            }

            $groups[] = [
                'id' => $provider->id(),
                'label' => $provider->label(),
                'results' => $results,
            ];
        }

        return [
            'query' => $query,
            'valid' => true,
            'groups' => $groups,
        ];
    }

    public static function query(Request $request): string
    {
        $raw = $request->get('q', '');

        if (!is_scalar($raw)) {
            return '';
        }

        $query = trim((string) $raw);

        if (self::length($query) > self::MAX_QUERY_LENGTH) {
            $query = self::slice($query, self::MAX_QUERY_LENGTH);
        }

        return $query;
    }

    private static function validQuery(string $query): bool
    {
        $length = self::length($query);

        return $length >= self::MIN_QUERY_LENGTH
            && $length <= self::MAX_QUERY_LENGTH;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private static function slice(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
