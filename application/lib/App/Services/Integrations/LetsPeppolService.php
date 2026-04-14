<?php

namespace App\Services\Integrations;

use App\Adapters\LetsPeppol\Endpoints\InvoiceClient;
use App\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;
use GuzzleHttp\Client;

class LetsPeppolService
{
    public function __construct(private IntegrationSettingsService $settingsService)
    {
    }

    public function validateParticipantId(string $peppolId): bool
    {
        $settings = $this->settingsService->letsPeppolSettings();
        if (empty($settings['base_url'])) {
            return false;
        }

        $token = $this->settingsService->activeTokenOrCreate();
        $participantClient = new ParticipantClient($this->makeClient($settings));

        return $participantClient->validatePeppolId($peppolId, $token);
    }

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

        $invoiceClient = new InvoiceClient($this->makeClient($settings));
        $response = $invoiceClient->sendInvoice($token, $payload);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    private function makeClient(array $settings): LetsPeppolClient
    {
        return new LetsPeppolClient(
            new Client(),
            $settings['base_url'],
            [
                'participants.validate' => 'api/participants/validate',
                'invoices.send' => 'api/invoices',
            ],
            $settings
        );
    }
}
