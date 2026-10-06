<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\GeneratedOutputCache;
use ChurchCMS\Core\RamblerSyndicationRenderer;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\Rss2SyndicationRenderer;
use ChurchCMS\Core\SyndicationExportLogRepository;
use ChurchCMS\Core\SyndicationFeed;
use ChurchCMS\Core\SyndicationRegistry;
use ChurchCMS\Core\SyndicationRenderer;
use Throwable;

final class SyndicationController
{
    public function rss(): never
    {
        $this->emit('rss', new Rss2SyndicationRenderer());
    }

    public function rambler(): never
    {
        $this->emit('rambler', new RamblerSyndicationRenderer());
    }

    private function emit(string $target, SyndicationRenderer $renderer): never
    {
        if (Config::get('syndication.enabled', true) !== true) {
            Response::text('404 Not Found', 404);
        }

        if (Config::get("syndication.targets.{$target}.enabled", false) !== true) {
            Response::text('404 Not Found', 404);
        }

        $siteUrl = rtrim((string) Config::get('syndication.site_url', ''), '/');
        if ($siteUrl === '' || filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            Response::text('Syndication is not configured.', 503);
        }

        $cache = GeneratedOutputCache::fromConfig(60);
        $key = $cache->key('syndication:' . $target, $siteUrl);
        $body = $cache->get($key);

        if ($body === null) {
            $startedAt = hrtime(true);
            $entryCount = 0;

            try {
                $entries = SyndicationRegistry::entriesFor($target, 100);
                $entryCount = count($entries);
                $feed = new SyndicationFeed(
                    title: (string) Config::get('syndication.channel_title', 'ChurchCMS'),
                    siteUrl: $siteUrl . '/',
                    description: (string) Config::get('syndication.channel_description', 'ChurchCMS feed'),
                    entries: $entries,
                );
                $body = $renderer->render($feed);
                $cache->put($key, $body);

                $this->recordExport(
                    $target,
                    'success',
                    $entryCount,
                    strlen($body),
                    $startedAt,
                );
            } catch (Throwable $exception) {
                $this->recordExport(
                    $target,
                    'failed',
                    $entryCount,
                    0,
                    $startedAt,
                    'generation_failed',
                );
                throw $exception;
            }
        }

        http_response_code(200);
        header('Content-Type: ' . $renderer->contentType());
        header('Cache-Control: public, max-age=60');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }

    private function recordExport(
        string $target,
        string $status,
        int $entryCount,
        int $bodyBytes,
        int $startedAt,
        ?string $errorCode = null,
    ): void {
        try {
            $durationMs = max(
                0,
                (int) round((hrtime(true) - $startedAt) / 1_000_000),
            );
            SyndicationExportLogRepository::fromDefaultConnection()->record(
                $target,
                $status,
                $entryCount,
                $bodyBytes,
                $durationMs,
                $errorCode,
            );
        } catch (Throwable) {
            // Журналирование не должно нарушать публичную выдачу фида.
        }
    }
}
