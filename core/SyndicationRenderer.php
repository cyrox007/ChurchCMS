<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface SyndicationRenderer
{
    public function contentType(): string;

    public function render(SyndicationFeed $feed): string;
}
