<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\ApiResource;

final class PageApiResource implements ApiResource
{
    public function __construct(
        private readonly Page $page,
        private readonly ?string $parentPublicId = null,
    ) {
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->page->publicId,
            'parent_id' => $this->parentPublicId,
            'organization_owner_id' =>
                $this->page->ownerOrganizationPublicId,
            'slug' => $this->page->slug,
            'path' => $this->page->path,
            'title' => $this->page->title,
            'navigation_title' => $this->page->navigationTitle,
            'body_html' => $this->page->bodyHtml,
            'sort_order' => $this->page->sortOrder,
            'published_at' => $this->page->publishedAt
                ?->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM),
            'updated_at' => $this->page->updatedAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM),
        ];
    }
}
