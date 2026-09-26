<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\ApiResource;
use ChurchCMS\Core\Config;

final class PublicationApiResource implements ApiResource
{
    public function __construct(private readonly Publication $publication)
    {
    }

    public function toApiArray(): array
    {
        $base = rtrim((string) Config::get('syndication.site_url', ''), '/');

        return [
            'id' => $this->publication->publicId,
            'type' => $this->publication->type->value,
            'slug' => $this->publication->slug,
            'title' => $this->publication->title,
            'excerpt' => $this->publication->excerpt,
            'body_html' => $this->publication->bodyHtml,
            'author' => $this->publication->authorName,
            'published_at' => $this->publication->publishedAt?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'updated_at' => $this->publication->updatedAt->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'url' => $base !== '' ? $base . '/publications/' . rawurlencode($this->publication->slug) : null,
        ];
    }
}
