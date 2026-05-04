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
        /* Arrange */
        $gateway = $this->fakeGateway();
        $endpoint = new ParticipantEndpoint($gateway);

        /* Act */
        $valid = $endpoint->validatePeppolId('0088:123');
        $details = $endpoint->getDetails('0088:123');
        $search = $endpoint->search('acme', 'SE');
        $capabilities = $endpoint->getCapabilities('0088:123');

        /* Assert */
        $this->assertTrue($valid);
        $this->assertSame(200, $details->getStatusCode());
        $this->assertSame(200, $search->getStatusCode());
        $this->assertSame(200, $capabilities->getStatusCode());
    }

    #[Test]
    public function it_calls_invoice_endpoints(): void
    {
        /* Arrange */
        $gateway = $this->fakeGateway();
        $endpoint = new InvoiceEndpoint($gateway);

        /* Act */
        $sent = $endpoint->sendInvoice(['invoice_id' => 1]);
        $status = $endpoint->getStatus(1);
        $cancelled = $endpoint->cancel(1, 'bad');
        $resent = $endpoint->resend(1, 'retry');

        /* Assert */
        $this->assertSame(200, $sent->getStatusCode());
        $this->assertSame(200, $status->getStatusCode());
        $this->assertSame(200, $cancelled->getStatusCode());
        $this->assertSame(200, $resent->getStatusCode());
    }

    #[Test]
    public function it_calls_credit_note_transmission_and_document_endpoints(): void
    {
        /* Arrange */
        $gateway = $this->fakeGateway();
        $credit = new CreditNoteEndpoint($gateway);
        $trans = new TransmissionEndpoint($gateway);
        $doc = new DocumentEndpoint($gateway);

        /* Act */
        $creditSent = $credit->send(['id' => 1]);
        $creditStatus = $credit->getStatus(1);
        $creditCancel = $credit->cancel(1, 'x');
        $transStatus = $trans->getStatus('t1');
        $transReceipt = $trans->getReceipt('t1');
        $transErrors = $trans->getErrors('t1');
        $transList = $trans->list(['status' => 'delivered']);
        $transRetry = $trans->retry('t1', 'again');
        $docGet = $doc->get('d1');
        $docDownload = $doc->download('d1');
        $docMetadata = $doc->getMetadata('d1');
        $docList = $doc->list(['status' => 'ok']);
        $docArchive = $doc->archive('d1', 'done');

        /* Assert */
        $this->assertSame(200, $creditSent->getStatusCode());
        $this->assertSame(200, $creditStatus->getStatusCode());
        $this->assertSame(200, $creditCancel->getStatusCode());
        $this->assertSame(200, $transStatus->getStatusCode());
        $this->assertSame(200, $transReceipt->getStatusCode());
        $this->assertSame(200, $transErrors->getStatusCode());
        $this->assertSame(200, $transList->getStatusCode());
        $this->assertSame(200, $transRetry->getStatusCode());
        $this->assertSame(200, $docGet->getStatusCode());
        $this->assertSame(200, $docDownload->getStatusCode());
        $this->assertSame(200, $docMetadata->getStatusCode());
        $this->assertSame(200, $docList->getStatusCode());
        $this->assertSame(200, $docArchive->getStatusCode());
    }

    private function fakeGateway(): GatewayClientInterface
    {
        return new class implements GatewayClientInterface {
            public function request(string $method, string $uri, array $options = []): Response
            {
                return new Response(200, ['Content-Type' => 'application/json'], '{}');
            }

            public function buildHeaders(array $options = []): array
            {
                return ['Content-Type' => 'application/json', 'Accept' => $options['accept'] ?? 'application/json'];
            }

            public function authorize(): void
            {
            }

            public function getSettings(?string $key = null, mixed $default = null): mixed
            {
                return $default;
            }
        };
    }
}
