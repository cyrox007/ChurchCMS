<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

class Rss2SyndicationRenderer implements SyndicationRenderer
{
    public function contentType(): string
    {
        return 'application/rss+xml; charset=utf-8';
    }

    public function render(SyndicationFeed $feed): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="utf-8"?>';
        $xml[] = '<rss version="2.0">';
        $xml[] = '<channel>';
        $xml[] = '<title>' . self::xml($feed->title) . '</title>';
        $xml[] = '<link>' . self::xml($feed->siteUrl) . '</link>';
        $xml[] = '<description>' . self::xml($feed->description) . '</description>';

        foreach ($feed->entries as $entry) {
            $xml[] = '<item>';
            $xml[] = '<guid isPermaLink="false">' . self::xml($entry->id) . '</guid>';
            $xml[] = '<title>' . self::xml($entry->title) . '</title>';
            $xml[] = '<link>' . self::xml($entry->url) . '</link>';
            $xml[] = '<pubDate>' . $entry->publishedAt->format(DATE_RSS) . '</pubDate>';

            if ($entry->description !== '') {
                $xml[] = '<description>' . self::xml($entry->description) . '</description>';
            }

            if ($entry->author !== null && $entry->author !== '') {
                $xml[] = '<author>' . self::xml($entry->author) . '</author>';
            }

            if (
                $entry->sourceName !== null
                && $entry->sourceName !== ''
                && $entry->sourceUrl !== null
                && $entry->sourceUrl !== ''
            ) {
                $xml[] = '<source url="'
                    . self::xmlAttr($entry->sourceUrl)
                    . '">'
                    . self::xml($entry->sourceName)
                    . '</source>';
            }

            foreach ($entry->categories as $category) {
                if (is_string($category) && $category !== '') {
                    $xml[] = '<category>' . self::xml($category) . '</category>';
                }
            }

            if ($entry->imageUrl !== null && $entry->imageMime !== null) {
                $xml[] = '<enclosure url="' . self::xmlAttr($entry->imageUrl)
                    . '" type="' . self::xmlAttr($entry->imageMime) . '"/>';
            }

            $xml[] = '</item>';
        }

        $xml[] = '</channel>';
        $xml[] = '</rss>';

        return implode("\n", $xml) . "\n";
    }

    protected static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected static function xmlAttr(string $value): string
    {
        return self::xml($value);
    }

    protected static function cdata(string $value): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
    }
}
