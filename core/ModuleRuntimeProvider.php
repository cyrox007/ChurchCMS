<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

interface ModuleRuntimeProvider
{
    public function moduleId(): string;

    /** @return array<string,object> */
    public function capabilities(): array;

    public function boot(): void;
}
