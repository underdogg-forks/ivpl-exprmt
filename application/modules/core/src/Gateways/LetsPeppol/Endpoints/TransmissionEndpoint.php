<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\PeppolApiClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class TransmissionEndpoint
{
    public function __construct(private PeppolApiClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function status(string $transmissionId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('transmissions.status', ['transmission_id' => $transmissionId]));
    }

    public function receipt(string $transmissionId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('transmissions.receipt', ['transmission_id' => $transmissionId]));
    }

    public function errors(string $transmissionId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('transmissions.errors', ['transmission_id' => $transmissionId]));
    }

    public function listing(array $filters = []): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('transmissions.list', $filters));
    }

    public function retry(string $transmissionId, ?string $reason = null): ApiResponseDto
    {
        $payload = ['transmission_id' => $transmissionId];
        if ($reason !== null) {
            $payload['retry_reason'] = $reason;
        }

        return $this->transformer->transform($this->client->post('transmissions.retry', $payload));
    }
}
