<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Invoice endpoint client.
 *
 * Handles invoice submission to the LetsPeppol gateway.
 * Uses the gateway client's request() method and buildHeaders() for consistency.
 */
class InvoiceEndpoint
{
    public function __construct(
        private GatewayClientInterface $gateway
    ) {
    }

    /**
     * Send invoice payload to LetsPeppol.
     *
     * Request JSON example:
     * {"invoice_id":1,"invoice_number":"INV-1","client_peppol_id":"0088:123"}
     *
     * Response JSON example (from fixture):
     * {"status":"accepted","id":"ext-123","transmission_id":"trans-456"}
     *
     * @param  array<string, mixed> $payload  Invoice data
     * @return ResponseInterface
     */
    public function sendInvoice(array $payload): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::POST->value, 'invoices.send', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }

    /**
     * Get invoice status by invoice ID.
     *
     * Request example:
     * GET /api/invoices/{invoice_id}
     *
     * Response JSON example (from fixture):
     * {
     *   "invoice_id": 1,
     *   "invoice_number": "INV-2026-001",
     *   "status": "delivered",
     *   "transmission_id": "trans-456",
     *   "sent_at": "2026-05-02T01:00:00Z",
     *   "delivered_at": "2026-05-02T01:05:30Z",
     *   "document_id": "ext-invoice-123"
     * }
     *
     * @param  int $invoiceId  Invoice ID from InvoicePlane
     * @return ResponseInterface
     */
    public function getStatus(int $invoiceId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'invoices.status', [
            'headers' => $headers,
            'query'   => ['invoice_id' => $invoiceId],
        ]);
    }

    /**
     * Cancel an invoice transmission (before delivery).
     *
     * Request JSON example:
     * {
     *   "invoice_id": 1,
     *   "cancel_reason": "Incorrect amount - will resend corrected version"
     * }
     *
     * Response JSON example (from fixture):
     * {
     *   "invoice_id": 1,
     *   "status": "cancelled",
     *   "transmission_id": "trans-456",
     *   "cancelled_at": "2026-05-02T01:10:00Z"
     * }
     *
     * @param  int $invoiceId        Invoice ID to cancel
     * @param  string|null $reason   Optional cancellation reason
     * @return ResponseInterface
     */
    public function cancel(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();
        
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'invoices.cancel', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }

    /**
     * Resend a previously failed invoice.
     *
     * Request JSON example:
     * {
     *   "invoice_id": 1,
     *   "resend_reason": "Recipient endpoint is now available"
     * }
     *
     * Response JSON example (from fixture):
     * {
     *   "invoice_id": 1,
     *   "original_transmission_id": "trans-456",
     *   "new_transmission_id": "trans-457",
     *   "status": "queued",
     *   "resent_at": "2026-05-02T02:00:00Z"
     * }
     *
     * @param  int $invoiceId        Invoice ID to resend
     * @param  string|null $reason   Optional resend reason
     * @return ResponseInterface
     */
    public function resend(int $invoiceId, ?string $reason = null): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();
        
        $payload = ['invoice_id' => $invoiceId];
        if ($reason !== null) {
            $payload['resend_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'invoices.resend', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }
}
