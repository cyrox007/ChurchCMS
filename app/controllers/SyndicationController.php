<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\RamblerSyndicationRenderer;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\Rss2SyndicationRenderer;
use ChurchCMS\Core\SyndicationFeed;
use ChurchCMS\Core\SyndicationRegistry;
use ChurchCMS\Core\SyndicationRenderer;

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

        $feed = new SyndicationFeed(
            title: (string) Config::get('syndication.channel_title', 'ChurchCMS'),
            siteUrl: $siteUrl . '/',
            description: (string) Config::get('syndication.channel_description', 'ChurchCMS feed'),
            entries: SyndicationRegistry::entriesFor($target, 100),
        );

        http_response_code(200);
        header('Content-Type: ' . $renderer->contentType());
        header('Cache-Control: public, max-age=60');
        header('X-Content-Type-Options: nosniff');
        echo $renderer->render($feed);
        exit;
    }
}
