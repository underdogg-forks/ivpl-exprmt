<?php

namespace Core\Gateways\Sovos;

use Core\Adapters\Sovos\Auth\SovosOAuthProviderFactory;
use Core\Contracts\OAuthProviderFactoryInterface;
use Core\Gateways\AbstractOAuthGatewayClient;
use GuzzleHttp\ClientInterface;

/**
 * Sovos gateway API client.
 *
 * Extends AbstractOAuthGatewayClient — all OAuth2 client-credentials logic
 * (authorize, buildHeaders, token injection) lives there.
 * This class only supplies the Sovos endpoint map, the default factory,
 * and the provider name used in log messages.
 */
class SovosGatewayClient extends AbstractOAuthGatewayClient
{
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
        ?OAuthProviderFactoryInterface $oauthFactory = null
    ) {
        parent::__construct($baseUri, $settings, $client, $oauthFactory);
    }

    protected function createDefaultFactory(): OAuthProviderFactoryInterface
    {
        return new SovosOAuthProviderFactory();
    }

    protected function providerName(): string
    {
        return 'Sovos';
    }
}
