<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

interface AdminSearchProvider
{
    public function id(): string;

    public function label(): string;

    public function permission(): string;

    /**
     * @return list<array{
     *     title:string,
     *     description:string,
     *     route:string,
     *     route_params:array<string,string>
     * }>
     */
    public function search(string $query, int $limit): array;
}
