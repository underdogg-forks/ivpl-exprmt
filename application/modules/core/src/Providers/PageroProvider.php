<?php

namespace Core\Providers;

use Core\Adapters\Pagero\Endpoints\InvoiceClient;
use Core\Adapters\Pagero\Endpoints\ParticipantClient;
use Core\Adapters\Pagero\PageroClientFactory;
use Core\Contracts\IntegrationProviderInterface;
use Core\Services\Integrations\IntegrationSettingsService;

/**
 * Pagero integration provider.
 *
 * Implements the generic IntegrationProviderInterface so the rest of the
 * application can interact with Pagero through the shared contract.
 *
 * Pagero authenticates via OAuth2 client credentials; the access token is
 * retrieved from the settings service cache or freshly obtained.
 */
class PageroProvider implements IntegrationProviderInterface
{
    public function __construct(
        private IntegrationSettingsService $settingsService,
        private PageroClientFactory $clientFactory = new PageroClientFactory(),
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when Pagero is not configured.
     */
    public function validateParticipant(string $participantId): bool
    {
        $settings = $this->settingsService->pageroSettings();

        if (empty($settings['base_url'])) {
            return false;
        }

        $settings['access_token'] = $this->settingsService->activePageroTokenOrCreate();
        $client = $this->clientFactory->create($settings['base_url'], $settings);

        return (new ParticipantClient($client))->validatePeppolId($participantId);
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when Pagero is not configured or when no
     * access token could be obtained.
     */
    public function sendInvoice(array $payload): bool
    {
        $settings = $this->settingsService->pageroSettings();

        if (empty($settings['base_url'])) {
            return false;
        }

        $token = $this->settingsService->activePageroTokenOrCreate();

        if (!$token) {
            return false;
        }

        $settings['access_token'] = $token;
        $client   = $this->clientFactory->create($settings['base_url'], $settings);
        $response = (new InvoiceClient($client))->sendInvoice($payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
