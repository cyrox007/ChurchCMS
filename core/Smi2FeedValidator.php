<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class Smi2FeedValidator
{
    private const CONTENT_NAMESPACE = 'http://purl.org/rss/1.0/modules/content/';

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

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('content', self::CONTENT_NAMESPACE);
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
        $this->requireText(
            $channel,
            'title',
            'channel_title',
            'Укажите название RSS-канала.',
            $issues,
        );
        $channelLink = $this->requireText(
            $channel,
            'link',
            'channel_link',
            'Укажите адрес сайта-источника.',
            $issues,
        );
        $this->requireText(
            $channel,
            'description',
            'channel_description',
            'Укажите описание RSS-канала.',
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
                'Полнотекстовый партнёрский фид должен содержать хотя бы один материал.',
            );
            return $issues;
        }

        $links = [];
        for ($index = 0; $index < $items->length; $index++) {
            $itemNumber = $index + 1;
            $item = $items->item($index);
            if (!$item instanceof DOMElement) {
                continue;
            }

            $this->validateItem(
                $xpath,
                $item,
                $itemNumber,
                $links,
                $issues,
            );
        }

        return $issues;
    }

    /**
     * @param array<string,int> $links
     * @param list<array{level:string,code:string,message:string,item:?int}> $issues
     */
    private function validateItem(
        DOMXPath $xpath,
        DOMElement $item,
        int $itemNumber,
        array &$links,
        array &$issues,
    ): void {
        $title = $this->childText($item, 'title');
        $link = $this->childText($item, 'link');
        $publishedAt = $this->childText($item, 'pubDate');
        $content = trim((string) $xpath->evaluate(
            'string(content:encoded)',
            $item,
        ));

        if ($title === null) {
            $issues[] = $this->issue(
                'error',
                'item_title',
                'У материала отсутствует заголовок.',
                $itemNumber,
            );
        }

        if ($link === null || !$this->absoluteHttpUrl($link)) {
            $issues[] = $this->issue(
                'error',
                'item_link',
                'У материала должен быть абсолютный HTTP/HTTPS URL.',
                $itemNumber,
            );
        } elseif (isset($links[$link])) {
            $issues[] = $this->issue(
                'error',
                'duplicate_link',
                'В фиде повторяется URL материала.',
                $itemNumber,
            );
        } else {
            $links[$link] = $itemNumber;
        }

        if ($publishedAt === null || !$this->validDate($publishedAt)) {
            $issues[] = $this->issue(
                'error',
                'item_pub_date',
                'У материала должна быть корректная дата публикации pubDate.',
                $itemNumber,
            );
        }

        if ($content === '') {
            $issues[] = $this->issue(
                'error',
                'full_text_missing',
                'Для партнёрского контура СМИ2 нужен полнотекстовый материал в content:encoded.',
                $itemNumber,
            );
        }

        if ($this->childText($item, 'guid') === null) {
            $issues[] = $this->issue(
                'warning',
                'guid_missing',
                'Рекомендуется указывать стабильный guid материала.',
                $itemNumber,
            );
        }

        $enclosures = $item->getElementsByTagName('enclosure');
        if ($enclosures->length === 0) {
            $issues[] = $this->issue(
                'warning',
                'image_missing',
                'У материала нет изображения enclosure; оно желательно для карточки в агрегаторе.',
                $itemNumber,
            );
            return;
        }

        $enclosure = $enclosures->item(0);
        if (!$enclosure instanceof DOMElement) {
            return;
        }

        $imageUrl = trim($enclosure->getAttribute('url'));
        $imageType = strtolower(trim($enclosure->getAttribute('type')));
        if (!$this->absoluteHttpUrl($imageUrl)) {
            $issues[] = $this->issue(
                'error',
                'image_url',
                'URL изображения enclosure должен быть абсолютным HTTP/HTTPS URL.',
                $itemNumber,
            );
        }
        if ($imageType === '' || !str_starts_with($imageType, 'image/')) {
            $issues[] = $this->issue(
                'warning',
                'image_mime',
                'Для enclosure рекомендуется MIME-тип image/*.',
                $itemNumber,
            );
        }
    }

    /**
     * @param list<array{level:string,code:string,message:string,item:?int}> $issues
     */
    private function requireText(
        DOMElement $parent,
        string $tag,
        string $code,
        string $message,
        array &$issues,
    ): ?string {
        $value = $this->childText($parent, $tag);
        if ($value === null) {
            $issues[] = $this->issue('error', $code, $message);
        }

        return $value;
    }

    private function childText(DOMElement $parent, string $tag): ?string
    {
        $nodes = $parent->getElementsByTagName($tag);
        if ($nodes->length === 0) {
            return null;
        }

        $value = trim((string) $nodes->item(0)?->textContent);
        return $value === '' ? null : $value;
    }

    private function absoluteHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function validDate(string $value): bool
    {
        try {
            new DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{level:string,code:string,message:string,item:?int}
     */
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
