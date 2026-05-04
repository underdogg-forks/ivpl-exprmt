<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class CreditNoteEndpoint
{
    public function __construct(private LetsPeppolGatewayClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function send(array $payload): ApiResponseDto
    {
        return $this->transformer->transform($this->client->post('credit_notes.send', $payload));
    }

    public function status(int $creditNoteId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('credit_notes.status', ['credit_note_id' => $creditNoteId]));
    }
}
