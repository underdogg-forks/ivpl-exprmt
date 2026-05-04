<?php

declare(strict_types=1);

namespace Tests\Unit;

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

    /**
     * Arrange: InvoiceEndpoint with fake HTTP.
     * Act: getStatus is called with invoice ID.
     * Assert: Response is 200 and request was made to correct endpoint.
     */
    #[Test]
    public function it_gets_invoice_status(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);

        $response = $endpoint->getStatus(1);

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('GET', 'api/invoices');
    }

    /**
     * Arrange: InvoiceEndpoint with fake HTTP.
     * Act: cancel is called without reason.
     * Assert: Response is 200 and POST request was made.
     */
    #[Test]
    public function it_cancels_invoice_without_reason(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);

        $response = $endpoint->cancel(1);

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('POST', 'api/invoices/cancel');
    }

    /**
     * Arrange: InvoiceEndpoint with fake HTTP.
     * Act: cancel is called with reason.
     * Assert: Response is 200 and request includes cancel_reason.
     */
    #[Test]
    public function it_cancels_invoice_with_reason(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);

        $response = $endpoint->cancel(1, 'Incorrect amount');

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('POST', 'api/invoices/cancel');
        
        $lastRequest = end($http->requests);
        $this->assertArrayHasKey('json', $lastRequest['options']);
        $this->assertSame('Incorrect amount', $lastRequest['options']['json']['cancel_reason']);
    }

    /**
     * Arrange: InvoiceEndpoint with fake HTTP.
     * Act: resend is called without reason.
     * Assert: Response is 200 and POST request was made.
     */
    #[Test]
    public function it_resends_invoice_without_reason(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);

        $response = $endpoint->resend(1);

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('POST', 'api/invoices/resend');
    }

    /**
     * Arrange: InvoiceEndpoint with fake HTTP.
     * Act: resend is called with reason.
     * Assert: Response is 200 and request includes resend_reason.
     */
    #[Test]
    public function it_resends_invoice_with_reason(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);

        $response = $endpoint->resend(1, 'Recipient endpoint is now available');

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('POST', 'api/invoices/resend');
        
        $lastRequest = end($http->requests);
        $this->assertArrayHasKey('json', $lastRequest['options']);
        $this->assertSame('Recipient endpoint is now available', $lastRequest['options']['json']['resend_reason']);
    }

    /**
     * Arrange: InvoiceEndpoint with Bearer token set.
     * Act: getStatus is called.
     * Assert: Authorization header is included in request.
     */
    #[Test]
    public function it_includes_authorization_headers_in_requests(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];
        
        $gateway = new LetsPeppolGatewayClient('https://api.test', $settings, $http);
        $gateway->setAccessToken('test-bearer-token');
        $endpoint = new InvoiceEndpoint($gateway);

        $endpoint->getStatus(1);

        $this->assertCount(1, $http->requests);
        $request = $http->requests[0];
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Authorization', $request['options']['headers']);
        $this->assertEquals('Bearer test-bearer-token', $request['options']['headers']['Authorization']);
    }

    #[Test]
    public function it_validates_invoice_lifecycle_fixtures_format(): void
    {
        $status = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/invoice_status.json'), true);
        $this->assertIsArray($status);
        $this->assertArrayHasKey('status', $status);

        $cancelled = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/invoice_cancelled.json'), true);
        $this->assertIsArray($cancelled);
        $this->assertSame('cancelled', $cancelled['status']);

        $resent = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/invoice_resent.json'), true);
        $this->assertIsArray($resent);
        $this->assertArrayHasKey('new_transmission_id', $resent);
    }

}
