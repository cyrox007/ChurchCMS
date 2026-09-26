<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Spam = 'spam';
}
