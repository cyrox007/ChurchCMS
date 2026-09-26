<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class RamblerSyndicationRenderer extends Rss2SyndicationRenderer
{
    public function render(SyndicationFeed $feed): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="utf-8"?>';
        $xml[] = '<rss xmlns:rambler="http://news.rambler.ru" version="2.0">';
        $xml[] = '<channel>';
        $xml[] = '<title>' . self::xml($feed->title) . '</title>';
        $xml[] = '<link>' . self::xml($feed->siteUrl) . '</link>';
        $xml[] = '<description>' . self::xml($feed->description) . '</description>';

        foreach ($feed->entries as $entry) {
            $xml[] = '<item>';
            $xml[] = '<guid>' . self::xml($entry->id) . '</guid>';
            $xml[] = '<title>' . self::xml($entry->title) . '</title>';
            $xml[] = '<link>' . self::xml($entry->url) . '</link>';
            $xml[] = '<pubDate>' . $entry->publishedAt->format(DATE_RSS) . '</pubDate>';

            if ($entry->description !== '') {
                $xml[] = '<description>' . self::xml($entry->description) . '</description>';
            }

            foreach ($entry->categories as $category) {
                if (is_string($category) && $category !== '') {
                    $xml[] = '<category>' . self::xml($category) . '</category>';
                }
            }

            if ($entry->contentHtml !== '') {
                $xml[] = '<content>' . self::cdata($entry->contentHtml) . '</content>';
            }

            if ($entry->author !== null && $entry->author !== '') {
                $xml[] = '<author>' . self::xml($entry->author) . '</author>';
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
}
