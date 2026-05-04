<?php

namespace Core\Adapters\Pagero\Endpoints;

use Core\Adapters\Pagero\PageroClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class ParticipantClient
{
    public function __construct(private PageroClient $client)
    {
    }

    /**
     * Request query JSON: {"peppol_id":"0088:123456789"}
     * Response JSON: {"valid":true}
     */
    public function validatePeppolId(string $peppolId): bool
    {
        try {
            $response = $this->client->request(RequestMethod::GET->value, 'participants.validate', ['headers' => $this->authHeaders(), 'query' => ['peppol_id' => $peppolId]]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Request query JSON: {"peppol_id":"0088:123456789"} */
    public function getDetails(string $peppolId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'participants.details', ['query' => ['peppol_id' => $peppolId]]);
    }

    /** Request query JSON: {"query":"Acme","country":"SE"} */
    public function search(string $query, ?string $country = null): ResponseInterface
    {
        $queryParams = ['query' => $query];
        if ($country !== null) { $queryParams['country'] = $country; }
        return $this->client->request(RequestMethod::GET->value, 'participants.search', ['query' => $queryParams]);
    }

    /** Request query JSON: {"peppol_id":"0088:123456789"} */
    public function getCapabilities(string $peppolId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'participants.capabilities', ['query' => ['peppol_id' => $peppolId]]);
    }

    /** Response headers JSON: {"Authorization":"Bearer <token>"} */
    private function authHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        $token = $this->client->settings('access_token');
        if ($token !== null) { $headers['Authorization'] = 'Bearer ' . $token; }
        return $headers;
    }
}
