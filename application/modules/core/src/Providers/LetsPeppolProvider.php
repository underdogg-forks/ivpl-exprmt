<?php

namespace Core\Providers;

use Core\Adapters\LetsPeppol\Endpoints\InvoiceClient;
use Core\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use Core\Adapters\LetsPeppol\LetsPeppolClientFactory;
use Core\Contracts\IntegrationProviderInterface;
use Core\Services\Integrations\IntegrationSettingsService;

/**
 * LetsPeppol integration provider.
 *
 * Implements the generic IntegrationProviderInterface so the rest of the
 * application can interact with LetsPeppol through the shared contract,
 * making it trivial to swap or extend with StoreCove, Stripe, PayPal, etc.
 */
class LetsPeppolProvider implements IntegrationProviderInterface
{
    public function __construct(
        private IntegrationSettingsService $settingsService,
        private LetsPeppolClientFactory $clientFactory = new LetsPeppolClientFactory(),
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when LetsPeppol is not configured.
     */
    public function validateParticipant(string $participantId): bool
    {
        $settings = $this->settingsService->letsPeppolSettings();
        if (empty($settings['base_url'])) {
            return false;
        }

        $token  = $this->settingsService->activeTokenOrCreate();
        $client = $this->clientFactory->create($settings['base_url'], $settings);

        return (new ParticipantClient($client))->validatePeppolId($participantId, $token);
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when LetsPeppol is not configured or
     * when no access token could be obtained.
     */
    public function sendInvoice(array $payload): bool
    {
        $settings = $this->settingsService->letsPeppolSettings();
        if (empty($settings['base_url'])) {
            return false;
        }

        $token = $this->settingsService->activeTokenOrCreate();
        if ( ! $token) {
            return false;
        }

        $settings['access_token'] = $token;
        $client   = $this->clientFactory->create($settings['base_url'], $settings);
        $response = (new InvoiceClient($client))->sendInvoice($payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
