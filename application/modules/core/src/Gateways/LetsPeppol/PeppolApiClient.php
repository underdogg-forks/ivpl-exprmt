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

class PeppolApiClient extends BaseClient
{
    private LetsPeppolOAuthProviderFactory $oauthFactory;

    protected array $endpoints = [
        'participants.validate' => 'api/participants/validate',
        'participants.details' => 'api/participants',
        'participants.search' => 'api/participants/search',
        'participants.capabilities' => 'api/participants/capabilities',
        'invoices.send' => 'api/invoices',
        'invoices.status' => 'api/invoices',
        'invoices.cancel' => 'api/invoices/cancel',
        'invoices.resend' => 'api/invoices/resend',
        'credit_notes.send' => 'api/credit-notes',
        'credit_notes.status' => 'api/credit-notes',
        'credit_notes.cancel' => 'api/credit-notes/cancel',
        'transmissions.status' => 'api/transmissions',
        'transmissions.receipt' => 'api/transmissions/receipt',
        'transmissions.errors' => 'api/transmissions/errors',
        'transmissions.list' => 'api/transmissions',
        'transmissions.retry' => 'api/transmissions/retry',
        'documents.get' => 'api/documents',
        'documents.download' => 'api/documents/download',
        'documents.metadata' => 'api/documents/metadata',
        'documents.list' => 'api/documents',
        'documents.archive' => 'api/documents/archive',
    ];

    public function __construct(string $baseUri, array $settings = [], ?ClientInterface $client = null, ?LetsPeppolOAuthProviderFactory $oauthFactory = null)
    {
        parent::__construct($baseUri, $settings, $client);
        $this->oauthFactory = $oauthFactory ?? new LetsPeppolOAuthProviderFactory();
    }

    public function buildHeaders(array $options = []): array
    {
        $headers = [
            'Content-Type' => $options['content_type'] ?? 'application/json',
            'Accept' => $options['accept'] ?? 'application/json',
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
        } catch (ClientException $exception) {
            log_message('error', 'LetsPeppol auth client error: ' . $this->sanitizeForLogging($exception->getMessage()));
            throw $exception;
        } catch (Error $error) {
            log_message('error', 'LetsPeppol auth error: ' . $this->sanitizeForLogging($error->getMessage()));
            throw $error;
        } catch (ErrorException $exception) {
            log_message('error', 'LetsPeppol auth error exception: ' . $this->sanitizeForLogging($exception->getMessage()));
            throw $exception;
        } catch (Throwable $throwable) {
            log_message('error', 'LetsPeppol auth throwable: ' . $this->sanitizeForLogging($throwable->getMessage()));
            throw $throwable;
        }
    }
}
