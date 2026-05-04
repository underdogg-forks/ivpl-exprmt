<?php

namespace Core\Adapters\StoreCove\Endpoints;

use Core\Adapters\StoreCove\StoreCoveClient;
use Core\Enums\RequestMethod;

class ParticipantClient
{
    public function __construct(private StoreCoveClient $client)
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
