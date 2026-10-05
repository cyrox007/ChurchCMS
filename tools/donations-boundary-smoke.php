<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Modules\Donations\DonationCapability;
use ChurchCMS\Modules\Donations\DonationCheckout;
use ChurchCMS\Modules\Donations\DonationCheckoutRequest;
use ChurchCMS\Modules\Donations\DonationProvider;
use ChurchCMS\Modules\Donations\DonationProviderRegistry;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

final class SmokeDonationProvider implements DonationProvider
{
    public ?DonationCheckoutRequest $lastRequest = null;

    public function id(): string
    {
        return 'smoke-pay';
    }

    public function label(): string
    {
        return 'Тестовый платёжный провайдер';
    }

    public function createCheckout(DonationCheckoutRequest $request): DonationCheckout
    {
        $this->lastRequest = $request;
        return new DonationCheckout(
            providerId: $this->id(),
            externalId: 'payment-001',
            redirectUrl: 'https://payments.example.test/checkout/payment-001',
        );
    }
}

$capability = ModuleRuntimeLoader::capability('donations', 'donations.checkout');
if (!$capability instanceof DonationCapability) {
    fwrite(STDERR, "Capability пожертвований не зарегистрирована.\n");
    exit(1);
}

$provider = new SmokeDonationProvider();
DonationProviderRegistry::register($provider);
if (($capability->providers()[0]['id'] ?? '') !== 'smoke-pay') {
    fwrite(STDERR, "Зарегистрированный провайдер не виден через capability.\n");
    exit(1);
}

$organizations = OrganizationService::fromDatabase();
$siteKey = 'donations-smoke';
$root = $organizations->ensureSiteRoot('Тестовый приход пожертвований', 'parish', $siteKey);

$checkout = $capability->createCheckout(
    providerId: 'smoke-pay',
    organizationPublicId: $root->publicId,
    amountMinor: 150000,
    currency: 'rub',
    purpose: 'Помощь приходу',
    returnPath: '/donations/thanks?source=site',
    siteKey: $siteKey,
);

if (
    $checkout->externalId !== 'payment-001'
    || $provider->lastRequest === null
    || $provider->lastRequest->currency !== 'RUB'
    || $provider->lastRequest->organizationPublicId !== $root->publicId
    || $provider->lastRequest->returnPath !== '/donations/thanks?source=site'
) {
    fwrite(STDERR, "Провайдер получил некорректный запрос пожертвования.\n");
    exit(1);
}

$foreign = $organizations->ensureSiteRoot('Чужой приход', 'parish', 'donations-foreign');
$invalidCases = [
    ['organizationPublicId' => $foreign->publicId],
    ['amountMinor' => 0],
    ['currency' => 'RUBLE'],
    ['purpose' => ''],
    ['returnPath' => 'https://evil.example/steal'],
    ['returnPath' => '//evil.example/steal'],
    ['providerId' => 'missing-provider'],
];

foreach ($invalidCases as $change) {
    $arguments = [
        'providerId' => 'smoke-pay',
        'organizationPublicId' => $root->publicId,
        'amountMinor' => 10000,
        'currency' => 'RUB',
        'purpose' => 'Пожертвование',
        'returnPath' => '/donations/thanks',
        'siteKey' => $siteKey,
    ];
    foreach ($change as $key => $value) {
        $arguments[$key] = $value;
    }

    try {
        $capability->createCheckout(...$arguments);
        fwrite(STDERR, "Граница пожертвований приняла некорректный запрос: {$key}.\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

try {
    new DonationCheckout(
        providerId: 'smoke-pay',
        externalId: 'bad-payment',
        redirectUrl: 'http://payments.example.test/insecure',
    );
    fwrite(STDERR, "Результат пожертвования разрешил небезопасный redirect.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Donation integration boundary smoke OK\n";
