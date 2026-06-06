<?php

namespace Core\Gateways;

use Core\Contracts\OAuthProviderFactoryInterface;
use Core\Integration\IntegrationCredentials;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * Shared OAuth2 client-credentials base for gateway API clients.
 *
 * Concrete clients (PageroGatewayClient, SovosGatewayClient, …) extend this
 * class and only need to provide:
 *   - a protected $endpoints array with the provider's endpoint map
 *   - createDefaultFactory() returning the provider-specific factory
 *   - providerName() returning a human-readable name for log messages
 *
 * This eliminates duplicated authorize(), buildHeaders(), and hasCredentials()
 * implementations across every OAuth-based gateway.
 */
abstract class AbstractOAuthGatewayClient extends ApiClient
{
    protected OAuthProviderFactoryInterface $oauthFactory;

    public function __construct(
        string $baseUri,
        array $settings = [],
        ?ClientInterface $client = null,
        ?OAuthProviderFactoryInterface $oauthFactory = null
    ) {
        parent::__construct($baseUri, $settings, $client);

        $this->oauthFactory = $oauthFactory ?? $this->createDefaultFactory();

        // Auto-authorize on construction if credentials are available.
        if ($this->hasCredentials()) {
            $this->authorize();
        }
    }

    /**
     * Return the provider-specific OAuth factory to use when none is injected.
     *
     * Subclasses instantiate their own concrete factory here so that tests can
     * still inject a mock via the constructor.
     */
    abstract protected function createDefaultFactory(): OAuthProviderFactoryInterface;

    /**
     * Return a short provider name used in log messages (e.g. "Pagero").
     */
    abstract protected function providerName(): string;

    /**
     * {@inheritDoc}
     *
     * Build Bearer-token authorization headers shared by all OAuth providers.
     */
    public function buildHeaders(array $options = []): array
    {
        $headers = [
            'Content-Type' => $options['content_type'] ?? 'application/json',
            'Accept'       => $options['accept'] ?? 'application/json',
        ];

        $token = $this->getAccessToken();

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if (!empty($options['extra_headers'])) {
            $headers = array_merge($headers, $options['extra_headers']);
        }

        return $headers;
    }

    /**
     * {@inheritDoc}
     *
     * Authorize using OAuth2 client credentials flow.
     * Exceptions are caught and logged without exposing sensitive details.
     */
    public function authorize(): void
    {
        if (!$this->hasCredentials()) {
            log_message('debug', $this->providerName() . ' authorization skipped: missing credentials');

            return;
        }

        try {
            log_message('debug', $this->providerName() . ' authorization started');

            $credentials = new IntegrationCredentials(
                $this->settings['client_id'],
                $this->settings['client_secret']
            );

            $provider = $this->oauthFactory->make($credentials, $this->baseUri);
            $token    = $provider->getAccessToken('client_credentials');

            $this->setAccessToken($token->getToken());

            log_message('debug', $this->providerName() . ' authorization completed');
        } catch (ClientException) {
            log_message('error', $this->providerName() . ' authorization failed (oauth_client_exception)');
        } catch (\Throwable) {
            log_message('error', $this->providerName() . ' authorization error (unexpected_exception)');
        }
    }

    /**
     * Inject an access token directly (used when token is cached externally).
     *
     * This allows the provider to use a cached token from IntegrationSettingsService
     * without triggering an OAuth authorization call.
     */
    public function setAccessToken(?string $token): void
    {
        parent::setAccessToken($token);
    }

    /**
     * Check if required OAuth credentials are present in settings.
     */
    protected function hasCredentials(): bool
    {
        return !empty($this->settings['client_id'])
            && !empty($this->settings['client_secret']);
    }
}
