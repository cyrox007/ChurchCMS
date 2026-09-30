<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class ChannelWebhookRequest
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public readonly string $rawBody,
        private readonly array $headers = [],
    ) {
    }

    public function header(
        string $name,
        ?string $default = null,
    ): ?string {
        $normalized = strtolower(
            str_replace('_', '-', trim($name)),
        );

        return $this->headers[$normalized] ?? $default;
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
