<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class ShareLinks
{
    /**
     * @return list<array{provider:string,label:string,url:string}>
     */
    public static function forPage(string $url, string $title): array
    {
        $result = [];
        $configured = Config::get('sharing.providers', []);

        if (!is_array($configured)) {
            return [];
        }

        foreach ($configured as $provider => $settings) {
            if (!is_array($settings) || ($settings['enabled'] ?? true) !== true) {
                continue;
            }

            $template = trim((string) ($settings['url'] ?? ''));
            $label = trim((string) ($settings['label'] ?? $provider));
            if ($template === '' || $label === '') {
                continue;
            }

            $shareUrl = strtr($template, [
                '{url}' => rawurlencode($url),
                '{title}' => rawurlencode($title),
                '{text}' => rawurlencode($title),
            ]);

            if (!str_starts_with($shareUrl, 'https://')) {
                continue;
            }

            $result[] = [
                'provider' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $provider)) ?: 'share',
                'label' => $label,
                'url' => $shareUrl,
            ];
        }

        return $result;
    }
}
