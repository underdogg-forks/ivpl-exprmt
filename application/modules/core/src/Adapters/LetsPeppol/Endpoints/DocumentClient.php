<?php

namespace Core\Adapters\LetsPeppol\Endpoints;

use Core\Adapters\LetsPeppol\LetsPeppolClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class DocumentClient
{
    public function __construct(private LetsPeppolClient $client) {}
    /** Request query JSON: {"document_id":"doc-1"}. Response JSON: {"document_id":"doc-1"} */
    public function get(string $documentId): ResponseInterface { return $this->client->request(RequestMethod::GET->value, 'documents.get', ['query'=>['document_id'=>$documentId]]); }
    /** Request query JSON: {"document_id":"doc-1"}. Response: UBL XML content */
    public function download(string $documentId): ResponseInterface { return $this->client->request(RequestMethod::GET->value, 'documents.download', ['query'=>['document_id'=>$documentId]]); }
    /** Request query JSON: {"document_id":"doc-1"}. Response JSON: {"metadata":{}} */
    public function getMetadata(string $documentId): ResponseInterface { return $this->client->request(RequestMethod::GET->value, 'documents.metadata', ['query'=>['document_id'=>$documentId]]); }
    /** Request query JSON: {"status":"delivered"}. Response JSON: {"documents":[]} */
    public function list(array $filters = []): ResponseInterface { return $this->client->request(RequestMethod::GET->value, 'documents.list', ['query'=>$filters]); }
    /** Request JSON: {"document_id":"doc-1","archive_reason":"reason"}. Response JSON: {"status":"archived"} */
    public function archive(string $documentId, ?string $reason = null): ResponseInterface { $p=['document_id'=>$documentId]; if($reason!==null){$p['archive_reason']=$reason;} return $this->client->request(RequestMethod::POST->value, 'documents.archive', ['json'=>$p]); }
}
