<?php

namespace Core\Providers;

use Core\Adapters\StoreCove\Endpoints\InvoiceClient;
use Core\Adapters\StoreCove\Endpoints\ParticipantClient;
use Core\Adapters\StoreCove\StoreCoveClientFactory;
use Core\Contracts\IntegrationProviderInterface;
use Core\Services\Integrations\IntegrationSettingsService;

/**
 * StoreCove integration provider.
 *
 * Implements the generic IntegrationProviderInterface so the rest of the
 * application can interact with StoreCove through the shared contract.
 *
 * StoreCove authenticates via a static API key rather than OAuth2; the key is
 * read from settings and injected as the `access_token` in the adapter client.
 */
class StoreCoveProvider implements IntegrationProviderInterface
{
    public function __construct(
        private IntegrationSettingsService $settingsService,
        private StoreCoveClientFactory $clientFactory = new StoreCoveClientFactory(),
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when StoreCove is not configured.
     */
    public function validateParticipant(string $participantId): bool
    {
        $settings = $this->settingsService->storeCoveSettings();

        if (empty($settings['base_url']) || empty($settings['api_key'])) {
            return false;
        }

        // The adapter client uses 'access_token' setting for Bearer auth.
        $settings['access_token'] = $settings['api_key'];
        $client = $this->clientFactory->create($settings['base_url'], $settings);

        return (new ParticipantClient($client))->validatePeppolId($participantId);
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when StoreCove is not configured.
     */
    public function sendInvoice(array $payload): bool
    {
        $settings = $this->settingsService->storeCoveSettings();

        if (empty($settings['base_url']) || empty($settings['api_key'])) {
            return false;
        }

        $settings['access_token'] = $settings['api_key'];
        $client   = $this->clientFactory->create($settings['base_url'], $settings);
        $response = (new InvoiceClient($client))->sendInvoice($payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
