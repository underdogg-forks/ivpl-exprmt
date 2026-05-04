<?php

namespace Tests\Unit\Gateways\LetsPeppol;

use Core\Contracts\GatewayClientInterface;
use Core\Gateways\LetsPeppol\Endpoints\CreditNoteEndpoint;
use Core\Gateways\LetsPeppol\Endpoints\DocumentEndpoint;
use Core\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use Core\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use Core\Gateways\LetsPeppol\Endpoints\TransmissionEndpoint;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EndpointClientsTest extends TestCase
{
    #[Test]
    public function it_calls_participant_endpoints(): void
    {
        $gateway = $this->fakeGateway();
        $endpoint = new ParticipantEndpoint($gateway);
        $this->assertTrue($endpoint->validatePeppolId('0088:123'));
        $this->assertSame(200, $endpoint->getDetails('0088:123')->getStatusCode());
        $this->assertSame(200, $endpoint->search('acme', 'SE')->getStatusCode());
        $this->assertSame(200, $endpoint->getCapabilities('0088:123')->getStatusCode());
    }

    #[Test]
    public function it_calls_invoice_endpoints(): void
    {
        $gateway = $this->fakeGateway();
        $endpoint = new InvoiceEndpoint($gateway);
        $this->assertSame(200, $endpoint->sendInvoice(['invoice_id' => 1])->getStatusCode());
        $this->assertSame(200, $endpoint->getStatus(1)->getStatusCode());
        $this->assertSame(200, $endpoint->cancel(1, 'bad')->getStatusCode());
        $this->assertSame(200, $endpoint->resend(1, 'retry')->getStatusCode());
    }

    #[Test]
    public function it_calls_credit_note_transmission_and_document_endpoints(): void
    {
        $gateway = $this->fakeGateway();
        $credit = new CreditNoteEndpoint($gateway);
        $trans = new TransmissionEndpoint($gateway);
        $doc = new DocumentEndpoint($gateway);

        $this->assertSame(200, $credit->send(['id' => 1])->getStatusCode());
        $this->assertSame(200, $credit->getStatus(1)->getStatusCode());
        $this->assertSame(200, $credit->cancel(1, 'x')->getStatusCode());
        $this->assertSame(200, $trans->getStatus('t1')->getStatusCode());
        $this->assertSame(200, $trans->getReceipt('t1')->getStatusCode());
        $this->assertSame(200, $trans->getErrors('t1')->getStatusCode());
        $this->assertSame(200, $trans->list(['status' => 'delivered'])->getStatusCode());
        $this->assertSame(200, $trans->retry('t1', 'again')->getStatusCode());
        $this->assertSame(200, $doc->get('d1')->getStatusCode());
        $this->assertSame(200, $doc->download('d1')->getStatusCode());
        $this->assertSame(200, $doc->getMetadata('d1')->getStatusCode());
        $this->assertSame(200, $doc->list(['status' => 'ok'])->getStatusCode());
        $this->assertSame(200, $doc->archive('d1', 'done')->getStatusCode());
    }

    private function fakeGateway(): GatewayClientInterface
    {
        return new class implements GatewayClientInterface {
            public function request(string $method, string $uri, array $options = []): Response { return new Response(200, ['Content-Type' => 'application/json'], '{}'); }
            public function buildHeaders(array $options = []): array { return ['Content-Type' => 'application/json', 'Accept' => $options['accept'] ?? 'application/json']; }
            public function authorize(): void {}
            public function getSettings(?string $key = null, mixed $default = null): mixed { return $default; }
        };
    }
}
