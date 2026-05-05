<?php

namespace Core\Adapters\Pagero;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class PageroClient
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
     * Returns the raw PSR-7 response object when the underlying client does not
     * throw. With default Guzzle behavior, HTTP 4xx/5xx responses may raise an
     * exception instead of returning a response.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $method, string $endpointOrPath, array $options = []): ResponseInterface
    {
        $path = $this->endpoints[$endpointOrPath] ?? $endpointOrPath;

        // Auth and Accept headers are always applied; caller-supplied headers take precedence.
        $options['headers'] = array_merge($this->buildAuthHeaders(), $options['headers'] ?? []);

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

        if (!empty($token)) {
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
