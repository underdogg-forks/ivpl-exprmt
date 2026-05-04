<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class TransmissionEndpoint
{
    public function __construct(private LetsPeppolGatewayClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function status(string $transmissionId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('transmissions.status', ['transmission_id' => $transmissionId]));
    }
}
