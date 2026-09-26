<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Withdrawn = 'withdrawn';
}
