<?php

namespace App\Adapters\LetsPeppol;

use GuzzleHttp\ClientInterface;

class LetsPeppolClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUrl,
        private array $endpoints = [],
        private array $settings = [],
    ) {
    }

    public function request(string $method, string $endpointOrPath, array $options = [])
    {
        $path = $this->endpoints[$endpointOrPath] ?? $endpointOrPath;

        return $this->httpClient->request($method, rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/'), $options);
    }

    public function settings(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->settings;
        }

        return $this->settings[$key] ?? $default;
    }
}
