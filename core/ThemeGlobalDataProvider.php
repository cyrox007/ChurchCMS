<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface ThemeGlobalDataProvider
{
    /**
     * @return array<string,mixed>
     */
    public function data(): array;
}
