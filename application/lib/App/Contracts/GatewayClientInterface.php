<?php

namespace App\Contracts;

use Psr\Http\Message\ResponseInterface;

/**
 * Contract for gateway API clients (LetsPeppol, PayPal, Stripe, etc.).
 *
 * This interface defines the common methods that all gateway clients must implement,
 * following the pattern established by PaypalLib.php.
 */
interface GatewayClientInterface
{
    /**
     * Make an HTTP request to the gateway API.
     *
     * This method mimics GuzzleHttp\Client::request() signature.
     *
     * @param  string               $method   HTTP method (GET, POST, etc.)
     * @param  string               $uri      Endpoint URI or path
     * @param  array<string, mixed> $options  Guzzle request options (headers, json, query, etc.)
     * @return ResponseInterface              PSR-7 response
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface;

    /**
     * Build headers for API requests.
     *
     * Implementations should include authorization headers and any gateway-specific headers.
     *
     * @param  array<string, mixed> $options  Optional context-specific headers
     * @return array<string, string>          Headers array
     */
    public function buildHeaders(array $options = []): array;

    /**
     * Authorize with the gateway to obtain access credentials.
     *
     * Implementations may use OAuth2, Bearer tokens, or other authentication methods.
     * This method is typically called during client initialization.
     *
     * @return void
     */
    public function authorize(): void;

    /**
     * Retrieve gateway settings by key.
     *
     * @param  string|null $key      Setting key, or null to return all settings
     * @param  mixed       $default  Default value if key not found
     * @return mixed                 Setting value or all settings
     */
    public function getSettings(?string $key = null, mixed $default = null): mixed;
}
