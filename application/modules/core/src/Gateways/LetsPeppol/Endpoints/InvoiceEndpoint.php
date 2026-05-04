<?php
namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class InvoiceEndpoint
{
    public function __construct(private GatewayClientInterface $gateway) {}
    public function sendInvoice(array $payload): ResponseInterface { return $this->gateway->request(RequestMethod::POST->value, 'invoices.send', ['headers'=>$this->gateway->buildHeaders(),'json'=>$payload]); }
    public function getStatus(int $invoiceId): ResponseInterface { return $this->gateway->request(RequestMethod::GET->value, 'invoices.status', ['headers'=>$this->gateway->buildHeaders(),'query'=>['invoice_id'=>$invoiceId]]); }
    public function cancel(int $invoiceId, ?string $reason = null): ResponseInterface { $payload=['invoice_id'=>$invoiceId]; if($reason!==null){$payload['cancel_reason']=$reason;} return $this->gateway->request(RequestMethod::POST->value, 'invoices.cancel', ['headers'=>$this->gateway->buildHeaders(),'json'=>$payload]); }
    public function resend(int $invoiceId, ?string $reason = null): ResponseInterface { $payload=['invoice_id'=>$invoiceId]; if($reason!==null){$payload['resend_reason']=$reason;} return $this->gateway->request(RequestMethod::POST->value, 'invoices.resend', ['headers'=>$this->gateway->buildHeaders(),'json'=>$payload]); }
}
