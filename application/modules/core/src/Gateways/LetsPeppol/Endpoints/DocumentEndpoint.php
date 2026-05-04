<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class DocumentEndpoint
{
    public function __construct(private LetsPeppolGatewayClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function get(string $documentId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('documents.get', ['document_id' => $documentId]));
    }
}
