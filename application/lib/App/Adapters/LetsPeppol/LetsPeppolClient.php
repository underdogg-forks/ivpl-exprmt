<?php

namespace App\Adapters\LetsPeppol;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class LetsPeppolClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUrl,
        private array $endpoints = [],
        private array $settings = [],
    ) {
    }

    /**
     * Generic transport method used by endpoint clients.
     *
     * Request options may include JSON body, query, headers, etc.
     * Response is the raw PSR-7 response object from Guzzle.
     */
    public function request(string $method, string $endpointOrPath, array $options = []): ResponseInterface
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
