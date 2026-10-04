<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\DatabaseManager;
use PDO;
use RuntimeException;
use Throwable;

final class YoutubeAdminConnectionCreator
{
    public function __construct(
        private readonly SocialConnectionService $connections,
        private readonly SocialConnectionRepository $repository,
        private readonly PDO $pdo,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            SocialConnectionService::fromDatabase(),
            SocialConnectionRepository::fromDatabase(),
            DatabaseManager::getInstance()->connection(),
        );
    }

    /** @param array<string,mixed> $input */
    public function create(array $input): SocialConnection
    {
        $outboundEnabled = self::boolValue(
            $input['outbound_enabled'] ?? false,
        );
        $inboundEnabled = self::boolValue(
            $input['inbound_enabled'] ?? false,
        );
        $configuration = YoutubeConnectionConfiguration::fromInput(
            [
                'api_key' => $input['api_key'] ?? '',
                'access_token' => $input['access_token'] ?? '',
                'refresh_token' => $input['refresh_token'] ?? '',
                'client_id' => $input['client_id'] ?? '',
                'client_secret' => $input['client_secret'] ?? '',
                'privacy' => $input['privacy'] ?? 'unlisted',
                'category_id' => $input['category_id'] ?? '22',
            ],
            $outboundEnabled,
        );

        $connection = $this->connections->create(
            provider: 'youtube',
            name: (string) ($input['name'] ?? ''),
            targetRef: (string) ($input['target_ref'] ?? ''),
            credentials: $configuration['credentials'],
            outboundEnabled: $outboundEnabled,
            inboundEnabled: $inboundEnabled,
            connectionKind: 'video',
        );

        try {
            $this->storeSettings(
                $connection->id,
                $configuration['settings'],
            );
        } catch (Throwable $error) {
            $this->repository->deleteById($connection->id);

            throw new RuntimeException(
                'Настройки YouTube не удалось сохранить.',
                0,
                $error,
            );
        }

        $stored = $this->repository->findById($connection->id);
        if ($stored === null) {
            throw new RuntimeException(
                'Подключение YouTube не удалось перечитать.'
            );
        }

        return $stored;
    }

    /** @param array<string,string> $settings */
    private function storeSettings(
        int $connectionId,
        array $settings,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE social_connections
             SET settings_json = :settings_json,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'settings_json' => json_encode(
                $settings,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $connectionId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'Строка подключения YouTube не обновлена.'
            );
        }
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'on'],
            true,
        );
    }
}
