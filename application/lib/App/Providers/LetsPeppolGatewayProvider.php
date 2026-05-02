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
 *
 * Uses IntegrationSettingsService's token cache to avoid unnecessary OAuth requests.
 */
class LetsPeppolGatewayProvider implements IntegrationProviderInterface
{
    private ?LetsPeppolGatewayClient $gateway = null;

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
        $gateway = $this->getGatewayClient();

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
        $gateway = $this->getGatewayClient();

        if ($gateway === null) {
            return false;
        }

        $endpoint = new InvoiceEndpoint($gateway);
        $response = $endpoint->sendInvoice($payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * Get or create the gateway client (singleton per provider instance).
     *
     * Returns null if required settings are missing (base_url, credentials).
     * Uses the cached token from IntegrationSettingsService to avoid re-authorization.
     */
    private function getGatewayClient(): ?LetsPeppolGatewayClient
    {
        if ($this->gateway !== null) {
            return $this->gateway;
        }

        $settings = $this->settingsService->letsPeppolSettings();

        if (empty($settings['base_url'])) {
            return null;
        }

        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            return null;
        }

        // Get cached token from IntegrationSettingsService (or create new one)
        $token = $this->settingsService->activeTokenOrCreate();

        // Create gateway client WITHOUT credentials to skip auto-authorization
        $this->gateway = new LetsPeppolGatewayClient($settings['base_url'], []);

        // If we have a cached token, inject it directly to avoid OAuth call
        if ($token) {
            $this->gateway->setAccessToken($token);
        }

        return $this->gateway;
    }
}
