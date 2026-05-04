<?php
namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class CreditNoteEndpoint
{
    public function __construct(private GatewayClientInterface $gateway) {}
    public function send(array $payload): ResponseInterface { return $this->gateway->request(RequestMethod::POST->value, 'credit_notes.send', ['headers'=>$this->gateway->buildHeaders(),'json'=>$payload]); }
    public function getStatus(int $creditNoteId): ResponseInterface { return $this->gateway->request(RequestMethod::GET->value, 'credit_notes.status', ['headers'=>$this->gateway->buildHeaders(),'query'=>['credit_note_id'=>$creditNoteId]]); }
    public function cancel(int $creditNoteId, ?string $reason = null): ResponseInterface { $payload=['credit_note_id'=>$creditNoteId]; if($reason!==null){$payload['cancel_reason']=$reason;} return $this->gateway->request(RequestMethod::POST->value, 'credit_notes.cancel', ['headers'=>$this->gateway->buildHeaders(),'json'=>$payload]); }
}
