<?php

namespace Core\Providers;

use Core\Adapters\Sovos\Endpoints\InvoiceClient;
use Core\Adapters\Sovos\Endpoints\ParticipantClient;
use Core\Adapters\Sovos\SovosClientFactory;
use Core\Contracts\IntegrationProviderInterface;
use Core\Services\Integrations\IntegrationSettingsService;

/**
 * Sovos integration provider.
 *
 * Implements the generic IntegrationProviderInterface so the rest of the
 * application can interact with Sovos through the shared contract.
 *
 * Sovos authenticates via OAuth2 client credentials; the access token is
 * retrieved from the settings service cache or freshly obtained.
 */
class SovosProvider implements IntegrationProviderInterface
{
    public function __construct(
        private IntegrationSettingsService $settingsService,
        private SovosClientFactory $clientFactory = new SovosClientFactory(),
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when Sovos is not configured.
     */
    public function validateParticipant(string $participantId): bool
    {
        $settings = $this->settingsService->sovosSettings();

        if (empty($settings['base_url'])) {
            return false;
        }

        $settings['access_token'] = $this->settingsService->activeSovosTokenOrCreate();
        $client = $this->clientFactory->create($settings['base_url'], $settings);

        return (new ParticipantClient($client))->validatePeppolId($participantId);
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when Sovos is not configured or when no
     * access token could be obtained.
     */
    public function sendInvoice(array $payload): bool
    {
        $settings = $this->settingsService->sovosSettings();

        if (empty($settings['base_url'])) {
            return false;
        }

        $token = $this->settingsService->activeSovosTokenOrCreate();

        if (!$token) {
            return false;
        }

        $settings['access_token'] = $token;
        $client   = $this->clientFactory->create($settings['base_url'], $settings);
        $response = (new InvoiceClient($client))->sendInvoice($payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
