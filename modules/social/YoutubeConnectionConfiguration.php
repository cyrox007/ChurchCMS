<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use InvalidArgumentException;

final class YoutubeConnectionConfiguration
{
    /**
     * @param array<string,mixed> $input
     * @return array{credentials:string,settings:array<string,string>}
     */
    public static function fromInput(
        array $input,
        bool $outboundEnabled,
    ): array {
        $apiKey = self::value($input, 'api_key');
        $accessToken = self::value($input, 'access_token');
        $refreshToken = self::value($input, 'refresh_token');
        $clientId = self::value($input, 'client_id');
        $clientSecret = self::value($input, 'client_secret');

        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'Для YouTube укажите API key.'
            );
        }

        self::assertRefreshCredentials(
            $refreshToken,
            $clientId,
            $clientSecret,
        );

        if (
            $outboundEnabled
            && $accessToken === ''
            && $refreshToken === ''
        ) {
            throw new InvalidArgumentException(
                'Для исходящей загрузки YouTube укажите access token '
                . 'или refresh token вместе с client ID и client secret.'
            );
        }

        $credentials = [
            'api_key' => $apiKey,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        return [
            'credentials' => json_encode(
                $credentials,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES,
            ),
            'settings' => self::settings($input),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,string>
     */
    public static function settings(array $input): array
    {
        $privacy = strtolower(
            self::value($input, 'privacy', 'unlisted')
        );
        if (!in_array(
            $privacy,
            ['private', 'unlisted', 'public'],
            true,
        )) {
            throw new InvalidArgumentException(
                'Некорректная приватность видео YouTube.'
            );
        }

        $categoryId = self::value(
            $input,
            'category_id',
            '22',
        );
        if (preg_match('/^\d{1,4}$/D', $categoryId) !== 1) {
            throw new InvalidArgumentException(
                'Категория YouTube должна быть числовым ID.'
            );
        }

        return [
            'youtube_privacy' => $privacy,
            'youtube_category_id' => $categoryId,
        ];
    }

    private static function assertRefreshCredentials(
        string $refreshToken,
        string $clientId,
        string $clientSecret,
    ): void {
        $provided = [
            $refreshToken !== '',
            $clientId !== '',
            $clientSecret !== '',
        ];
        $count = count(array_filter($provided));

        if ($count !== 0 && $count !== 3) {
            throw new InvalidArgumentException(
                'Refresh token YouTube требует одновременно client ID '
                . 'и client secret.'
            );
        }
    }

    /** @param array<string,mixed> $input */
    private static function value(
        array $input,
        string $key,
        string $default = '',
    ): string {
        if (!array_key_exists($key, $input)) {
            return $default;
        }

        return trim((string) $input[$key]);
    }
}
