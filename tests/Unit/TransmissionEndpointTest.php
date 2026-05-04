<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Gateways\LetsPeppol\Endpoints\TransmissionEndpoint;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class TransmissionEndpointTest extends TestCase
{
    private FakeLetsPeppolHttpClient $http;
    private LetsPeppolGatewayClient $gateway;
    private TransmissionEndpoint $endpoint;

    protected function setUp(): void
    {
        $this->http = new FakeLetsPeppolHttpClient(200);
        $this->gateway = new LetsPeppolGatewayClient('https://api.test', [], $this->http);
        $this->endpoint = new TransmissionEndpoint($this->gateway);
    }

    #[Test]
    public function it_gets_transmission_status(): void
    {
        /* Arrange */
        $transmissionId = 'trans-456';

        /* Act */
        $response = $this->endpoint->getStatus($transmissionId);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/transmissions');
    }

    #[Test]
    public function it_gets_transmission_receipt(): void
    {
        /* Arrange */
        $transmissionId = 'trans-456';

        /* Act */
        $response = $this->endpoint->getReceipt($transmissionId);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/transmissions/receipt');
    }

    #[Test]
    public function it_gets_transmission_errors(): void
    {
        /* Arrange */
        $transmissionId = 'trans-789';

        /* Act */
        $response = $this->endpoint->getErrors($transmissionId);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/transmissions/errors');
    }

    #[Test]
    public function it_lists_transmissions_without_filters(): void
    {
        /* Arrange - no filters */

        /* Act */
        $response = $this->endpoint->list();

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/transmissions');
    }

    #[Test]
    public function it_lists_transmissions_with_filters(): void
    {
        /* Arrange */
        $filters = [
            'status' => 'delivered',
            'from'   => '2026-05-01',
            'to'     => '2026-05-31',
        ];

        /* Act */
        $response = $this->endpoint->list($filters);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/transmissions');
    }

    #[Test]
    public function it_retries_failed_transmission_without_reason(): void
    {
        /* Arrange */
        $transmissionId = 'trans-789';

        /* Act */
        $response = $this->endpoint->retry($transmissionId);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/transmissions/retry');
    }

    #[Test]
    public function it_retries_failed_transmission_with_reason(): void
    {
        /* Arrange */
        $transmissionId = 'trans-789';
        $reason = 'Recipient endpoint was temporarily unavailable';

        /* Act */
        $response = $this->endpoint->retry($transmissionId, $reason);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/transmissions/retry');
    }

    #[Test]
    public function it_includes_authorization_headers_in_requests(): void
    {
        /* Arrange */
        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];
        
        // Don't auto-authorize by providing http without OAuth mock
        $this->gateway = new LetsPeppolGatewayClient('https://api.test', $settings, $this->http);
        $this->gateway->setAccessToken('test-bearer-token');
        $this->endpoint = new TransmissionEndpoint($this->gateway);

        /* Act */
        $this->endpoint->getStatus('trans-456');

        /* Assert */
        $this->assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Authorization', $request['options']['headers']);
        $this->assertEquals('Bearer test-bearer-token', $request['options']['headers']['Authorization']);
    }

    #[Test]
    public function it_validates_transmission_fixtures_format(): void
    {
        $delivered = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/transmission_status_delivered.json'), true);
        $this->assertIsArray($delivered);
        $this->assertSame('delivered', $delivered['status']);

        $failed = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/transmission_status_failed.json'), true);
        $this->assertIsArray($failed);
        $this->assertSame('failed', $failed['status']);

        $receipt = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/transmission_receipt.json'), true);
        $this->assertIsArray($receipt);
        $this->assertArrayHasKey('receipt_status', $receipt);

        $errors = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/transmission_errors.json'), true);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('error_code', $errors);

        $list = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/transmission_list.json'), true);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('transmissions', $list);

        $retry = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/transmission_retry.json'), true);
        $this->assertIsArray($retry);
        $this->assertArrayHasKey('new_transmission_id', $retry);
    }

}
