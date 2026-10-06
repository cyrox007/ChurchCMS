<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class RamblerFeedValidator
{
    /**
     * @return list<array{level:string,code:string,message:string,item:?int}>
     */
    public function validate(
        string $xml,
        string $contentType = 'application/rss+xml; charset=utf-8',
    ): array {
        $issues = [];

        if (preg_match('/^<\?xml\s+version=["\']1\.0["\']\s+encoding=["\']utf-8["\']\s*\?>/i', $xml) !== 1) {
            $issues[] = $this->issue(
                'error',
                'xml_declaration',
                'Фид должен начинаться с XML-декларации версии 1.0 и кодировки UTF-8.',
            );
        }

        if (strtolower(trim($contentType)) !== 'application/rss+xml; charset=utf-8') {
            $issues[] = $this->issue(
                'error',
                'content_type',
                'Content-Type должен быть application/rss+xml; charset=utf-8.',
            );
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $document->loadXML(
            $xml,
            LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            $issues[] = $this->issue(
                'error',
                'invalid_xml',
                'Фид содержит некорректный XML.',
            );
            return $issues;
        }

        $root = $document->documentElement;
        if ($root->tagName !== 'rss' || $root->getAttribute('version') !== '2.0') {
            $issues[] = $this->issue(
                'error',
                'rss_version',
                'Корневой элемент должен быть RSS версии 2.0.',
            );
        }

        if ($root->getAttributeNS('http://www.w3.org/2000/xmlns/', 'rambler') !== 'http://news.rambler.ru') {
            $issues[] = $this->issue(
                'warning',
                'rambler_namespace',
                'Рекомендуется объявить пространство имён Rambler http://news.rambler.ru.',
            );
        }

        $xpath = new DOMXPath($document);
        $channels = $xpath->query('/rss/channel');
        if ($channels === false || $channels->length !== 1) {
            $issues[] = $this->issue(
                'error',
                'channel',
                'Фид должен содержать ровно один элемент channel.',
            );
            return $issues;
        }

        /** @var DOMElement $channel */
        $channel = $channels->item(0);
        $this->requireChildText($channel, 'title', 'channel_title', 'Укажите название RSS-канала.', $issues);
        $channelLink = $this->requireChildText(
            $channel,
            'link',
            'channel_link',
            'Укажите адрес сайта-источника.',
            $issues,
        );
        if ($channelLink !== null && !$this->absoluteHttpUrl($channelLink)) {
            $issues[] = $this->issue(
                'error',
                'channel_link_url',
                'Адрес сайта-источника должен быть абсолютным HTTP/HTTPS URL.',
            );
        }

        $items = $xpath->query('/rss/channel/item');
        if ($items === false || $items->length === 0) {
            $issues[] = $this->issue(
                'error',
                'items_missing',
                'Фид должен содержать хотя бы одну новостную публикацию.',
            );
            return $issues;
        }

        $links = [];
        $guids = [];
        foreach ($items as $index => $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $itemNumber = $index + 1;
            $this->requireChildText(
                $node,
                'title',
                'item_title',
                'У публикации отсутствует заголовок.',
                $issues,
                $itemNumber,
            );
            $link = $this->requireChildText(
                $node,
                'link',
                'item_link',
                'У публикации отсутствует ссылка.',
                $issues,
                $itemNumber,
            );
            if ($link !== null) {
                if (!$this->absoluteHttpUrl($link)) {
                    $issues[] = $this->issue(
                        'error',
                        'item_link_url',
                        'Ссылка публикации должна быть абсолютным HTTP/HTTPS URL.',
                        $itemNumber,
                    );
                } elseif (isset($links[$link])) {
                    $issues[] = $this->issue(
                        'error',
                        'item_link_duplicate',
                        'Ссылка публикации должна быть уникальной в пределах фида.',
                        $itemNumber,
                    );
                } else {
                    $links[$link] = true;
                }
            }

            if (!$this->hasFullText($node)) {
                $issues[] = $this->issue(
                    'error',
                    'item_content',
                    'У публикации отсутствует обязательный полный текст content/full-text.',
                    $itemNumber,
                );
            }

            $pubDate = $this->childText($node, 'pubDate');
            if ($pubDate !== null && !$this->rfc2822($pubDate)) {
                $issues[] = $this->issue(
                    'error',
                    'item_pub_date',
                    'pubDate должен соответствовать RFC-2822.',
                    $itemNumber,
                );
            }

            $guid = $this->childText($node, 'guid');
            if ($guid !== null && $guid !== '') {
                if (isset($guids[$guid])) {
                    $issues[] = $this->issue(
                        'warning',
                        'item_guid_duplicate',
                        'GUID публикации повторяется в пределах фида.',
                        $itemNumber,
                    );
                }
                $guids[$guid] = true;
            }

            $this->validateEnclosures($node, $issues, $itemNumber);
        }

        return $issues;
    }

    /**
     * @param list<array{level:string,code:string,message:string,item:?int}> $issues
     */
    private function requireChildText(
        DOMElement $parent,
        string $tag,
        string $code,
        string $message,
        array &$issues,
        ?int $item = null,
    ): ?string {
        $value = $this->childText($parent, $tag);
        if ($value === null || $value === '') {
            $issues[] = $this->issue('error', $code, $message, $item);
            return null;
        }

        return $value;
    }

    private function childText(DOMElement $parent, string $tag): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === $tag) {
                return trim($child->textContent);
            }
        }

        return null;
    }

    private function hasFullText(DOMElement $item): bool
    {
        foreach ($item->childNodes as $child) {
            if (
                $child instanceof DOMElement
                && in_array($child->tagName, ['content', 'full-text'], true)
                && trim($child->textContent) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{level:string,code:string,message:string,item:?int}> $issues
     */
    private function validateEnclosures(
        DOMElement $item,
        array &$issues,
        int $itemNumber,
    ): void {
        $supportedImages = [
            'image/png',
            'image/jpeg',
            'image/pjpeg',
            'image/webp',
            'image/avif',
        ];

        foreach ($item->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->tagName !== 'enclosure') {
                continue;
            }

            $url = trim($child->getAttribute('url'));
            $type = strtolower(trim($child->getAttribute('type')));
            if ($url === '' || !$this->absoluteHttpUrl($url)) {
                $issues[] = $this->issue(
                    'error',
                    'enclosure_url',
                    'enclosure должен содержать абсолютный HTTP/HTTPS URL.',
                    $itemNumber,
                );
            }
            if (str_starts_with($type, 'image/') && !in_array($type, $supportedImages, true)) {
                $issues[] = $this->issue(
                    'warning',
                    'enclosure_image_type',
                    'Формат изображения enclosure не входит в перечень PNG/JPG/WEBP/AVIF/PJPEG.',
                    $itemNumber,
                );
            }
        }
    }

    private function absoluteHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(
            strtolower((string) parse_url($value, PHP_URL_SCHEME)),
            ['http', 'https'],
            true,
        );
    }

    private function rfc2822(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('D, d M Y H:i:s O', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false) {
            return false;
        }

        return $errors === false
            || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0);
    }

    /** @return array{level:string,code:string,message:string,item:?int} */
    private function issue(
        string $level,
        string $code,
        string $message,
        ?int $item = null,
    ): array {
        return [
            'level' => $level,
            'code' => $code,
            'message' => $message,
            'item' => $item,
        ];
    }
}
