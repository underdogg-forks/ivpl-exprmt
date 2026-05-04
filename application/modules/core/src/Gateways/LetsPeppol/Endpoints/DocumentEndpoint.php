<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\PeppolApiClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class DocumentEndpoint
{
    public function __construct(private PeppolApiClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function get(string $documentId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('documents.get', ['document_id' => $documentId]));
    }

    public function download(string $documentId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('documents.download', ['document_id' => $documentId]));
    }

    public function metadata(string $documentId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('documents.metadata', ['document_id' => $documentId]));
    }

    public function listing(array $filters = []): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('documents.list', $filters));
    }

    public function archive(string $documentId, ?string $reason = null): ApiResponseDto
    {
        $payload = ['document_id' => $documentId];
        if ($reason !== null) {
            $payload['archive_reason'] = $reason;
        }

        return $this->transformer->transform($this->client->post('documents.archive', $payload));
    }
}
