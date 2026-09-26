<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

interface AdminTaskProvider
{
    public function id(): string;

    public function permission(): string;

    /**
     * @return list<array{
     *     id:string,
     *     title:string,
     *     description:string,
     *     count:int,
     *     severity:string,
     *     route:string,
     *     route_params:array<string,string>
     * }>
     */
    public function tasks(int $limit): array;
}
