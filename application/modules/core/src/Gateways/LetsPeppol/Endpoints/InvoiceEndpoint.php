<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Gateways\LetsPeppol\DTO\ApiResponseDto;
use Core\Gateways\LetsPeppol\PeppolApiClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;

class InvoiceEndpoint
{
    public function __construct(private PeppolApiClient $client, private ApiResponseTransformer $transformer)
    {
    }

    public function send(array $payload): ApiResponseDto
    {
        return $this->transformer->transform($this->client->post('invoices.send', $payload));
    }

    public function status(int $invoiceId): ApiResponseDto
    {
        return $this->transformer->transform($this->client->get('invoices.status', ['invoice_id' => $invoiceId]));
    }

    public function cancel(int $invoiceId, ?string $reason = null): ApiResponseDto
    {
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->transformer->transform($this->client->post('invoices.cancel', $payload));
    }

    public function resend(int $invoiceId, ?string $reason = null): ApiResponseDto
    {
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['resend_reason'] = $reason;
        }

        return $this->transformer->transform($this->client->post('invoices.resend', $payload));
    }
}
