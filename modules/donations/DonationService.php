<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Donations;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use InvalidArgumentException;
use PDO;

final class DonationService
{
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function createCheckout(
        string $providerId,
        string $organizationPublicId,
        int $amountMinor,
        string $currency,
        string $purpose,
        string $returnPath,
        string $siteKey = 'default',
    ): DonationCheckout {
        $provider = DonationProviderRegistry::provider($providerId);
        if ($provider === null) {
            throw new InvalidArgumentException('Провайдер пожертвований недоступен.');
        }

        $siteKey = self::siteKey($siteKey);
        $organization = $this->organizations->findByPublicId(trim($organizationPublicId), $siteKey);
        if ($organization === null || $organization->status !== 'active') {
            throw new InvalidArgumentException('Организация-получатель недоступна.');
        }

        if ($amountMinor < 1 || $amountMinor > 100_000_000_00) {
            throw new InvalidArgumentException('Некорректная сумма пожертвования.');
        }

        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new InvalidArgumentException('Некорректная валюта пожертвования.');
        }

        $purpose = trim($purpose);
        if ($purpose === '' || mb_strlen($purpose) > 255) {
            throw new InvalidArgumentException('Укажите назначение пожертвования.');
        }

        $returnPath = self::returnPath($returnPath);
        $checkout = $provider->createCheckout(new DonationCheckoutRequest(
            siteKey: $siteKey,
            organizationPublicId: $organization->publicId,
            amountMinor: $amountMinor,
            currency: $currency,
            purpose: $purpose,
            returnPath: $returnPath,
        ));

        if ($checkout->providerId !== $provider->id()) {
            throw new InvalidArgumentException('Провайдер вернул идентификатор другого адаптера.');
        }

        return $checkout;
    }

    private static function siteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if ($siteKey === '' || strlen($siteKey) > 64) {
            throw new InvalidArgumentException('Некорректный site_key.');
        }
        return $siteKey;
    }

    private static function returnPath(string $returnPath): string
    {
        $returnPath = trim($returnPath);
        if (
            $returnPath === ''
            || !str_starts_with($returnPath, '/')
            || str_starts_with($returnPath, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $returnPath) === 1
        ) {
            throw new InvalidArgumentException('Некорректный путь возврата после пожертвования.');
        }

        $parts = parse_url($returnPath);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            throw new InvalidArgumentException('Путь возврата должен быть локальным.');
        }

        return $returnPath;
    }
}
