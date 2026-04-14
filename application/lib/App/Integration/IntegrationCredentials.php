<?php


namespace App\Integration;

class IntegrationCredentials
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
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
