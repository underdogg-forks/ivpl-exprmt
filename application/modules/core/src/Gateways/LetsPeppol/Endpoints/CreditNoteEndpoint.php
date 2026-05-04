<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Credit Note endpoint client.
 *
 * Handles credit note submission and management in the Peppol network.
 */
class CreditNoteEndpoint
{
    public function __construct(private GatewayClientInterface $gateway)
    {
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
        return $this->gateway->request(RequestMethod::POST->value, 'credit_notes.send', [
            'headers' => $this->gateway->buildHeaders(),
            'json' => $payload,
        ]);
    }

    /**
     * Get credit note status by credit note ID.
     */
    public function getStatus(int $creditNoteId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'credit_notes.status', [
            'headers' => $this->gateway->buildHeaders(),
            'query' => ['credit_note_id' => $creditNoteId],
        ]);
    }

    /**
     * Cancel a credit note transmission (before delivery).
     */
    public function cancel(int $creditNoteId, ?string $reason = null): ResponseInterface
    {
        $payload = ['credit_note_id' => $creditNoteId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'credit_notes.cancel', [
            'headers' => $this->gateway->buildHeaders(),
            'json' => $payload,
        ]);
    }
}
