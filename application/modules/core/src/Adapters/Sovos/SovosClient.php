<?php

namespace Core\Adapters\Sovos;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class SovosClient
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
     * Returns the raw PSR-7 response object when the HTTP client does not
     * convert the response into an exception. With Guzzle's default
     * `http_errors` behavior, 4xx and 5xx responses will throw instead of
     * returning a response object.
     */
    public function request(string $method, string $endpointOrPath, array $options = []): ResponseInterface
    {
        $path = $this->endpoints[$endpointOrPath] ?? $endpointOrPath;

        return $this->httpClient->request($method, rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/'), $options);
    }

    /**
     * Build authentication headers for requests.
     *
     * Returns headers array with Accept and optional Bearer token.
     */
    public function buildAuthHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];

        $token = $this->settings('access_token');

        if ($token !== null && trim($token) !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    public function settings(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->settings;
        }

        return $this->settings[$key] ?? $default;
    }
}
