<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
