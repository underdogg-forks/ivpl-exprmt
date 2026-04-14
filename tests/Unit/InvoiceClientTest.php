<?php

use App\Adapters\LetsPeppol\Endpoints\InvoiceClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\TestCase;

class InvoiceClientTest extends TestCase
{
    public function testSendInvoiceCallsUnderlyingClient()
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
                        && $options['json']['invoice_id'] === 99;
                })
            );

        $client = new InvoiceClient($baseClient);
        $client->sendInvoice('token-123', ['invoice_id' => 99]);
    }
}
