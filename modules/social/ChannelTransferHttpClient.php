<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

interface ChannelTransferHttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null,
     *     headers:array<string,string>
     * }
     */
    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
    ): array;
}
