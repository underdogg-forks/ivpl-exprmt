<?php

namespace Core\Adapters\StoreCove\Endpoints;

use Core\Adapters\StoreCove\StoreCoveClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class InvoiceClient
{
    public function __construct(private StoreCoveClient $client)
    {
    }

    private function buildAuthHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        $token = $this->client->settings('access_token');
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    public function sendInvoice(array $payload): ResponseInterface
    {
        return $this->client->request(RequestMethod::POST->value, 'invoices.send', ['headers' => $this->buildAuthHeaders(), 'json' => $payload]);
    }

    public function getStatus(int $invoiceId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'invoices.status', ['headers' => $this->buildAuthHeaders(), 'query' => ['invoice_id' => $invoiceId]]);
    }

    public function cancel(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }
        return $this->client->request(RequestMethod::POST->value, 'invoices.cancel', ['headers' => $this->buildAuthHeaders(), 'json' => $payload]);
    }

    public function resend(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['resend_reason'] = $reason;
        }
        return $this->client->request(RequestMethod::POST->value, 'invoices.resend', ['headers' => $this->buildAuthHeaders(), 'json' => $payload]);
    }
}
