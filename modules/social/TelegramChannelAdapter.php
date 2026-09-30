<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class TelegramChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester
{
    private const API_BASE = 'https://api.telegram.org/';
    private const MAX_MESSAGE_LENGTH = 4096;

    public function __construct(
        private readonly ChannelHttpClient $http =
            new NativeHttpClient(),
    ) {
    }

    public function providerId(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::PUBLISH_TEXT,
            ChannelCapability::PUBLISH_LINK,
            ChannelCapability::IMPORT_POSTS,
            ChannelCapability::POLLING,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        $targetRef = trim($targetRef);
        $credentials = trim($credentials);

        if (
            !self::validToken($credentials)
            || $targetRef === ''
        ) {
            return new ChannelConnectionTestResult(
                false,
                'Проверьте токен бота и идентификатор канала.',
            );
        }

        try {
            $me = $this->api(
                $credentials,
                'getMe',
            );
            if (!is_array($me)) {
                return new ChannelConnectionTestResult(
                    false,
                    'Telegram не подтвердил токен бота.',
                );
            }

            $chat = $this->api(
                $credentials,
                'getChat',
                ['chat_id' => $targetRef],
            );
            if (!is_array($chat)) {
                return new ChannelConnectionTestResult(
                    false,
                    'Telegram не подтвердил целевой канал.',
                );
            }
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'Telegram не подтвердил подключение.',
            );
        }

        return new ChannelConnectionTestResult(
            true,
            'Бот и целевой канал доступны.',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        try {
            $result = $this->api(
                $credentials,
                'sendMessage',
                [
                    'chat_id' => $connection->targetRef,
                    'text' => self::outboundText($item),
                ],
                true,
            );
        } catch (Throwable $error) {
            return new ChannelPublishResult(
                false,
                error: self::safeError($error),
            );
        }

        if (!is_array($result)) {
            return new ChannelPublishResult(
                false,
                error: 'Telegram не подтвердил отправку сообщения.',
            );
        }

        $messageId = (int) ($result['message_id'] ?? 0);
        if ($messageId <= 0) {
            return new ChannelPublishResult(
                false,
                error: 'Telegram вернул ответ без message_id.',
            );
        }

        $remoteUrl = self::messageUrl(
            is_array($result['chat'] ?? null)
                ? $result['chat']
                : [],
            $messageId,
            $connection->targetRef,
        );

        return new ChannelPublishResult(
            true,
            remoteId: (string) $messageId,
            remoteUrl: $remoteUrl,
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        $limit = max(1, min(100, $limit));
        $query = [
            'limit' => $limit,
            'timeout' => 0,
            'allowed_updates' => json_encode(
                [
                    'message',
                    'edited_message',
                    'channel_post',
                    'edited_channel_post',
                ],
                JSON_THROW_ON_ERROR,
            ),
        ];

        if (
            $cursor !== null
            && preg_match('/^\d+$/D', $cursor) === 1
        ) {
            $query['offset'] = ((int) $cursor) + 1;
        }

        $result = $this->api(
            $credentials,
            'getUpdates',
            $query,
        );

        if (!is_array($result)) {
            throw new RuntimeException(
                'Telegram вернул некорректный список обновлений.'
            );
        }

        $items = [];
        $nextCursor = $cursor;

        foreach ($result as $update) {
            if (!is_array($update)) {
                continue;
            }

            $updateId = (int) ($update['update_id'] ?? 0);
            if ($updateId > 0) {
                $nextCursor = (string) max(
                    $updateId,
                    (int) ($nextCursor ?? 0),
                );
            }

            $item = self::inboundItem(
                $update,
                $connection->targetRef,
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return new ChannelPullBatch(
            $items,
            $nextCursor,
        );
    }

    /**
     * @param array<string,string|int|float|bool|null> $payload
     */
    private function api(
        string $token,
        string $method,
        array $payload = [],
        bool $post = false,
    ): mixed {
        $token = trim($token);
        if (!self::validToken($token)) {
            throw new RuntimeException(
                'Некорректный токен Telegram-бота.'
            );
        }

        $url = self::API_BASE
            . 'bot'
            . $token
            . '/'
            . $method;

        $response = $post
            ? $this->http->postJson(
                $url,
                $payload,
            )
            : $this->http->getJson(
                $url,
                $payload,
            );

        $json = $response['json'] ?? null;
        if (
            ($response['status'] ?? 0) < 200
            || ($response['status'] ?? 0) >= 300
            || !is_array($json)
            || ($json['ok'] ?? false) !== true
        ) {
            throw new RuntimeException(
                'Telegram Bot API отклонил запрос.'
            );
        }

        return $json['result'] ?? null;
    }

    private static function validToken(string $token): bool
    {
        return preg_match(
            '/^[0-9]+:[A-Za-z0-9_-]+$/D',
            trim($token),
        ) === 1;
    }

    private static function outboundText(
        ChannelOutboundItem $item,
    ): string {
        $text = trim($item->text);
        if ($text === '') {
            $text = trim($item->title);
        }

        $url = trim($item->canonicalUrl);
        $suffix = '';

        if (
            $url !== ''
            && !str_contains($text, $url)
        ) {
            $suffix = "\n\n" . $url;
        }

        $suffixLength = self::length($suffix);
        if ($suffixLength >= self::MAX_MESSAGE_LENGTH) {
            return self::slice(
                $suffix,
                0,
                self::MAX_MESSAGE_LENGTH,
            );
        }

        $text = self::slice(
            $text,
            0,
            self::MAX_MESSAGE_LENGTH - $suffixLength,
        );

        $result = trim($text . $suffix);

        return $result !== ''
            ? $result
            : self::slice(
                trim($item->title),
                0,
                self::MAX_MESSAGE_LENGTH,
            );
    }

    private static function inboundItem(
        array $update,
        string $targetRef,
    ): ?ChannelInboundItem {
        $message = null;
        $edited = false;

        foreach ([
            'channel_post' => false,
            'edited_channel_post' => true,
            'message' => false,
            'edited_message' => true,
        ] as $key => $isEdited) {
            if (is_array($update[$key] ?? null)) {
                $message = $update[$key];
                $edited = $isEdited;
                break;
            }
        }

        if (
            !is_array($message)
            || !is_array($message['chat'] ?? null)
            || !self::matchesTarget(
                $message['chat'],
                $targetRef,
            )
        ) {
            return null;
        }

        $chat = $message['chat'];
        $chatId = (string) ($chat['id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);

        if ($chatId === '' || $messageId <= 0) {
            return null;
        }

        $text = trim((string) (
            $message['text']
            ?? $message['caption']
            ?? ''
        ));

        return new ChannelInboundItem(
            remoteId: $chatId . ':' . $messageId,
            kind: self::messageKind($message),
            title: null,
            text: $text,
            canonicalUrl: self::messageUrl(
                $chat,
                $messageId,
                $targetRef,
            ),
            media: self::media($message),
            publishedAt: self::date(
                $message['date'] ?? null,
            ),
            updatedAt: self::date(
                $message['edit_date'] ?? null,
            ),
            payload: [
                'update_id' => (int) (
                    $update['update_id'] ?? 0
                ),
                'message_id' => $messageId,
                'chat_id' => $chatId,
                'chat_username' => isset($chat['username'])
                    ? (string) $chat['username']
                    : null,
                'edited' => $edited,
            ],
        );
    }

    private static function matchesTarget(
        array $chat,
        string $targetRef,
    ): bool {
        $targetRef = trim($targetRef);

        if (str_starts_with($targetRef, '@')) {
            $username = trim(
                (string) ($chat['username'] ?? '')
            );

            return $username !== ''
                && strtolower('@' . $username)
                    === strtolower($targetRef);
        }

        return (string) ($chat['id'] ?? '')
            === $targetRef;
    }

    private static function messageKind(array $message): string
    {
        if (is_array($message['video'] ?? null)) {
            return 'video';
        }

        if (is_array($message['photo'] ?? null)) {
            return 'image';
        }

        if (is_array($message['document'] ?? null)) {
            return 'document';
        }

        return 'post';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function media(array $message): array
    {
        if (is_array($message['video'] ?? null)) {
            return [
                self::mediaItem(
                    'video',
                    $message['video'],
                ),
            ];
        }

        if (is_array($message['document'] ?? null)) {
            return [
                self::mediaItem(
                    'document',
                    $message['document'],
                ),
            ];
        }

        $photos = $message['photo'] ?? null;
        if (is_array($photos) && $photos !== []) {
            $photo = end($photos);
            if (is_array($photo)) {
                return [
                    self::mediaItem(
                        'image',
                        $photo,
                    ),
                ];
            }
        }

        return [];
    }

    /**
     * @return array<string,mixed>
     */
    private static function mediaItem(
        string $type,
        array $source,
    ): array {
        $item = [
            'type' => $type,
            'file_id' => (string) (
                $source['file_id'] ?? ''
            ),
        ];

        foreach ([
            'file_name',
            'mime_type',
            'file_size',
            'width',
            'height',
            'duration',
        ] as $key) {
            if (isset($source[$key])) {
                $item[$key] = $source[$key];
            }
        }

        return $item;
    }

    private static function messageUrl(
        array $chat,
        int $messageId,
        string $targetRef,
    ): ?string {
        $username = trim(
            (string) ($chat['username'] ?? '')
        );

        if (
            $username === ''
            && str_starts_with(trim($targetRef), '@')
        ) {
            $username = substr(trim($targetRef), 1);
        }

        if ($username === '' || $messageId <= 0) {
            return null;
        }

        return 'https://t.me/'
            . rawurlencode($username)
            . '/'
            . $messageId;
    }

    private static function date(mixed $timestamp): ?DateTimeImmutable
    {
        $timestamp = is_int($timestamp)
            ? $timestamp
            : (int) $timestamp;

        if ($timestamp <= 0) {
            return null;
        }

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(
                new \DateTimeZone('UTC'),
            );
    }

    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        preg_match_all('/./us', $value, $matches);

        return count($matches[0] ?? []);
    }

    private static function slice(
        string $value,
        int $start,
        int $length,
    ): string {
        if ($length <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr(
                $value,
                $start,
                $length,
                'UTF-8',
            );
        }

        preg_match_all('/./us', $value, $matches);
        $characters = $matches[0] ?? [];

        return implode(
            '',
            array_slice(
                $characters,
                $start,
                $length,
            ),
        );
    }

    private static function safeError(Throwable $error): string
    {
        $message = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            trim($error->getMessage()),
        );

        return $message !== ''
            ? substr($message, 0, 500)
            : 'Ошибка Telegram Bot API.';
    }
}
