<?php

namespace Core\Contracts;

use Core\Integration\IntegrationCredentials;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Contract for OAuth2 client-credentials provider factories.
 *
 * Each integration that uses OAuth2 (LetsPeppol, Pagero, Sovos, …) provides
 * a concrete factory that wires up the provider-specific OAuth endpoint URLs.
 * This interface lets the shared AbstractOAuthGatewayClient accept any factory
 * without depending on a concrete implementation.
 */
interface OAuthProviderFactoryInterface
{
    /**
     * Build an OAuth2 GenericProvider for the given credentials and base URL.
     *
     * @throws \InvalidArgumentException When the base URL is missing, invalid, or not HTTPS.
     */
    public function make(IntegrationCredentials $credentials, string $baseUrl): GenericProvider;
}
