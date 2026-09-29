<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

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

        return $this->connections->create(
            provider: $provider,
            name: $name,
            targetRef: $targetRef,
            tokenEncrypted: SecretVault::encrypt($credentials),
            settings: [],
            enabled: true,
            outboundEnabled: $outboundEnabled,
            inboundEnabled: $inboundEnabled,
            inboundPolicy: $inboundEnabled ? $inboundPolicy : 'disabled',
            connectionKind: $connectionKind,
        );
    }

    private function assertLength(
        string $value,
        int $min,
        int $max,
        string $label,
    ): void {
        $length = mb_strlen($value);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException(
                $label . ' заполнен некорректно.'
            );
        }
    }
}
