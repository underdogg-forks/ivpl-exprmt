<?php

namespace App\Gateways\LetsPeppol;

use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use App\Gateways\ApiClient;
use App\Integration\IntegrationCredentials;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * LetsPeppol gateway API client.
 *
 * Extends the base ApiClient with LetsPeppol-specific authorization (OAuth2)
 * and header building logic. Following the pattern from PaypalLib.php.
 */
class LetsPeppolGatewayClient extends ApiClient
{
    private LetsPeppolOAuthProviderFactory $oauthFactory;

    /**
     * LetsPeppol API endpoints mapping.
     */
    protected array $endpoints = [
        'participants.validate' => 'api/participants/validate',
        'invoices.send'        => 'api/invoices',
    ];

    public function __construct(
        string $baseUri,
        array $settings = [],
        ?ClientInterface $client = null,
        ?LetsPeppolOAuthProviderFactory $oauthFactory = null
    ) {
        parent::__construct($baseUri, $settings, $client);

        $this->oauthFactory = $oauthFactory ?? new LetsPeppolOAuthProviderFactory();

        // Auto-authorize on construction if credentials are available
        if ($this->hasCredentials()) {
            $this->authorize();
        }
    }

    /**
     * {@inheritDoc}
     *
     * Build LetsPeppol-specific headers with Bearer token authorization.
     */
    public function buildHeaders(array $options = []): array
    {
        $headers = [
            'Content-Type' => $options['content_type'] ?? 'application/json',
            'Accept'       => $options['accept'] ?? 'application/json',
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        // Allow additional headers from options
        if (!empty($options['extra_headers'])) {
            $headers = array_merge($headers, $options['extra_headers']);
        }

        return $headers;
    }

    /**
     * {@inheritDoc}
     *
     * Authorize with LetsPeppol using OAuth2 client credentials flow.
     */
    public function authorize(): void
    {
        if (!$this->hasCredentials()) {
            log_message('debug', 'LetsPeppol authorization skipped: missing credentials');

            return;
        }

        try {
            log_message('debug', 'LetsPeppol authorization started');

            $credentials = new IntegrationCredentials(
                $this->settings['client_id'],
                $this->settings['client_secret']
            );

            $provider = $this->oauthFactory->make($credentials, $this->baseUri);
            $token    = $provider->getAccessToken('client_credentials');

            $this->accessToken = $token->getToken();

            log_message('debug', 'LetsPeppol authorization completed');
        } catch (ClientException $e) {
            log_message('error', 'LetsPeppol authorization failed: ' . $this->sanitize($e->getMessage()));
        } catch (\Throwable $e) {
            log_message('error', 'LetsPeppol authorization error: ' . $this->sanitize($e->getMessage()));
        }
    }

    /**
     * Inject an access token directly (used when token is cached externally).
     *
     * This allows the provider to use a cached token from IntegrationSettingsService
     * without triggering OAuth authorization.
     */
    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * Check if required credentials are present in settings.
     */
    private function hasCredentials(): bool
    {
        return !empty($this->settings['client_id'])
            && !empty($this->settings['client_secret']);
    }

    /**
     * Sanitize log messages to prevent log injection.
     */
    private function sanitize(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
