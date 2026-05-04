<?php

namespace Core\Adapters\Pagero\Endpoints;

use Core\Adapters\Pagero\PageroClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class DocumentClient
{
    public function __construct(private PageroClient $client)
    {
    }

    public function get(string $documentId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'documents.get', ['query' => ['document_id' => $documentId]]);
    }

    public function download(string $documentId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'documents.download', ['query' => ['document_id' => $documentId]]);
    }

    public function getMetadata(string $documentId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'documents.metadata', ['query' => ['document_id' => $documentId]]);
    }

    public function list(array $filters = []): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'documents.list', ['query' => $filters]);
    }

    public function archive(string $documentId, ?string $reason = null): ResponseInterface
    {
        $payload = ['document_id' => $documentId];
        if ($reason !== null) {
            $payload['archive_reason'] = $reason;
        }
        return $this->client->request(RequestMethod::POST->value, 'documents.archive', ['json' => $payload]);
    }
}
