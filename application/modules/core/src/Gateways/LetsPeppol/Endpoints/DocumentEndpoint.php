<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Document endpoint client.
 *
 * Handles document retrieval, metadata queries, and document lifecycle management
 * for documents in the Peppol network.
 * Uses the gateway client's request() method and buildHeaders() for consistency.
 */
class DocumentEndpoint
{
    public function __construct(
        private GatewayClientInterface $gateway
    ) {
    }

    /**
     * Retrieve a document by its ID.
     *
     * Request example:
     * GET /api/documents/{document_id}
     *
     * Response JSON example (from fixture):
     * {
     *   "document_id": "ext-invoice-123",
     *   "document_type": "invoice",
     *   "document_number": "INV-2026-001",
     *   "sender": "0088:123456789",
     *   "recipient": "0088:987654321",
     *   "created_at": "2026-05-02T01:00:00Z",
     *   "content_type": "application/xml",
     *   "content_url": "https://api.letspeppol.com/documents/ext-invoice-123/download"
     * }
     *
     * @param  string $documentId  Unique document identifier
     * @return ResponseInterface
     */
    public function get(string $documentId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'documents.get', [
            'headers' => $headers,
            'query'   => ['document_id' => $documentId],
        ]);
    }

    /**
     * Download document content (UBL XML).
     *
     * Request example:
     * GET /api/documents/{document_id}/download
     *
     * Response: Raw XML content with application/xml content-type
     *
     * @param  string $documentId  Unique document identifier
     * @return ResponseInterface
     */
    public function download(string $documentId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders([
            'accept' => 'application/xml',
        ]);

        return $this->gateway->request(RequestMethod::GET->value, 'documents.download', [
            'headers' => $headers,
            'query'   => ['document_id' => $documentId],
        ]);
    }

    /**
     * Get document metadata without downloading full content.
     *
     * Request example:
     * GET /api/documents/{document_id}/metadata
     *
     * Response JSON example (from fixture):
     * {
     *   "document_id": "ext-invoice-123",
     *   "metadata": {
     *     "document_type_id": "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2",
     *     "process_id": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
     *     "file_size_bytes": 15420,
     *     "hash_algorithm": "SHA-256",
     *     "hash_value": "a1b2c3d4e5f6..."
     *   }
     * }
     *
     * @param  string $documentId  Unique document identifier
     * @return ResponseInterface
     */
    public function getMetadata(string $documentId): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'documents.metadata', [
            'headers' => $headers,
            'query'   => ['document_id' => $documentId],
        ]);
    }

    /**
     * List documents with optional filtering.
     *
     * Request example:
     * GET /api/documents?document_type=invoice&from=2026-05-01&to=2026-05-31&status=delivered
     *
     * Response JSON example (from fixture):
     * {
     *   "documents": [
     *     {
     *       "document_id": "ext-invoice-123",
     *       "document_type": "invoice",
     *       "document_number": "INV-2026-001",
     *       "created_at": "2026-05-02T01:00:00Z",
     *       "status": "delivered"
     *     }
     *   ],
     *   "total": 1,
     *   "page": 1,
     *   "per_page": 50
     * }
     *
     * @param  array<string, mixed> $filters  Optional filters (document_type, from, to, status, sender, recipient)
     * @return ResponseInterface
     */
    public function list(array $filters = []): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::GET->value, 'documents.list', [
            'headers' => $headers,
            'query'   => $filters,
        ]);
    }

    /**
     * Archive a document (mark as archived but don't delete).
     *
     * Request JSON example:
     * {
     *   "document_id": "ext-invoice-123",
     *   "archive_reason": "Invoice paid and reconciled"
     * }
     *
     * Response JSON example (from fixture):
     * {
     *   "document_id": "ext-invoice-123",
     *   "status": "archived",
     *   "archived_at": "2026-06-01T12:00:00Z"
     * }
     *
     * @param  string $documentId    Document identifier to archive
     * @param  string|null $reason   Optional archival reason
     * @return ResponseInterface
     */
    public function archive(string $documentId, ?string $reason = null): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();
        
        $payload = ['document_id' => $documentId];
        if ($reason !== null) {
            $payload['archive_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'documents.archive', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }
}
