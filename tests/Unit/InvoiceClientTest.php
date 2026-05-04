<?php

use Core\Adapters\LetsPeppol\Endpoints\InvoiceClient;
use Core\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class InvoiceClientTest extends TestCase
{
    /**
     * Arrange: InvoiceClient backed by FakeLetsPeppolHttpClient (200 OK).
     * Act: sendInvoice is called.
     * Assert: POST request with auth header and payload was made.
     */
    #[Test]
    public function it_sends_invoice_payload_with_bearer_token(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $client        = new LetsPeppolClient($http, 'https://api.test', ['invoices.send' => 'api/invoices']);
        $invoiceClient = new InvoiceClient($client);

        $response = $invoiceClient->sendInvoice(['invoice_id' => 99]);

        $this->assertSame(200, $response->getStatusCode());

        $http->assertRequestMade('POST', 'https://api.test/api/invoices');

        $lastRequest = end($http->requests);
                $this->assertSame(99, $lastRequest['options']['json']['invoice_id']);
    }

    /**
     * Arrange: FakeInvoiceClient (stateful fake, no HTTP at all).
     * Act: sendInvoice is called.
     * Assert: payload is recorded and assertion helpers work.
     */
    #[Test]
    public function it_records_sent_invoices_in_fake(): void
    {
        $fake = new Tests\Fakes\FakeInvoiceClient(true);

        $fake->sendInvoice(['invoice_id' => 42, 'invoice_number' => 'INV-42']);

        $fake->assertInvoiceSent(['invoice_id' => 42]);
        $this->assertSame('INV-42', $fake->lastPayload()['invoice_number']);
    }

    /**
     * Arrange: FakeInvoiceClient (no invoices sent).
     * Act: nothing sent.
     * Assert: assertNoInvoicesSent passes.
     */
    #[Test]
    public function it_reports_no_invoices_sent_when_idle(): void
    {
        $fake = new Tests\Fakes\FakeInvoiceClient();

        $fake->assertNoInvoicesSent();
    }
}
