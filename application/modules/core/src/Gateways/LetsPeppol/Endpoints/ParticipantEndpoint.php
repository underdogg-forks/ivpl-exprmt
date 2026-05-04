<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class ParticipantEndpoint
{
    public function __construct(private GatewayClientInterface $gateway)
    {
    }

    /**
     * Validate participant ID in LetsPeppol registry.
     *
     * Request query example:
     * GET /api/participants/validate?peppol_id=0088:123456789
     *
     * Response JSON example (from fixture):
     * {"valid":true,"participant":{"peppol_id":"0088:123456789","name":"Test Company AB"}}
     */
    public function validatePeppolId(string $peppolId): bool
    {
        $response = $this->gateway->request(RequestMethod::GET->value, 'participants.validate', [
            'headers' => $this->gateway->buildHeaders(),
            'query' => ['peppol_id' => $peppolId],
        ]);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    public function getDetails(string $peppolId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'participants.details', ['headers' => $this->gateway->buildHeaders(), 'query' => ['peppol_id' => $peppolId]]);
    }

    public function search(string $query, ?string $country = null): ResponseInterface
    {
        $queryParams = ['query' => $query];
        if ($country !== null) {
            $queryParams['country'] = $country;
        }

        return $this->gateway->request(RequestMethod::GET->value, 'participants.search', ['headers' => $this->gateway->buildHeaders(), 'query' => $queryParams]);
    }

    public function getCapabilities(string $peppolId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'participants.capabilities', ['headers' => $this->gateway->buildHeaders(), 'query' => ['peppol_id' => $peppolId]]);
    }
}
