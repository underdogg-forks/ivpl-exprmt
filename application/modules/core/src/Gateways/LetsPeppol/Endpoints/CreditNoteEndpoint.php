<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Credit Note endpoint client.
 *
 * Handles credit note submission and management in the Peppol network.
 * Credit notes follow similar patterns to invoices but with specific UBL schema.
 * Uses the gateway client's request() method and buildHeaders() for consistency.
 */
class CreditNoteEndpoint
{
    public function __construct(
        private GatewayClientInterface $gateway
    ) {
    }

    /**
     * Send credit note payload to LetsPeppol.
     *
     * Request JSON example:
     * {
     *   "credit_note_id": 5,
     *   "credit_note_number": "CN-2026-001",
     *   "invoice_id": 1,
     *   "invoice_number": "INV-2026-001",
     *   "client_peppol_id": "0088:987654321",
     *   "amount": 250.00,
     *   "currency": "EUR",
     *   "reason": "Partial refund for damaged goods",
     *   "issue_date": "2026-05-02"
     * }
     *
     * Response JSON example (from fixture):
     * {
     *   "status": "accepted",
     *   "id": "ext-creditnote-45",
     *   "credit_note_id": 5,
     *   "transmission_id": "trans-789",
     *   "created_at": "2026-05-02T02:00:00Z"
     * }
     *
     * @param  array<string, mixed> $payload  Credit note data
     * @return ResponseInterface
     */
    public function send(array $payload): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::POST->value, 'credit_notes.send', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }

    /**
     * Get credit note status by credit note ID.
     *
     * Request example:
     * GET /api/credit-notes/{credit_note_id}
     *
     * Response JSON example (from fixture):
     * {
     *   "credit_note_id": 5,
     *   "credit_note_number": "CN-2026-001",
     *   "status": "delivered",
     *   "transmission_id": "trans-789",
     *   "sent_at": "2026-05-02T02:00:00Z",
     *   "delivered_at": "2026-05-02T02:05:00Z"
     * }
     *
     * @param  int $creditNoteId  Credit note ID from InvoicePlane
     * @return ResponseInterface
     */
    public function getStatus(int $creditNoteId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'credit_notes.status', [
            'headers' => $headers,
            'query'   => ['credit_note_id' => $creditNoteId],
        ]);
    }

    /**
     * Cancel a credit note transmission (before delivery).
     *
     * Request JSON example:
     * {
     *   "credit_note_id": 5,
     *   "cancel_reason": "Issued in error - amount incorrect"
     * }
     *
     * Response JSON example (from fixture):
     * {
     *   "credit_note_id": 5,
     *   "status": "cancelled",
     *   "transmission_id": "trans-789",
     *   "cancelled_at": "2026-05-02T02:10:00Z"
     * }
     *
     * @param  int $creditNoteId     Credit note ID to cancel
     * @param  string|null $reason   Optional cancellation reason
     * @return ResponseInterface
     */
    public function cancel(int $creditNoteId, ?string $reason = null): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();
        
        $payload = ['credit_note_id' => $creditNoteId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'credit_notes.cancel', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }
}
