<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\SecretVault;
use InvalidArgumentException;
use RuntimeException;

final class SocialConnectionService
{
    public function __construct(
        private readonly SocialConnectionRepository $connections,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            SocialConnectionRepository::fromDatabase(),
        );
    }

    /**
     * @return list<array{
     *     id:string,
     *     label:string,
     *     capabilities:list<string>,
     *     can_test:bool
     * }>
     */
    public function availableAdapters(): array
    {
        $result = [];

        foreach (ChannelAdapterRegistry::all() as $id => $adapter) {
            $capabilities = array_values(
                array_unique($adapter->capabilities())
            );
            sort($capabilities, SORT_STRING);

            $result[] = [
                'id' => $id,
                'label' => trim($adapter->label()) !== ''
                    ? trim($adapter->label())
                    : $id,
                'capabilities' => $capabilities,
                'can_test' => $adapter instanceof ChannelConnectionTester,
            ];
        }

        usort(
            $result,
            static fn(array $left, array $right): int =>
                [$left['label'], $left['id']]
                <=> [$right['label'], $right['id']],
        );

        return $result;
    }

    public function create(
        string $provider,
        string $name,
        string $targetRef,
        string $credentials,
        bool $outboundEnabled,
        bool $inboundEnabled,
        string $connectionKind = 'social',
        string $inboundPolicy = 'review',
    ): SocialConnection {
        $provider = SocialProvider::normalize($provider);
        $name = trim($name);
        $targetRef = trim($targetRef);
        $credentials = trim($credentials);
        $connectionKind = strtolower(trim($connectionKind));
        $inboundPolicy = strtolower(trim($inboundPolicy));

        $this->assertLength($name, 1, 120, 'Название подключения');
        $this->assertLength($targetRef, 1, 255, 'Идентификатор канала');
        $this->assertLength($credentials, 1, 65535, 'Секрет подключения');

        if (
            preg_match('/^[a-z][a-z0-9_.-]{1,31}$/D', $connectionKind) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный тип подключения.'
            );
        }

        if (!in_array($inboundPolicy, ['review', 'disabled'], true)) {
            throw new InvalidArgumentException(
                'Некорректная политика входящих материалов.'
            );
        }

        if (!$outboundEnabled && !$inboundEnabled) {
            throw new InvalidArgumentException(
                'Включите хотя бы одно направление обмена.'
            );
        }

        $adapter = ChannelAdapterRegistry::get($provider);
        if ($adapter === null) {
            throw new InvalidArgumentException(
                'Выбранный адаптер внешнего канала недоступен.'
            );
        }

        if (!$adapter instanceof ChannelConnectionTester) {
            throw new RuntimeException(
                'Адаптер не поддерживает безопасную проверку подключения.'
            );
        }

        $test = $adapter->testConnection(
            $targetRef,
            $credentials,
            [],
        );

        if (!$test->success) {
            throw new RuntimeException(
                'Проверка подключения внешнего канала не пройдена.'
            );
        }

        $requiresActivation = $inboundEnabled
            && $adapter instanceof ChannelConnectionActivator;

        $connection = $this->connections->create(
            provider: $provider,
            name: $name,
            targetRef: $targetRef,
            tokenEncrypted: SecretVault::encrypt($credentials),
            settings: [],
            enabled: !$requiresActivation,
            outboundEnabled: $outboundEnabled,
            inboundEnabled: $inboundEnabled,
            inboundPolicy: $inboundEnabled ? $inboundPolicy : 'disabled',
            connectionKind: $connectionKind,
        );

        if (!$requiresActivation) {
            return $connection;
        }

        try {
            $adapter->activateConnection(
                $connection,
                $credentials,
                $this->webhookUrl($connection),
            );

            if (!$this->connections->setEnabled(
                $connection->id,
                true,
            )) {
                throw new RuntimeException(
                    'Не удалось активировать локальное подключение.'
                );
            }
        } catch (\Throwable $error) {
            $this->connections->deleteById(
                $connection->id,
            );

            throw new RuntimeException(
                'Не удалось зарегистрировать входящий webhook у провайдера.',
                0,
                $error,
            );
        }

        $activated = $this->connections->findById(
            $connection->id,
        );
        if ($activated === null || !$activated->enabled) {
            throw new RuntimeException(
                'Активированное подключение не удалось перечитать.'
            );
        }

        return $activated;
    }

    private function webhookUrl(
        SocialConnection $connection,
    ): string {
        $baseUrl = trim((string) Config::get(
            'syndication.site_url',
            Config::get('app.url', ''),
        ));

        $parts = parse_url($baseUrl);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new RuntimeException(
                'Для входящего webhook требуется канонический HTTPS URL сайта.'
            );
        }

        $path = Router::getInstance()->url(
            'external_channels_webhook',
            [
                'connectionPublicId' =>
                    $connection->publicId,
            ],
        );

        return rtrim($baseUrl, '/')
            . $path;
    }

    private function assertLength(
        string $value,
        int $min,
        int $max,
        string $label,
    ): void {
        $length = strlen($value);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException(
                $label . ' заполнен некорректно.'
            );
        }
    }
}
