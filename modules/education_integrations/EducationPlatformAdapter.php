<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

interface EducationPlatformAdapter
{
    public function id(): string;

    public function label(): string;

    /** @return array{ok:bool,message:string,metadata:array<string,mixed>} */
    public function testConnection(): array;
}
