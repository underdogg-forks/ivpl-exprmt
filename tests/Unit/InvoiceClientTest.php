<?php

use App\Adapters\LetsPeppol\Endpoints\InvoiceClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvoiceClientTest extends TestCase
{
    /**
     * Arrange: invoice endpoint client and mock transport client.
     * Act: sendInvoice is called.
     * Assert: POST request with auth header and payload is sent.
     */
    #[Test]
    public function it_sends_invoice_payload_with_bearer_token()
    {
        $baseClient = $this->createMock(LetsPeppolClient::class);
        $baseClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'invoices.send',
                $this->callback(function ($options) {
                    return isset($options['headers']['Authorization'])
                        && $options['headers']['Authorization'] === 'Bearer token-123'
                        && isset($options['json']['invoice_id'])
                        && $options['json']['invoice_id'] === 99;
                })
            );

        $client = new InvoiceClient($baseClient);

        $client->sendInvoice('token-123', ['invoice_id' => 99]);
    }
}
