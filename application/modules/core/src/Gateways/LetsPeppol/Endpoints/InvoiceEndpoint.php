<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Invoice endpoint client.
 */
class InvoiceEndpoint
{
    public function __construct(private GatewayClientInterface $gateway)
    {
    }

    /**
     * Send invoice payload to LetsPeppol.
     *
     * @param  array<string, mixed> $payload
     * @return ResponseInterface
     */
    public function sendInvoice(array $payload): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::POST->value, 'invoices.send', ['headers' => $this->gateway->buildHeaders(), 'json' => $payload]);
    }

    /**
     * Get invoice status by invoice ID.
     */
    public function getStatus(int $invoiceId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'invoices.status', ['headers' => $this->gateway->buildHeaders(), 'query' => ['invoice_id' => $invoiceId]]);
    }

    /**
     * Cancel an invoice transmission (before delivery).
     */
    public function cancel(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'invoices.cancel', ['headers' => $this->gateway->buildHeaders(), 'json' => $payload]);
    }

    /**
     * Resend a previously failed invoice.
     */
    public function resend(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['resend_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'invoices.resend', ['headers' => $this->gateway->buildHeaders(), 'json' => $payload]);
    }
}
