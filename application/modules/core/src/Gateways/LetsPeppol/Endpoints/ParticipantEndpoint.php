<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\PeppolApiClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class ParticipantEndpoint
{
    public function __construct(private PeppolApiClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function validate(string $peppolId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('participants.validate', ['peppol_id' => $peppolId]));
    }

    public function details(string $peppolId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('participants.details', ['peppol_id' => $peppolId]));
    }

    public function search(string $query, ?string $country = null): ApiResponseDto
    {
        $queryParams = ['query' => $query];
        if ($country !== null) {
            $queryParams['country'] = $country;
        }

        return $this->transformer->transform($this->client->get('participants.search', $queryParams));
    }

    public function capabilities(string $peppolId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('participants.capabilities', ['peppol_id' => $peppolId]));
    }
}
