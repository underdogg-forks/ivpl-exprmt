<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class ParticipantEndpoint
{
    public function __construct(private LetsPeppolGatewayClient $client, private ApiResponseTransformer $transformer)
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
}
