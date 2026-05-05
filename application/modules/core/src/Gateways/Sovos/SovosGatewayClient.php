<?php

namespace Core\Gateways\Sovos;

use Core\Adapters\Sovos\Auth\SovosOAuthProviderFactory;
use Core\Gateways\ApiClient;
use Core\Integration\IntegrationCredentials;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * Sovos gateway API client.
 *
 * Extends the base ApiClient with Sovos-specific authorization (OAuth2
 * client credentials flow) and header building logic.
 *
 * On construction, if credentials (client_id + client_secret) are present,
 * the client will automatically obtain an OAuth2 access token and store it
 * internally so that buildHeaders() can inject it into every request.
 */
class SovosGatewayClient extends ApiClient
{
    private SovosOAuthProviderFactory $oauthFactory;

    /**
     * Sovos API endpoints mapping.
     */
    protected array $endpoints = [
        // Participant endpoints
        'participants.validate'     => 'api/participants/validate',
        'participants.details'      => 'api/participants',
        'participants.search'       => 'api/participants/search',
        'participants.capabilities' => 'api/participants/capabilities',

        // Invoice endpoints
        'invoices.send'   => 'api/invoices',
        'invoices.status' => 'api/invoices',
        'invoices.cancel' => 'api/invoices/cancel',
        'invoices.resend' => 'api/invoices/resend',

        // Credit note endpoints
        'credit_notes.send'   => 'api/credit-notes',
        'credit_notes.status' => 'api/credit-notes',
        'credit_notes.cancel' => 'api/credit-notes/cancel',

        // Transmission endpoints
        'transmissions.status'  => 'api/transmissions',
        'transmissions.receipt' => 'api/transmissions/receipt',
        'transmissions.errors'  => 'api/transmissions/errors',
        'transmissions.list'    => 'api/transmissions',
        'transmissions.retry'   => 'api/transmissions/retry',

        // Document endpoints
        'documents.get'      => 'api/documents',
        'documents.download' => 'api/documents/download',
        'documents.metadata' => 'api/documents/metadata',
        'documents.list'     => 'api/documents',
        'documents.archive'  => 'api/documents/archive',
    ];

    public function __construct(
        string $baseUri,
        array $settings = [],
        ?ClientInterface $client = null,
        ?SovosOAuthProviderFactory $oauthFactory = null
    ) {
        parent::__construct($baseUri, $settings, $client);

        $this->oauthFactory = $oauthFactory ?? new SovosOAuthProviderFactory();

        // Auto-authorize on construction if credentials are available
        if ($this->hasCredentials()) {
            $this->authorize();
        }
    }

    /**
     * {@inheritDoc}
     *
     * Build Sovos-specific headers with Bearer token authorization.
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
     * Authorize with Sovos using OAuth2 client credentials flow.
     */
    public function authorize(): void
    {
        if (!$this->hasCredentials()) {
            log_message('debug', 'Sovos authorization skipped: missing credentials');

            return;
        }

        try {
            log_message('debug', 'Sovos authorization started');

            $credentials = new IntegrationCredentials(
                $this->settings['client_id'],
                $this->settings['client_secret']
            );

            $provider = $this->oauthFactory->make($credentials, $this->baseUri);
            $token    = $provider->getAccessToken('client_credentials');

            $this->setAccessToken($token->getToken());

            log_message('debug', 'Sovos authorization completed');
        } catch (ClientException $e) {
            log_message('error', 'Sovos authorization failed (oauth_client_exception)');
        } catch (\Throwable $e) {
            log_message('error', 'Sovos authorization error (unexpected_exception)');
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
        parent::setAccessToken($token);
    }

    /**
     * Check if required OAuth credentials are present in settings.
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
