<?php


namespace Core\Integration;

class IntegrationSetting
{
    public function __construct(
        private string $key,
        private string $value,
        private bool $isEncrypted = false,
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
