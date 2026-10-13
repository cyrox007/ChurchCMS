<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

final class RutubeVideoPolicy
{
    public const MAX_BYTES = 25_000_000_000;
    public const MAX_DURATION_SECONDS = 5 * 60 * 60;

    /** @var list<string> */
    private const MIME_TYPES = [
        'video/mp4',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/quicktime',
        'video/x-flv',
        'video/mpeg',
        'video/3gpp',
        'video/webm',
        'video/x-matroska',
        'video/hevc',
        'video/h265',
    ];

    /**
     * @return array{success:bool,error:?string,video:?array<string,mixed>}
     */
    public static function validate(ChannelOutboundItem $item): array
    {
        $video = null;

        foreach ($item->media as $media) {
            if (is_array($media) && ($media['type'] ?? null) === 'video') {
                $video = $media;
                break;
            }
        }

        if ($video === null) {
            return self::error(
                'Для публикации в Rutube к материалу должно быть привязано видео из Media.'
            );
        }

        $publicId = trim((string) ($video['public_id'] ?? ''));
        if ($publicId === '') {
            return self::error(
                'Видео Rutube не содержит публичный ID Media.'
            );
        }

        $bytes = (int) ($video['bytes'] ?? 0);
        if ($bytes <= 0) {
            return self::error(
                'Не удалось определить размер видео Rutube.'
            );
        }
        if ($bytes > self::MAX_BYTES) {
            return self::error(
                'Rutube принимает видео размером не более 25 ГБ.'
            );
        }

        $mimeType = strtolower(trim((string) ($video['mime_type'] ?? '')));
        if (!in_array($mimeType, self::MIME_TYPES, true)) {
            return self::error(
                'Формат видео не входит в подтверждённый список форматов Rutube.'
            );
        }

        if (isset($video['duration_seconds'])) {
            $duration = (int) $video['duration_seconds'];
            if ($duration <= 0 || $duration > self::MAX_DURATION_SECONDS) {
                return self::error(
                    'Rutube принимает видео длительностью не более 5 часов.'
                );
            }
        }

        if (trim($item->title) === '') {
            return self::error(
                'Для отправки видео в Rutube требуется заголовок.'
            );
        }

        return [
            'success' => true,
            'error' => null,
            'video' => $video,
        ];
    }

    /**
     * @return array{success:false,error:string,video:null}
     */
    private static function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'video' => null,
        ];
    }
}
