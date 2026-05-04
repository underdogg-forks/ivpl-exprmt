<?php

namespace Core\Gateways\LetsPeppol;

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Gateways\LetsPeppol\Client\BaseClient;
use Core\Integration\IntegrationCredentials;
use Error;
use ErrorException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Throwable;

class LetsPeppolGatewayClient extends BaseClient
{
    private LetsPeppolOAuthProviderFactory $oauthFactory;

    protected array $endpoints = [
        'participants.validate' => 'api/participants/validate',
        'participants.details' => 'api/participants',
        'participants.search' => 'api/participants/search',
        'participants.capabilities' => 'api/participants/capabilities',
        'invoices.send' => 'api/invoices',
        'invoices.status' => 'api/invoices',
        'credit_notes.send' => 'api/credit-notes',
        'credit_notes.status' => 'api/credit-notes',
        'transmissions.status' => 'api/transmissions',
        'documents.get' => 'api/documents',
    ];

    public function __construct(string $baseUri, array $settings = [], ?ClientInterface $client = null, ?LetsPeppolOAuthProviderFactory $oauthFactory = null)
    {
        parent::__construct($baseUri, $settings, $client);
        $this->oauthFactory = $oauthFactory ?? new LetsPeppolOAuthProviderFactory();
    }

    public function buildHeaders(array $options = []): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        return $headers;
    }

    public function authorize(): void
    {
        try {
            $credentials = new IntegrationCredentials((string) ($this->settings['client_id'] ?? ''), (string) ($this->settings['client_secret'] ?? ''));
            $provider = $this->oauthFactory->make($credentials, $this->baseUri);
            $token = $provider->getAccessToken('client_credentials');
            $this->accessToken = $token->getToken();
        } catch (ClientException|Error|ErrorException|Throwable $exception) {
            log_message('error', 'LetsPeppol auth error: ' . $this->sanitizeForLogging($exception->getMessage()));
            throw $exception;
        }
    }

    public function post(string $endpointKey, array $payload = []): array
    {
        return $this->sendPostRequest($endpointKey, $payload);
    }

    public function get(string $endpointKey, array $query = []): array
    {
        return $this->sendGetRequest($endpointKey, $query);
    }
}
