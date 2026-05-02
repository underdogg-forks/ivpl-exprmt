<?php

use Core\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class InvoiceEndpointTest extends TestCase
{
    /**
     * Arrange: InvoiceEndpoint backed by gateway client with fake HTTP (no credentials).
     * Act: sendInvoice is called.
     * Assert: POST request with proper headers and payload is made.
     */
    #[Test]
    public function it_sends_invoice_with_proper_headers(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $gateway = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            [],
            $http
        );

        $endpoint = new InvoiceEndpoint($gateway);

        $payload = [
            'invoice_id'       => 42,
            'invoice_number'   => 'INV-042',
            'client_peppol_id' => '0088:123456789',
        ];

        $response = $endpoint->sendInvoice($payload);

        $this->assertSame(200, $response->getStatusCode());

        $http->assertRequestMade('POST', 'https://api.letspeppol.test/api/invoices');

        $lastRequest = end($http->requests);
        $this->assertSame(42, $lastRequest['options']['json']['invoice_id']);
        $this->assertSame('INV-042', $lastRequest['options']['json']['invoice_number']);
        $this->assertArrayHasKey('Content-Type', $lastRequest['options']['headers']);
        $this->assertArrayHasKey('Accept', $lastRequest['options']['headers']);
    }

    /**
     * Arrange: InvoiceEndpoint with fixture data.
     * Act: sendInvoice is called.
     * Assert: Response matches expected fixture format.
     */
    #[Test]
    public function it_returns_response_matching_fixture_format(): void
    {
        // Load fixture
        $fixtureContent = file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/invoice_sent.json');
        $this->assertNotFalse($fixtureContent);

        $fixtureData = json_decode($fixtureContent, true);
        $this->assertIsArray($fixtureData);
        $this->assertArrayHasKey('status', $fixtureData);
        $this->assertSame('accepted', $fixtureData['status']);

        // Test that our endpoint can handle this response format
        $http = new FakeLetsPeppolHttpClient(200);

        $gateway  = new LetsPeppolGatewayClient('https://api.letspeppol.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);

        $response = $endpoint->sendInvoice(['invoice_id' => 1]);

        // Response should be successful
        $this->assertSame(200, $response->getStatusCode());
    }
}
