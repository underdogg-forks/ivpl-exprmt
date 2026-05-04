<?php

namespace Core\Adapters\Sovos\Endpoints;

use Core\Adapters\Sovos\SovosClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class InvoiceClient
{
    public function __construct(private SovosClient $client)
    {
    }

    /**
     * Request JSON:
     * {"invoice_id":1}
     *
     * Response JSON:
     * {"status":"accepted","transmission_id":"trans-123"}
     */
    public function sendInvoice(array $payload): ResponseInterface
    {
        return $this->client->request(RequestMethod::POST->value, 'invoices.send', [
            'headers' => $this->client->buildAuthHeaders(),
            'json' => $payload,
        ]);
    }

    /**
     * Request query JSON:
     * {"invoice_id":1}
     *
     * Response JSON:
     * {"status":"delivered"}
     */
    public function getStatus(int $invoiceId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'invoices.status', [
            'headers' => $this->client->buildAuthHeaders(),
            'query' => ['invoice_id' => $invoiceId],
        ]);
    }

    /**
     * Request JSON:
     * {"invoice_id":1,"cancel_reason":"reason"}
     *
     * Response JSON:
     * {"status":"cancelled"}
     */
    public function cancel(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $payload = ['invoice_id' => $invoiceId];

        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->client->request(RequestMethod::POST->value, 'invoices.cancel', [
            'headers' => $this->client->buildAuthHeaders(),
            'json' => $payload,
        ]);
    }

    /**
     * Request JSON:
     * {"invoice_id":1,"resend_reason":"reason"}
     *
     * Response JSON:
     * {"status":"queued"}
     */
    public function resend(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $payload = ['invoice_id' => $invoiceId];

        if ($reason !== null) {
            $payload['resend_reason'] = $reason;
        }

        return $this->client->request(RequestMethod::POST->value, 'invoices.resend', [
            'headers' => $this->client->buildAuthHeaders(),
            'json' => $payload,
        ]);
    }
}
