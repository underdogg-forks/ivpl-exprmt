<?php

namespace App\Providers;

use App\Contracts\IntegrationProviderInterface;
use App\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use App\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use App\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use App\Services\Integrations\IntegrationSettingsService;

/**
 * LetsPeppol gateway provider.
 *
 * Implements the generic IntegrationProviderInterface using the new gateway pattern.
 * Follows the architectural pattern from PaypalLib.php with request(), buildHeaders(),
 * and authorize() methods centralized in the gateway client.
 */
class LetsPeppolGatewayProvider implements IntegrationProviderInterface
{
    public function __construct(
        private IntegrationSettingsService $settingsService
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when LetsPeppol is not configured.
     */
    public function validateParticipant(string $participantId): bool
    {
        $gateway = $this->createGatewayClient();

        if ($gateway === null) {
            return false;
        }

        $endpoint = new ParticipantEndpoint($gateway);

        return $endpoint->validatePeppolId($participantId);
    }

    /**
     * {@inheritDoc}
     *
     * Returns false immediately when LetsPeppol is not configured.
     */
    public function sendInvoice(array $payload): bool
    {
        $gateway = $this->createGatewayClient();

        if ($gateway === null) {
            return false;
        }

        $endpoint = new InvoiceEndpoint($gateway);
        $response = $endpoint->sendInvoice($payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * Create and configure a gateway client from stored settings.
     *
     * Returns null if required settings are missing (base_url, credentials).
     * The gateway client handles authorization automatically on construction.
     */
    private function createGatewayClient(): ?LetsPeppolGatewayClient
    {
        $settings = $this->settingsService->letsPeppolSettings();

        if (empty($settings['base_url'])) {
            return null;
        }

        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            return null;
        }

        return new LetsPeppolGatewayClient($settings['base_url'], $settings);
    }
}
