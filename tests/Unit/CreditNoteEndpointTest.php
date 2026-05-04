<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Gateways\LetsPeppol\Endpoints\CreditNoteEndpoint;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class CreditNoteEndpointTest extends TestCase
{
    private FakeLetsPeppolHttpClient $http;
    private LetsPeppolGatewayClient $gateway;
    private CreditNoteEndpoint $endpoint;

    protected function setUp(): void
    {
        $this->http = new FakeLetsPeppolHttpClient(200);
        $this->gateway = new LetsPeppolGatewayClient('https://api.test', [], $this->http);
        $this->endpoint = new CreditNoteEndpoint($this->gateway);
    }

    #[Test]
    public function it_sends_credit_note(): void
    {
        /* Arrange */
        $payload = [
            'credit_note_id'     => 5,
            'credit_note_number' => 'CN-2026-001',
            'invoice_id'         => 1,
            'client_peppol_id'   => '0088:987654321',
            'amount'             => 250.00,
        ];

        /* Act */
        $response = $this->endpoint->send($payload);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/credit-notes');
    }

    #[Test]
    public function it_gets_credit_note_status(): void
    {
        /* Arrange */
        $creditNoteId = 5;

        /* Act */
        $response = $this->endpoint->getStatus($creditNoteId);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/credit-notes');
    }

    #[Test]
    public function it_cancels_credit_note_without_reason(): void
    {
        /* Arrange */
        $creditNoteId = 5;

        /* Act */
        $response = $this->endpoint->cancel($creditNoteId);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/credit-notes/cancel');
    }

    #[Test]
    public function it_cancels_credit_note_with_reason(): void
    {
        /* Arrange */
        $creditNoteId = 5;
        $reason = 'Issued in error - amount incorrect';

        /* Act */
        $response = $this->endpoint->cancel($creditNoteId, $reason);

        /* Assert */
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/credit-notes/cancel');
    }

    #[Test]
    public function it_includes_authorization_headers_in_requests(): void
    {
        /* Arrange */
        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];
        
        $this->gateway = new LetsPeppolGatewayClient('https://api.test', $settings, $this->http);
        $this->gateway->setAccessToken('test-bearer-token');
        $this->endpoint = new CreditNoteEndpoint($this->gateway);

        $payload = ['credit_note_id' => 5];

        /* Act */
        $this->endpoint->send($payload);

        /* Assert */
        $this->assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Authorization', $request['options']['headers']);
        $this->assertEquals('Bearer test-bearer-token', $request['options']['headers']['Authorization']);
    }

    #[Test]
    public function it_sends_json_payload_with_correct_headers(): void
    {
        /* Arrange */
        $payload = [
            'credit_note_id'     => 5,
            'credit_note_number' => 'CN-2026-001',
        ];

        /* Act */
        $this->endpoint->send($payload);

        /* Assert */
        $this->assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        $this->assertArrayHasKey('json', $request['options']);
        $this->assertEquals($payload, $request['options']['json']);
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Content-Type', $request['options']['headers']);
        $this->assertEquals('application/json', $request['options']['headers']['Content-Type']);
    }

    #[Test]
    public function it_validates_credit_note_fixtures_format(): void
    {
        $sent = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/credit_note_sent.json'), true);
        $this->assertIsArray($sent);
        $this->assertArrayHasKey('status', $sent);

        $status = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/credit_note_status.json'), true);
        $this->assertIsArray($status);
        $this->assertArrayHasKey('transmission_id', $status);

        $cancelled = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/credit_note_cancelled.json'), true);
        $this->assertIsArray($cancelled);
        $this->assertSame('cancelled', $cancelled['status']);
    }

}
