<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

interface EducationPlatformHttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function getJson(string $url, array $headers = []): array;

    /**
     * @param array<string,string|int|float> $payload
     * @param array<string,string> $headers
     * @return array{status:int,body:string,json:array<string,mixed>|null}
     */
    public function postForm(string $url, array $payload, array $headers = []): array;
}
