<?php

namespace Core\Gateways\StoreCove;

use Core\Gateways\ApiClient;
use GuzzleHttp\ClientInterface;

/**
 * StoreCove gateway API client.
 *
 * StoreCove uses a static API key for Bearer token authentication.
 * No OAuth2 handshake is required — the key is stored in integration settings
 * and injected at construction time.
 *
 * Endpoint mapping covers all StoreCove Peppol network operations.
 */
class StoreCoveGatewayClient extends ApiClient
{
    /**
     * StoreCove API endpoints mapping.
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
        ?ClientInterface $client = null
    ) {
        parent::__construct($baseUri, $settings, $client);

        // Inject the stored API key as the access token
        if (!empty($settings['api_key'])) {
            $this->setAccessToken($settings['api_key']);
        }
    }

    /**
     * {@inheritDoc}
     *
     * Build StoreCove-specific headers with Bearer API key authorization.
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
     * StoreCove uses a static API key — no authorization handshake needed.
     * The token is already injected in the constructor from settings.
     */
    public function authorize(): void
    {
        // Static API key — no OAuth or HTTP call required.
    }
}
