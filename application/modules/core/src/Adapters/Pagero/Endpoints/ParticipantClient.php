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
     * Validate participant in Peppol registry.
     *
     * Request query JSON:
     * {"peppol_id":"0088:123456789"}
     *
     * Response JSON:
     * {"valid":true}
     */
    public function validatePeppolId(string $peppolId): bool
    {
        try {
            $response = $this->client->request(RequestMethod::GET->value, 'participants.validate', [
                'headers' => $this->client->buildAuthHeaders(),
                'query' => ['peppol_id' => $peppolId],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get full participant details.
     *
     * Request query JSON:
     * {"peppol_id":"0088:123456789"}
     *
     * Response JSON:
     * {"peppol_id":"0088:123456789","name":"Test Company AB","country_code":"SE"}
     */
    public function getDetails(string $peppolId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'participants.details', [
            'query' => ['peppol_id' => $peppolId],
        ]);
    }

    /**
     * Search participants by query and optional country.
     *
     * Request query JSON:
     * {"query":"Acme","country":"SE"}
     *
     * Response JSON:
     * {"participants":[{"peppol_id":"0088:123456789","name":"Acme AB"}],"total":1}
     */
    public function search(string $query, ?string $country = null): ResponseInterface
    {
        $queryParams = ['query' => $query];

        if ($country !== null) {
            $queryParams['country'] = $country;
        }

        return $this->client->request(RequestMethod::GET->value, 'participants.search', [
            'query' => $queryParams,
        ]);
    }

    /**
     * Get participant document capabilities.
     *
     * Request query JSON:
     * {"peppol_id":"0088:123456789"}
     *
     * Response JSON:
     * {"peppol_id":"0088:123456789","document_types":[{"type_id":"urn:oasis...Invoice-2"}]}
     */
    public function getCapabilities(string $peppolId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'participants.capabilities', [
            'query' => ['peppol_id' => $peppolId],
        ]);
    }
}
