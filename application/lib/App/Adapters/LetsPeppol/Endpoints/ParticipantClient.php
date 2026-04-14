<?php

namespace App\Adapters\LetsPeppol\Endpoints;

use App\Adapters\LetsPeppol\LetsPeppolClient;
use App\Enums\RequestMethod;

class ParticipantClient
{
    public function __construct(private LetsPeppolClient $client)
    {
    }

    /**
     * Validates participant ID in LetsPeppol registry.
     *
     * Request query example:
     * GET /api/participants/validate?peppol_id=0088:123456789
     *
     * Response JSON example:
     * {"valid":true,"participant":{"peppol_id":"0088:123456789"}}
     */
    public function validatePeppolId(string $peppolId, ?string $accessToken = null): bool
    {
        try {
            $options = [
                'query' => ['peppol_id' => $peppolId],
            ];

            if ($accessToken) {
                $options['headers'] = ['Authorization' => 'Bearer ' . $accessToken];
            }

            $response = $this->client->request(RequestMethod::GET->value, 'participants.validate', $options);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $throwable) {
            log_message('error', 'LetsPeppol participant validation failed: ' . $throwable->getMessage());

            return false;
        }
    }
}
