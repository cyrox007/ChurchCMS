<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class MaxChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester,
    ChannelConnectionActivator,
    ChannelWebhookAdapter
{
    private const API_BASE = 'https://platform-api2.max.ru';
    private const MAX_TEXT_LENGTH = 4000;

    public function __construct(
        private readonly ChannelHttpClient $http =
            new NativeHttpClient(),
    ) {
    }

    public function providerId(): string
    {
        return 'max';
    }

    public function label(): string
    {
        return 'MAX';
    }

    public function capabilities(): array
    {
        return [
            ChannelCapability::PUBLISH_TEXT,
            ChannelCapability::PUBLISH_LINK,
            ChannelCapability::IMPORT_POSTS,
            ChannelCapability::WEBHOOK,
        ];
    }

    public function testConnection(
        string $targetRef,
        string $credentials,
        array $settings = [],
    ): ChannelConnectionTestResult {
        $chatId = self::chatId($targetRef);
        $credentials = trim($credentials);

        if ($chatId === null || !self::validToken($credentials)) {
            return new ChannelConnectionTestResult(
                false,
                'Проверьте access token и числовой chat_id канала или чата.',
            );
        }

        try {
            $me = $this->get(
                '/me',
                $credentials,
            );
            if (!is_array($me)) {
                return new ChannelConnectionTestResult(
                    false,
                    'MAX не подтвердил токен бота.',
                );
            }

            $chat = $this->get(
                '/chats/' . rawurlencode((string) $chatId),
                $credentials,
            );
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'MAX не подтвердил подключение.',
            );
        }

        if (!is_array($chat)) {
            return new ChannelConnectionTestResult(
                false,
                'MAX не вернул данные канала или чата.',
            );
        }

        $status = strtolower(trim((string) (
            $chat['status'] ?? ''
        )));
        $type = strtolower(trim((string) (
            $chat['type'] ?? ''
        )));

        if (
            $status !== 'active'
            || !in_array($type, ['channel', 'chat'], true)
        ) {
            return new ChannelConnectionTestResult(
                false,
                'Бот должен быть активным участником канала или группового чата.',
            );
        }

        return new ChannelConnectionTestResult(
            true,
            'Бот и целевой канал MAX доступны.',
        );
    }

    public function activateConnection(
        SocialConnection $connection,
        string $credentials,
        string $webhookUrl,
    ): void {
        $secret = self::webhookSecret(
            $connection,
            $credentials,
        );

        $result = $this->post(
            '/subscriptions',
            $credentials,
            [
                'url' => $webhookUrl,
                'update_types' => [
                    'message_created',
                    'message_edited',
                ],
                'secret' => $secret,
            ],
        );

        if (
            !is_array($result)
            || ($result['success'] ?? false) !== true
        ) {
            throw new RuntimeException(
                'MAX не подтвердил webhook-подписку.'
            );
        }
    }

    public function verifyWebhook(
        SocialConnection $connection,
        string $credentials,
        ChannelWebhookRequest $request,
    ): bool {
        return ChannelWebhookSignature::verifySecret(
            (string) $request->header(
                'X-Max-Bot-Api-Secret',
                '',
            ),
            self::webhookSecret(
                $connection,
                $credentials,
            ),
        );
    }

    public function receiveWebhook(
        SocialConnection $connection,
        string $credentials,
        ChannelWebhookRequest $request,
    ): ChannelWebhookResult {
        try {
            $update = json_decode(
                $request->rawBody,
                true,
                64,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $error) {
            throw new \InvalidArgumentException(
                'MAX webhook содержит некорректный JSON.',
                0,
                $error,
            );
        }

        if (!is_array($update)) {
            throw new \InvalidArgumentException(
                'MAX webhook должен содержать объект Update.'
            );
        }

        $chatId = self::chatId(
            $connection->targetRef,
        );
        if ($chatId === null) {
            throw new \InvalidArgumentException(
                'Некорректный MAX chat_id.'
            );
        }

        $item = self::inboundItem(
            $update,
            $chatId,
        );

        return new ChannelWebhookResult(
            items: $item !== null ? [$item] : [],
            status: 200,
            body: 'OK',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        $chatId = self::chatId($connection->targetRef);
        if ($chatId === null) {
            return new ChannelPublishResult(
                false,
                error: 'Некорректный MAX chat_id.',
            );
        }

        try {
            $result = $this->post(
                '/messages?chat_id='
                    . rawurlencode((string) $chatId),
                $credentials,
                [
                    'text' => self::outboundText($item),
                    'attachments' => [],
                    'link' => null,
                    'notify' => true,
                ],
            );
        } catch (Throwable $error) {
            return new ChannelPublishResult(
                false,
                error: self::safeError($error),
            );
        }

        $message = is_array($result)
            ? ($result['message'] ?? null)
            : null;
        $body = is_array($message)
            ? ($message['body'] ?? null)
            : null;
        $mid = is_array($body)
            ? trim((string) ($body['mid'] ?? ''))
            : '';

        if ($mid === '') {
            return new ChannelPublishResult(
                false,
                error: 'MAX вернул ответ без message.body.mid.',
            );
        }

        $remoteUrl = is_array($message)
            ? trim((string) ($message['url'] ?? ''))
            : '';

        return new ChannelPublishResult(
            true,
            remoteId: $chatId . ':' . $mid,
            remoteUrl: $remoteUrl !== ''
                ? $remoteUrl
                : null,
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        $chatId = self::chatId($connection->targetRef);
        if ($chatId === null) {
            throw new RuntimeException(
                'Некорректный MAX chat_id.'
            );
        }

        $limit = max(1, min(1000, $limit));
        $query = [
            'limit' => $limit,
            'timeout' => 0,
            'types' => 'message_created,message_edited',
        ];

        if (
            $cursor !== null
            && preg_match('/^\d+$/D', $cursor) === 1
        ) {
            $query['marker'] = (int) $cursor;
        }

        $result = $this->get(
            '/updates',
            $credentials,
            $query,
        );

        if (
            !is_array($result)
            || !is_array($result['updates'] ?? null)
        ) {
            throw new RuntimeException(
                'MAX вернул некорректный список обновлений.'
            );
        }

        $items = [];

        foreach ($result['updates'] as $update) {
            if (!is_array($update)) {
                continue;
            }

            $item = self::inboundItem(
                $update,
                $chatId,
            );

            if ($item !== null) {
                $items[] = $item;
            }
        }

        $marker = $result['marker'] ?? null;
        $nextCursor = is_int($marker)
            || (
                is_string($marker)
                && preg_match('/^\d+$/D', $marker) === 1
            )
            ? (string) $marker
            : $cursor;

        return new ChannelPullBatch(
            $items,
            $nextCursor,
        );
    }

    /**
     * @param array<string,string|int|float|bool|null> $query
     */
    private function get(
        string $path,
        string $token,
        array $query = [],
    ): mixed {
        $response = $this->http->getJson(
            self::API_BASE . $path,
            $query,
            self::headers($token),
        );

        return self::response($response);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function post(
        string $path,
        string $token,
        array $payload,
    ): mixed {
        $response = $this->http->postJson(
            self::API_BASE . $path,
            $payload,
            self::headers($token),
        );

        return self::response($response);
    }

    /**
     * @return array<string,string>
     */
    private static function headers(string $token): array
    {
        $token = trim($token);

        if (!self::validToken($token)) {
            throw new RuntimeException(
                'Некорректный MAX access token.'
            );
        }

        return [
            'Authorization' => $token,
        ];
    }

    /**
     * @param array{
     *     status:int,
     *     body:string,
     *     json:array<string,mixed>|null
     * } $response
     */
    private static function response(array $response): mixed
    {
        $status = (int) ($response['status'] ?? 0);
        $json = $response['json'] ?? null;

        if (
            $status < 200
            || $status >= 300
            || !is_array($json)
        ) {
            $message = is_array($json)
                ? trim((string) ($json['message'] ?? ''))
                : '';

            throw new RuntimeException(
                $message !== ''
                    ? 'MAX API: ' . self::safeText($message, 240)
                    : 'MAX API отклонил запрос.'
            );
        }

        return $json;
    }

    private static function webhookSecret(
        SocialConnection $connection,
        string $credentials,
    ): string {
        return hash_hmac(
            'sha256',
            'churchcms-max-webhook:'
                . $connection->publicId,
            $credentials,
        );
    }

    private static function validToken(string $token): bool
    {
        $token = trim($token);

        return $token !== ''
            && strlen($token) <= 4096
            && preg_match('/\s/u', $token) !== 1;
    }

    private static function chatId(string $targetRef): ?int
    {
        $targetRef = trim($targetRef);

        if (
            preg_match('/^-?\d+$/D', $targetRef) !== 1
        ) {
            return null;
        }

        $value = (int) $targetRef;

        return $value !== 0 ? $value : null;
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
        if ($suffixLength >= self::MAX_TEXT_LENGTH) {
            return self::slice(
                $suffix,
                0,
                self::MAX_TEXT_LENGTH,
            );
        }

        $text = self::slice(
            $text,
            0,
            self::MAX_TEXT_LENGTH - $suffixLength,
        );

        $result = trim($text . $suffix);

        return $result !== ''
            ? $result
            : self::slice(
                trim($item->title),
                0,
                self::MAX_TEXT_LENGTH,
            );
    }

    private static function inboundItem(
        array $update,
        int $targetChatId,
    ): ?ChannelInboundItem {
        $type = (string) ($update['update_type'] ?? '');

        if (!in_array(
            $type,
            ['message_created', 'message_edited'],
            true,
        )) {
            return null;
        }

        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $recipient = $message['recipient'] ?? null;
        $body = $message['body'] ?? null;

        if (
            !is_array($recipient)
            || !is_array($body)
            || (int) ($recipient['chat_id'] ?? 0)
                !== $targetChatId
        ) {
            return null;
        }

        $mid = trim((string) ($body['mid'] ?? ''));
        if ($mid === '') {
            return null;
        }

        $text = trim((string) ($body['text'] ?? ''));
        $timestamp = self::millisecondsDate(
            $message['timestamp'] ?? null,
        );
        $updatedAt = $type === 'message_edited'
            ? self::millisecondsDate(
                $update['timestamp'] ?? null,
            )
            : null;
        $url = trim((string) ($message['url'] ?? ''));

        return new ChannelInboundItem(
            remoteId: $targetChatId . ':' . $mid,
            kind: self::messageKind($body),
            title: null,
            text: $text,
            canonicalUrl: $url !== '' ? $url : null,
            media: self::media($body),
            publishedAt: $timestamp,
            updatedAt: $updatedAt,
            payload: [
                'update_type' => $type,
                'timestamp' => (int) (
                    $update['timestamp'] ?? 0
                ),
                'chat_id' => $targetChatId,
                'mid' => $mid,
                'seq' => (int) ($body['seq'] ?? 0),
            ],
        );
    }

    private static function messageKind(array $body): string
    {
        $types = [];

        foreach ($body['attachments'] ?? [] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $type = (string) ($attachment['type'] ?? '');
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        if (isset($types['video'])) {
            return 'video';
        }

        if (isset($types['image'])) {
            return 'image';
        }

        if (isset($types['file'])) {
            return 'document';
        }

        return 'post';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function media(array $body): array
    {
        $result = [];

        foreach ($body['attachments'] ?? [] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $type = (string) ($attachment['type'] ?? '');

            if (!in_array(
                $type,
                ['image', 'video', 'audio', 'file', 'share'],
                true,
            )) {
                continue;
            }

            $item = [
                'type' => match ($type) {
                    'image' => 'image',
                    'file' => 'document',
                    default => $type,
                },
            ];

            foreach ([
                'filename',
                'size',
                'width',
                'height',
                'duration',
                'thumbnail',
                'title',
                'description',
                'image_url',
            ] as $key) {
                if (isset($attachment[$key])) {
                    $item[$key] = $attachment[$key];
                }
            }

            $payload = $attachment['payload'] ?? null;
            if (is_array($payload)) {
                foreach ([
                    'url',
                    'token',
                    'photo_id',
                    'video_id',
                    'file_id',
                ] as $key) {
                    if (isset($payload[$key])) {
                        $item[$key] = $payload[$key];
                    }
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    private static function millisecondsDate(
        mixed $value,
    ): ?DateTimeImmutable {
        $milliseconds = (int) $value;

        if ($milliseconds <= 0) {
            return null;
        }

        $seconds = intdiv($milliseconds, 1000);

        return (new DateTimeImmutable(
            '@' . $seconds,
        ))->setTimezone(
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

        return implode(
            '',
            array_slice(
                $matches[0] ?? [],
                $start,
                $length,
            ),
        );
    }

    private static function safeError(
        Throwable $error,
    ): string {
        return self::safeText(
            $error->getMessage(),
            500,
        );
    }

    private static function safeText(
        string $text,
        int $limit,
    ): string {
        $text = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            trim($text),
        );

        return $text !== ''
            ? substr($text, 0, $limit)
            : 'Ошибка MAX API.';
    }
}
