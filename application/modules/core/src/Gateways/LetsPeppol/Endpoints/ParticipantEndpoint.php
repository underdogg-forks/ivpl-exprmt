<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

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

    /**
     * Get detailed participant information.
     *
     * Request example:
     * GET /api/participants/{peppol_id}
     *
     * Response JSON example (from fixture):
     * {
     *   "peppol_id": "0088:123456789",
     *   "name": "Test Company AB",
     *   "country_code": "SE",
     *   "registration_date": "2024-01-15",
     *   "capabilities": ["invoice", "credit_note", "order"],
     *   "document_types": [
     *     "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2",
     *     "urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"
     *   ],
     *   "service_metadata": {
     *     "endpoint_url": "https://ap.example.com/peppol",
     *     "transport_profile": "peppol-transport-as4-v2_0"
     *   }
     * }
     *
     * @param  string $peppolId  Peppol participant identifier
     * @return ResponseInterface
     */
    public function getDetails(string $peppolId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'participants.details', [
            'headers' => $headers,
            'query'   => ['peppol_id' => $peppolId],
        ]);
    }

    /**
     * Search for participants by name or organization number.
     *
     * Request example:
     * GET /api/participants/search?query=Acme+Corp&country=SE
     *
     * Response JSON example (from fixture):
     * {
     *   "participants": [
     *     {
     *       "peppol_id": "0088:123456789",
     *       "name": "Acme Corporation AB",
     *       "country_code": "SE",
     *       "match_score": 0.95
     *     },
     *     {
     *       "peppol_id": "0088:987654321",
     *       "name": "Acme Corp Ltd",
     *       "country_code": "SE",
     *       "match_score": 0.87
     *     }
     *   ],
     *   "total": 2,
     *   "query": "Acme Corp"
     * }
     *
     * @param  string $query         Search query (company name or org number)
     * @param  string|null $country  Optional ISO country code filter
     * @return ResponseInterface
     */
    public function search(string $query, ?string $country = null): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        $queryParams = ['query' => $query];
        if ($country !== null) {
            $queryParams['country'] = $country;
        }

        return $this->gateway->request(RequestMethod::GET->value, 'participants.search', [
            'headers' => $headers,
            'query'   => $queryParams,
        ]);
    }

    /**
     * Get participant capabilities (supported document types).
     *
     * Request example:
     * GET /api/participants/{peppol_id}/capabilities
     *
     * Response JSON example (from fixture):
     * {
     *   "peppol_id": "0088:123456789",
     *   "capabilities": ["invoice", "credit_note", "order", "order_response"],
     *   "document_types": [
     *     {
     *       "type_id": "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2",
     *       "process_id": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
     *       "transport_profile": "peppol-transport-as4-v2_0"
     *     },
     *     {
     *       "type_id": "urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2",
     *       "process_id": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
     *       "transport_profile": "peppol-transport-as4-v2_0"
     *     }
     *   ]
     * }
     *
     * @param  string $peppolId  Peppol participant identifier
     * @return ResponseInterface
     */
    public function getCapabilities(string $peppolId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'participants.capabilities', [
            'headers' => $headers,
            'query'   => ['peppol_id' => $peppolId],
        ]);
    }
}
