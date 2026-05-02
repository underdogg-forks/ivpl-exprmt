<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;

/**
 * LetsPeppol Participant endpoint client.
 *
 * Handles participant validation in the LetsPeppol/Peppol registry.
 * Uses the gateway client's request() method and buildHeaders() for consistency.
 */
class ParticipantEndpoint
{
    public function __construct(
        private GatewayClientInterface $gateway
    ) {
    }

    /**
     * Validate participant ID in LetsPeppol registry.
     *
     * Request query example:
     * GET /api/participants/validate?peppol_id=0088:123456789
     *
     * Response JSON example (from fixture):
     * {"valid":true,"participant":{"peppol_id":"0088:123456789","name":"Test Company AB"}}
     *
     * @param  string $peppolId  Peppol participant identifier
     * @return bool              True if participant is valid and registered
     */
    public function validatePeppolId(string $peppolId): bool
    {
        try {
            $headers = $this->gateway->buildHeaders();

            $response = $this->gateway->request(RequestMethod::GET->value, 'participants.validate', [
                'headers' => $headers,
                'query'   => ['peppol_id' => $peppolId],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $throwable) {
            $sanitizedMessage = str_replace(["\r", "\n"], '', $throwable->getMessage());
            log_message('error', 'LetsPeppol participant validation failed: ' . $sanitizedMessage);

            return false;
        }
    }
}
