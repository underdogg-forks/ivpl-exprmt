<?php

namespace Core\Adapters\Sovos\Endpoints;

use Core\Adapters\Sovos\SovosClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class InvoiceClient
{
    public function __construct(private SovosClient $client) {}

    /** Response headers JSON: {"Authorization":"Bearer <token>"} */
    private function buildAuthHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        $token = $this->client->settings('access_token');
        if ($token !== null) { $headers['Authorization'] = 'Bearer ' . $token; }
        return $headers;
    }

    /** Request JSON: {"invoice_id":1} */
    public function sendInvoice(array $payload): ResponseInterface
    { return $this->client->request(RequestMethod::POST->value, 'invoices.send', ['headers'=>$this->buildAuthHeaders(),'json'=>$payload]); }

    /** Request query JSON: {"invoice_id":1} */
    public function getStatus(int $invoiceId): ResponseInterface
    { return $this->client->request(RequestMethod::GET->value, 'invoices.status', ['headers'=>$this->buildAuthHeaders(),'query'=>['invoice_id'=>$invoiceId]]); }

    /** Request JSON: {"invoice_id":1,"cancel_reason":"reason"} */
    public function cancel(int $invoiceId, ?string $reason = null): ResponseInterface
    { $payload=['invoice_id'=>$invoiceId]; if($reason!==null){$payload['cancel_reason']=$reason;} return $this->client->request(RequestMethod::POST->value,'invoices.cancel',['headers'=>$this->buildAuthHeaders(),'json'=>$payload]); }

    /** Request JSON: {"invoice_id":1,"resend_reason":"reason"} */
    public function resend(int $invoiceId, ?string $reason = null): ResponseInterface
    { $payload=['invoice_id'=>$invoiceId]; if($reason!==null){$payload['resend_reason']=$reason;} return $this->client->request(RequestMethod::POST->value,'invoices.resend',['headers'=>$this->buildAuthHeaders(),'json'=>$payload]); }
}
