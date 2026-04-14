<?php

declare(strict_types=1);

namespace App\Integration;

final class IntegrationCredentials
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }
}
