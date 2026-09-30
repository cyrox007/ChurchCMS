<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

interface ChannelHttpClient
{
    /**
     * @param array<string,string|int|float|bool|null> $query
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function getJson(
        string $url,
        array $query = [],
        array $headers = [],
    ): array;

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function postJson(
        string $url,
        array $payload,
        array $headers = [],
    ): array;

    /**
     * @param array<string,string|int|float> $payload
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function postForm(
        string $url,
        array $payload,
        array $headers = [],
    ): array;
}
