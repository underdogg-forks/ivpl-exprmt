<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Transmission endpoint client.
 *
 * Handles transmission status tracking, receipt verification, and error handling
 * for documents sent through the Peppol network.
 * Uses the gateway client's request() method and buildHeaders() for consistency.
 */
class TransmissionEndpoint
{
    public function __construct(
        private GatewayClientInterface $gateway
    ) {
    }

    /**
     * Get transmission status by transmission ID.
     *
     * Request example:
     * GET /api/transmissions/{transmission_id}
     *
     * Response JSON example (from fixture):
     * {
     *   "transmission_id": "trans-456",
     *   "status": "delivered",
     *   "document_id": "ext-invoice-123",
     *   "sent_at": "2026-05-02T01:00:00Z",
     *   "delivered_at": "2026-05-02T01:05:30Z",
     *   "recipient": "0088:987654321"
     * }
     *
     * @param  string $transmissionId  Unique transmission identifier
     * @return ResponseInterface
     */
    public function getStatus(string $transmissionId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.status', [
            'headers' => $headers,
            'query'   => ['transmission_id' => $transmissionId],
        ]);
    }

    /**
     * Get receipt acknowledgment for a transmission.
     *
     * Request example:
     * GET /api/transmissions/{transmission_id}/receipt
     *
     * Response JSON example (from fixture):
     * {
     *   "transmission_id": "trans-456",
     *   "receipt_type": "application_response",
     *   "receipt_status": "accepted",
     *   "received_at": "2026-05-02T01:05:30Z",
     *   "receipt_content": {
     *     "status_code": "AP",
     *     "status_reason": "Document accepted by recipient"
     *   }
     * }
     *
     * @param  string $transmissionId  Unique transmission identifier
     * @return ResponseInterface
     */
    public function getReceipt(string $transmissionId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.receipt', [
            'headers' => $headers,
            'query'   => ['transmission_id' => $transmissionId],
        ]);
    }

    /**
     * Get error details for a failed transmission.
     *
     * Request example:
     * GET /api/transmissions/{transmission_id}/errors
     *
     * Response JSON example (from fixture):
     * {
     *   "transmission_id": "trans-789",
     *   "error_code": "INVALID_RECIPIENT",
     *   "error_message": "Recipient Peppol ID not found in SML",
     *   "occurred_at": "2026-05-02T02:15:00Z",
     *   "details": {
     *     "recipient_id": "0088:invalid",
     *     "sml_query_result": "not_found"
     *   }
     * }
     *
     * @param  string $transmissionId  Unique transmission identifier
     * @return ResponseInterface
     */
    public function getErrors(string $transmissionId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.errors', [
            'headers' => $headers,
            'query'   => ['transmission_id' => $transmissionId],
        ]);
    }

    /**
     * List all transmissions with optional filtering.
     *
     * Request example:
     * GET /api/transmissions?status=delivered&from=2026-05-01&to=2026-05-31
     *
     * Response JSON example (from fixture):
     * {
     *   "transmissions": [
     *     {
     *       "transmission_id": "trans-456",
     *       "status": "delivered",
     *       "document_type": "invoice",
     *       "sent_at": "2026-05-02T01:00:00Z"
     *     }
     *   ],
     *   "total": 1,
     *   "page": 1,
     *   "per_page": 50
     * }
     *
     * @param  array<string, mixed> $filters  Optional filters (status, from, to, document_type)
     * @return ResponseInterface
     */
    public function list(array $filters = []): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.list', [
            'headers' => $headers,
            'query'   => $filters,
        ]);
    }

    /**
     * Retry a failed transmission.
     *
     * Request JSON example:
     * {
     *   "transmission_id": "trans-789",
     *   "retry_reason": "Recipient endpoint was temporarily unavailable"
     * }
     *
     * Response JSON example (from fixture):
     * {
     *   "original_transmission_id": "trans-789",
     *   "new_transmission_id": "trans-790",
     *   "status": "queued",
     *   "retried_at": "2026-05-02T03:00:00Z"
     * }
     *
     * @param  string $transmissionId  Original transmission identifier
     * @param  string|null $reason     Optional retry reason
     * @return ResponseInterface
     */
    public function retry(string $transmissionId, ?string $reason = null): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();
        
        $payload = ['transmission_id' => $transmissionId];
        if ($reason !== null) {
            $payload['retry_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'transmissions.retry', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }
}
