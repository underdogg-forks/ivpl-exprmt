<?php

declare(strict_types=1);

namespace App\Integration;

final class IntegrationSetting
{
    public function __construct(
        private readonly string $key,
        private readonly string $value,
        private readonly bool $isEncrypted = false,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }
}
