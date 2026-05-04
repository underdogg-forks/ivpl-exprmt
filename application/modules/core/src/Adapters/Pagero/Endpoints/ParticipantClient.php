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

    public function validatePeppolId(string $peppolId): bool
    {
        try {
            $response = $this->client->request(RequestMethod::GET->value, 'participants.validate', [
                'headers' => $this->authHeaders(),
                'query' => ['peppol_id' => $peppolId],
            ]);
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getDetails(string $peppolId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'participants.details', ['query' => ['peppol_id' => $peppolId]]);
    }

    public function search(string $query, ?string $country = null): ResponseInterface
    {
        $queryParams = ['query' => $query];
        if ($country !== null) {
            $queryParams['country'] = $country;
        }
        return $this->client->request(RequestMethod::GET->value, 'participants.search', ['query' => $queryParams]);
    }

    public function getCapabilities(string $peppolId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'participants.capabilities', ['query' => ['peppol_id' => $peppolId]]);
    }

    private function authHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        $token = $this->client->settings('access_token');
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
