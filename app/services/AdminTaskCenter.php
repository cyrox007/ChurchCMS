<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Request;

final class AdminTaskCenter
{
    private const REQUEST_CACHE_KEY = 'admin.task_center.summary';
    private const TASKS_PER_PROVIDER = 5;

    /**
     * @return array{
     *     count:int,
     *     tasks:list<array{
     *         provider:string,
     *         id:string,
     *         title:string,
     *         description:string,
     *         count:int,
     *         severity:string,
     *         route:string,
     *         route_params:array<string,string>
     *     }>
     * }
     */
    public static function summary(Request $request): array
    {
        $cached = $request->attribute(self::REQUEST_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $tasks = [];
        $count = 0;

        foreach (AdminTaskRegistry::providers() as $provider) {
            if (!AdminAuthorization::can($request, $provider->permission())) {
                continue;
            }

            foreach ($provider->tasks(self::TASKS_PER_PROVIDER) as $task) {
                $normalized = self::normalizeTask($provider->id(), $task);
                if ($normalized === null) {
                    continue;
                }

                $tasks[] = $normalized;
                $count += $normalized['count'];
            }
        }

        usort(
            $tasks,
            static fn(array $left, array $right): int =>
                [self::severityRank($left['severity']), $left['title']]
                <=> [self::severityRank($right['severity']), $right['title']],
        );

        $summary = [
            'count' => min(9999, $count),
            'tasks' => $tasks,
        ];

        $request->setAttribute(self::REQUEST_CACHE_KEY, $summary);
        return $summary;
    }

    /**
     * @param array<string,mixed> $task
     * @return array{
     *     provider:string,
     *     id:string,
     *     title:string,
     *     description:string,
     *     count:int,
     *     severity:string,
     *     route:string,
     *     route_params:array<string,string>
     * }|null
     */
    private static function normalizeTask(string $provider, array $task): ?array
    {
        $id = trim((string) ($task['id'] ?? ''));
        $title = trim((string) ($task['title'] ?? ''));
        $count = max(0, (int) ($task['count'] ?? 0));

        if (
            preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $id) !== 1
            || $title === ''
            || $count < 1
        ) {
            return null;
        }

        $severity = (string) ($task['severity'] ?? 'info');
        if (!in_array($severity, ['error', 'warning', 'info'], true)) {
            $severity = 'info';
        }

        $rawParams = $task['route_params'] ?? [];
        $routeParams = [];

        if (is_array($rawParams)) {
            foreach ($rawParams as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $routeParams[$key] = (string) $value;
                }
            }
        }

        return [
            'provider' => $provider,
            'id' => $id,
            'title' => $title,
            'description' => trim((string) ($task['description'] ?? '')),
            'count' => $count,
            'severity' => $severity,
            'route' => trim((string) ($task['route'] ?? '')),
            'route_params' => $routeParams,
        ];
    }

    private static function severityRank(string $severity): int
    {
        return match ($severity) {
            'error' => 0,
            'warning' => 1,
            default => 2,
        };
    }
}
