<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Donations;

use InvalidArgumentException;

final readonly class DonationCheckout
{
    public function __construct(
        public string $providerId,
        public string $externalId,
        public string $redirectUrl,
    ) {
        if (trim($providerId) === '' || trim($externalId) === '') {
            throw new InvalidArgumentException('Провайдер вернул неполный идентификатор операции.');
        }

        $parts = parse_url($redirectUrl);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || trim((string) ($parts['host'] ?? '')) === '') {
            throw new InvalidArgumentException('Провайдер должен вернуть безопасный HTTPS-адрес платёжной страницы.');
        }
    }
}
