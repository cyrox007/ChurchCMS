<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\DistributionWebhooks;

interface DistributionWebhookTransport
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}
     */
    public function post(string $url, string $body, array $headers): array;
}
