<?php

namespace App\Gateways;

use App\Contracts\GatewayClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base API client that wraps Guzzle HTTP client for gateway integrations.
 *
 * This class provides a decorated request() method that mimics GuzzleClient::request()
 * while adding common functionality like automatic authorization, header building,
 * and endpoint mapping.
 *
 * Concrete gateway clients (LetsPeppol, PayPal, Stripe) should extend this class
 * and implement the abstract methods for gateway-specific behavior.
 */
abstract class ApiClient implements GatewayClientInterface
{
    protected ClientInterface $client;

    protected string $baseUri;

    protected array $settings;

    protected ?string $accessToken = null;

    /**
     * Endpoint mapping: logical names to actual paths.
     * Example: ['invoices.send' => 'api/invoices', 'participants.validate' => 'api/participants/validate']
     */
    protected array $endpoints = [];

    public function __construct(string $baseUri, array $settings = [], ?ClientInterface $client = null)
    {
        $this->baseUri  = rtrim($baseUri, '/');
        $this->settings = $settings;
        $this->client   = $client ?? new Client(['base_uri' => $this->baseUri]);
    }

    /**
     * {@inheritDoc}
     *
     * Maps endpoint keys to actual paths and delegates to the HTTP client.
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        // Map endpoint key to path if it exists in the endpoints array
        $path = $this->endpoints[$uri] ?? $uri;

        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Build full URI
        $fullUri = $this->baseUri . $path;

        return $this->client->request($method, $fullUri, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getSettings(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->settings;
        }

        return $this->settings[$key] ?? $default;
    }

    /**
     * Get the access token (for use by concrete implementations).
     */
    protected function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Set the access token (for use by concrete implementations).
     */
    protected function setAccessToken(?string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * {@inheritDoc}
     *
     * Concrete implementations must provide gateway-specific header building logic.
     */
    abstract public function buildHeaders(array $options = []): array;

    /**
     * {@inheritDoc}
     *
     * Concrete implementations must provide gateway-specific authorization logic.
     */
    abstract public function authorize(): void;
}
