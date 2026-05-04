<?php
namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class DocumentEndpoint
{
    public function __construct(private GatewayClientInterface $gateway) {}
    public function get(string $documentId): ResponseInterface { return $this->gateway->request(RequestMethod::GET->value, 'documents.get', ['headers'=>$this->gateway->buildHeaders(),'query'=>['document_id'=>$documentId]]); }
    public function download(string $documentId): ResponseInterface { return $this->gateway->request(RequestMethod::GET->value, 'documents.download', ['headers'=>$this->gateway->buildHeaders(['accept'=>'application/xml']),'query'=>['document_id'=>$documentId]]); }
    public function getMetadata(string $documentId): ResponseInterface { return $this->gateway->request(RequestMethod::GET->value, 'documents.metadata', ['headers'=>$this->gateway->buildHeaders(),'query'=>['document_id'=>$documentId]]); }
    public function list(array $filters = []): ResponseInterface { return $this->gateway->request(RequestMethod::GET->value, 'documents.list', ['headers'=>$this->gateway->buildHeaders(),'query'=>$filters]); }
    public function archive(string $documentId, ?string $reason = null): ResponseInterface { $payload=['document_id'=>$documentId]; if($reason!==null){$payload['archive_reason']=$reason;} return $this->gateway->request(RequestMethod::POST->value, 'documents.archive', ['headers'=>$this->gateway->buildHeaders(),'json'=>$payload]); }
}
