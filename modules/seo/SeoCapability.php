<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Seo;

use ChurchCMS\Modules\Publications\Publication;

final class SeoCapability
{
    /** @return array<string,mixed> */
    public function metaForPublication(Publication $publication): array
    {
        return PublicationSeoRepository::fromDatabase()->metaFor($publication);
    }

    /** @return array<string,mixed> */
    public function formForPublication(Publication $publication): array
    {
        return PublicationSeoRepository::fromDatabase()->formFor($publication);
    }

    /** @param array<string,mixed> $input */
    public function savePublication(Publication $publication, array $input): void
    {
        PublicationSeoRepository::fromDatabase()->save($publication, $input);
    }
}
