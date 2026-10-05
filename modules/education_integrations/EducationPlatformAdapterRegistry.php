<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationIntegrations;

use InvalidArgumentException;

final class EducationPlatformAdapterRegistry
{
    /** @var array<string,EducationPlatformAdapter> */
    private array $adapters = [];

    public function register(EducationPlatformAdapter $adapter): void
    {
        $this->adapters[$adapter->id()] = $adapter;
    }

    public function get(string $id): EducationPlatformAdapter
    {
        $id = trim($id);
        if (!isset($this->adapters[$id])) {
            throw new InvalidArgumentException('Неизвестный адаптер образовательной платформы.');
        }
        return $this->adapters[$id];
    }

    /** @return list<EducationPlatformAdapter> */
    public function all(): array
    {
        return array_values($this->adapters);
    }
}
