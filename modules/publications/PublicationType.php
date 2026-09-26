<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

enum PublicationType: string
{
    case News = 'news';
    case Article = 'article';
    case Announcement = 'announcement';
    case Sermon = 'sermon';
    case Interview = 'interview';
    case Document = 'document';
}
