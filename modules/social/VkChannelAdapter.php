<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class VkChannelAdapter implements
    ChannelAdapter,
    ChannelConnectionTester
{
    private const API_BASE = 'https://api.vk.com/method/';
    private const API_VERSION = '5.199';

    public function __construct(
        private readonly ChannelHttpClient $http =
            new NativeHttpClient(),
    ) {
    }

    public function providerId(): string
    {
        return 'vk';
    }

    public function label(): string
    {
        return 'VK';
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

        if ($targetRef === '' || !self::validToken($credentials)) {
            return new ChannelConnectionTestResult(
                false,
                'Проверьте access token и идентификатор сообщества.',
            );
        }

        try {
            $group = $this->resolveGroup(
                $targetRef,
                $credentials,
            );
        } catch (Throwable) {
            return new ChannelConnectionTestResult(
                false,
                'VK не подтвердил токен или сообщество.',
            );
        }

        $adminLevel = (int) ($group['admin_level'] ?? 0);
        $isAdmin = self::boolValue(
            $group['is_admin'] ?? false,
        );

        if (!$isAdmin || $adminLevel < 2) {
            return new ChannelConnectionTestResult(
                false,
                'Для публикации нужен пользовательский токен редактора или администратора сообщества.',
            );
        }

        return new ChannelConnectionTestResult(
            true,
            'Сообщество доступно для публикации.',
        );
    }

    public function publish(
        SocialConnection $connection,
        string $credentials,
        ChannelOutboundItem $item,
    ): ChannelPublishResult {
        try {
            $group = $this->resolveGroup(
                $connection->targetRef,
                $credentials,
            );
            $groupId = (int) ($group['id'] ?? 0);
            if ($groupId <= 0) {
                throw new RuntimeException(
                    'VK не вернул ID сообщества.'
                );
            }

            $result = $this->api(
                $credentials,
                'wall.post',
                [
                    'owner_id' => -$groupId,
                    'from_group' => 1,
                    'message' => self::outboundText($item),
                    'guid' => self::guid(
                        $connection,
                        $item,
                    ),
                ],
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
                error: 'VK не подтвердил публикацию.',
            );
        }

        $postId = (int) ($result['post_id'] ?? 0);
        if ($postId <= 0) {
            return new ChannelPublishResult(
                false,
                error: 'VK вернул ответ без post_id.',
            );
        }

        $ownerId = -$groupId;

        return new ChannelPublishResult(
            true,
            remoteId: $ownerId . ':' . $postId,
            remoteUrl: 'https://vk.com/wall'
                . $ownerId
                . '_'
                . $postId,
        );
    }

    public function pull(
        SocialConnection $connection,
        string $credentials,
        ?string $cursor,
        int $limit = 50,
    ): ChannelPullBatch {
        $limit = max(1, min(100, $limit));
        $group = $this->resolveGroup(
            $connection->targetRef,
            $credentials,
        );
        $groupId = (int) ($group['id'] ?? 0);
        if ($groupId <= 0) {
            throw new RuntimeException(
                'VK не вернул ID сообщества.'
            );
        }

        $cursorPoint = self::parseCursor($cursor);
        $count = $cursorPoint === null
            ? $limit
            : max($limit, 50);

        $result = $this->api(
            $credentials,
            'wall.get',
            [
                'domain' => -$groupId,
                'count' => min(100, $count),
                'filter' => 'owner',
            ],
        );

        if (
            !is_array($result)
            || !is_array($result['items'] ?? null)
        ) {
            throw new RuntimeException(
                'VK вернул некорректную стену сообщества.'
            );
        }

        $candidates = [];
        foreach ($result['items'] as $post) {
            if (!is_array($post)) {
                continue;
            }

            $item = self::inboundItem(
                $post,
                $groupId,
            );
            if ($item === null) {
                continue;
            }

            $point = self::postPoint($post);
            if (
                $cursorPoint !== null
                && self::comparePoint(
                    $point,
                    $cursorPoint,
                ) <= 0
            ) {
                continue;
            }

            $candidates[] = [
                'point' => $point,
                'item' => $item,
            ];
        }

        usort(
            $candidates,
            static fn(array $left, array $right): int =>
                self::comparePoint(
                    $left['point'],
                    $right['point'],
                ),
        );

        $candidates = array_slice(
            $candidates,
            0,
            $limit,
        );
        $items = array_map(
            static fn(array $candidate): ChannelInboundItem =>
                $candidate['item'],
            $candidates,
        );

        $nextCursor = $cursor;
        if ($candidates !== []) {
            $last = $candidates[array_key_last($candidates)];
            $nextCursor = self::formatCursor(
                $last['point'],
            );
        }

        return new ChannelPullBatch(
            $items,
            $nextCursor,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveGroup(
        string $targetRef,
        string $token,
    ): array {
        $result = $this->api(
            $token,
            'groups.getById',
            [
                'group_id' => trim($targetRef),
                'fields' =>
                    'screen_name,is_admin,admin_level,can_post',
            ],
        );

        $groups = is_array($result)
            ? ($result['groups'] ?? null)
            : null;

        if (
            !is_array($groups)
            || !is_array($groups[0] ?? null)
        ) {
            throw new RuntimeException(
                'VK не нашёл указанное сообщество.'
            );
        }

        return $groups[0];
    }

    /**
     * @param array<string,string|int|float> $payload
     */
    private function api(
        string $token,
        string $method,
        array $payload = [],
    ): mixed {
        $token = trim($token);
        if (!self::validToken($token)) {
            throw new RuntimeException(
                'Некорректный VK access token.'
            );
        }

        $payload['access_token'] = $token;
        $payload['v'] = self::API_VERSION;

        $response = $this->http->postForm(
            self::API_BASE . $method,
            $payload,
        );

        $json = $response['json'] ?? null;
        if (
            ($response['status'] ?? 0) < 200
            || ($response['status'] ?? 0) >= 300
            || !is_array($json)
        ) {
            throw new RuntimeException(
                'VK API не вернул корректный ответ.'
            );
        }

        if (is_array($json['error'] ?? null)) {
            $code = (int) (
                $json['error']['error_code'] ?? 0
            );
            $message = trim((string) (
                $json['error']['error_msg'] ?? ''
            ));

            throw new RuntimeException(
                'VK API отклонил запрос'
                . ($code > 0 ? ' (' . $code . ')' : '')
                . ($message !== ''
                    ? ': ' . self::safeText($message, 240)
                    : '.')
            );
        }

        if (!array_key_exists('response', $json)) {
            throw new RuntimeException(
                'VK API вернул ответ без поля response.'
            );
        }

        return $json['response'];
    }

    private static function validToken(string $token): bool
    {
        $token = trim($token);

        return $token !== ''
            && strlen($token) <= 4096
            && preg_match('/\s/u', $token) !== 1;
    }

    private static function outboundText(
        ChannelOutboundItem $item,
    ): string {
        $text = trim($item->text);
        if ($text === '') {
            $text = trim($item->title);
        }

        $url = trim($item->canonicalUrl);
        if (
            $url !== ''
            && !str_contains($text, $url)
        ) {
            $text .= "\n\n" . $url;
        }

        return trim($text);
    }

    private static function guid(
        SocialConnection $connection,
        ChannelOutboundItem $item,
    ): string {
        return substr(
            hash(
                'sha256',
                $connection->publicId
                . ':'
                . $item->sourceId,
            ),
            0,
            32,
        );
    }

    private static function inboundItem(
        array $post,
        int $groupId,
    ): ?ChannelInboundItem {
        $postId = (int) ($post['id'] ?? 0);
        $ownerId = (int) (
            $post['owner_id'] ?? -$groupId
        );

        if (
            $postId <= 0
            || $ownerId !== -$groupId
        ) {
            return null;
        }

        $text = trim((string) ($post['text'] ?? ''));
        $date = self::date($post['date'] ?? null);
        $edited = self::date($post['edited'] ?? null);

        return new ChannelInboundItem(
            remoteId: $ownerId . ':' . $postId,
            kind: self::postKind($post),
            title: null,
            text: $text,
            canonicalUrl: 'https://vk.com/wall'
                . $ownerId
                . '_'
                . $postId,
            media: self::media($post),
            publishedAt: $date,
            updatedAt: $edited,
            payload: [
                'owner_id' => $ownerId,
                'post_id' => $postId,
                'from_id' => (int) (
                    $post['from_id'] ?? 0
                ),
                'edited' => isset($post['edited'])
                    ? (int) $post['edited']
                    : null,
            ],
        );
    }

    private static function postKind(array $post): string
    {
        $types = [];

        foreach ($post['attachments'] ?? [] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $type = (string) (
                $attachment['type'] ?? ''
            );
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        if (isset($types['video'])) {
            return 'video';
        }

        if (isset($types['photo'])) {
            return 'image';
        }

        if (isset($types['doc'])) {
            return 'document';
        }

        return 'post';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function media(array $post): array
    {
        $result = [];

        foreach ($post['attachments'] ?? [] as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $type = (string) (
                $attachment['type'] ?? ''
            );
            $source = $attachment[$type] ?? null;

            if (!is_array($source)) {
                continue;
            }

            $item = self::mediaItem(
                $type,
                $source,
            );
            if ($item !== null) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function mediaItem(
        string $type,
        array $source,
    ): ?array {
        if (!in_array(
            $type,
            ['photo', 'video', 'doc', 'link'],
            true,
        )) {
            return null;
        }

        $item = [
            'type' => $type === 'photo'
                ? 'image'
                : ($type === 'doc'
                    ? 'document'
                    : $type),
        ];

        foreach ([
            'id',
            'owner_id',
            'title',
            'size',
            'ext',
            'date',
            'duration',
            'width',
            'height',
            'url',
        ] as $key) {
            if (isset($source[$key])) {
                $item[$key] = $source[$key];
            }
        }

        if ($type === 'photo') {
            $url = self::largestPhotoUrl(
                $source['sizes'] ?? null,
            );
            if ($url !== null) {
                $item['url'] = $url;
            }
        }

        return $item;
    }

    private static function largestPhotoUrl(
        mixed $sizes,
    ): ?string {
        if (!is_array($sizes)) {
            return null;
        }

        $bestUrl = null;
        $bestArea = -1;

        foreach ($sizes as $size) {
            if (!is_array($size)) {
                continue;
            }

            $url = trim((string) (
                $size['url'] ?? ''
            ));
            if ($url === '') {
                continue;
            }

            $area = (int) ($size['width'] ?? 0)
                * (int) ($size['height'] ?? 0);

            if ($area >= $bestArea) {
                $bestArea = $area;
                $bestUrl = $url;
            }
        }

        return $bestUrl;
    }

    /**
     * @return array{timestamp:int,post_id:int}
     */
    private static function postPoint(array $post): array
    {
        return [
            'timestamp' => max(
                (int) ($post['date'] ?? 0),
                (int) ($post['edited'] ?? 0),
            ),
            'post_id' => (int) ($post['id'] ?? 0),
        ];
    }

    /**
     * @return array{timestamp:int,post_id:int}|null
     */
    private static function parseCursor(
        ?string $cursor,
    ): ?array {
        if (
            $cursor === null
            || preg_match(
                '/^(\d+):(\d+)$/D',
                $cursor,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return [
            'timestamp' => (int) $matches[1],
            'post_id' => (int) $matches[2],
        ];
    }

    /**
     * @param array{timestamp:int,post_id:int} $point
     */
    private static function formatCursor(
        array $point,
    ): string {
        return $point['timestamp']
            . ':'
            . $point['post_id'];
    }

    /**
     * @param array{timestamp:int,post_id:int} $left
     * @param array{timestamp:int,post_id:int} $right
     */
    private static function comparePoint(
        array $left,
        array $right,
    ): int {
        return [
            $left['timestamp'],
            $left['post_id'],
        ] <=> [
            $right['timestamp'],
            $right['post_id'],
        ];
    }

    private static function date(
        mixed $timestamp,
    ): ?DateTimeImmutable {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return null;
        }

        return (new DateTimeImmutable(
            '@' . $timestamp,
        ))->setTimezone(
            new \DateTimeZone('UTC'),
        );
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower((string) $value),
            ['1', 'true', 'yes', 'on'],
            true,
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

        if ($text === '') {
            return 'Ошибка VK API.';
        }

        return substr($text, 0, $limit);
    }
}
