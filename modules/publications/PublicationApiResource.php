<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\ApiResource;
use ChurchCMS\Core\Config;

final class PublicationApiResource implements ApiResource
{
    /**
     * @param array{
     *     categories?:list<array{public_id:string,name:string,slug:string}>,
     *     tags?:list<array{public_id:string,name:string,slug:string}>
     * } $taxonomy
     */
    public function __construct(
        private readonly Publication $publication,
        private readonly array $taxonomy = [],
    ) {
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
            'comments_enabled' => $this->publication->commentsEnabled,
            'categories' => array_values($this->taxonomy['categories'] ?? []),
            'tags' => array_values($this->taxonomy['tags'] ?? []),
            'published_at' => $this->publication->publishedAt?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'updated_at' => $this->publication->updatedAt->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'url' => $base !== '' ? $base . '/publications/' . rawurlencode($this->publication->slug) : null,
        ];
    }
}
