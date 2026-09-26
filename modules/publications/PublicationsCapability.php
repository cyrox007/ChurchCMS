<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

final class PublicationsCapability
{
    public function repository(): PublicationRepository
    {
        return PublicationRepository::fromDatabase();
    }

    public function service(): PublicationService
    {
        return PublicationService::fromDatabase();
    }
}
