<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Gateways\LetsPeppol\Endpoints\DocumentEndpoint;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class DocumentEndpointTest extends TestCase
{
    private FakeLetsPeppolHttpClient $http;
    private LetsPeppolGatewayClient $gateway;
    private DocumentEndpoint $endpoint;

    protected function setUp(): void
    {
        $this->http = new FakeLetsPeppolHttpClient(200);
        $this->gateway = new LetsPeppolGatewayClient('https://api.test', [], $this->http);
        $this->endpoint = new DocumentEndpoint($this->gateway);
    }

    #[Test]
    public function it_gets_document_by_id(): void
    {
        // Arrange
        $documentId = 'ext-invoice-123';

        // Act
        $response = $this->endpoint->get($documentId);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/documents');
    }

    #[Test]
    public function it_downloads_document_content(): void
    {
        // Arrange
        $documentId = 'ext-invoice-123';

        // Act
        $response = $this->endpoint->download($documentId);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/documents/download');
    }

    #[Test]
    public function it_gets_document_metadata(): void
    {
        // Arrange
        $documentId = 'ext-invoice-123';

        // Act
        $response = $this->endpoint->getMetadata($documentId);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/documents/metadata');
    }

    #[Test]
    public function it_lists_documents_without_filters(): void
    {
        // Arrange - no filters

        // Act
        $response = $this->endpoint->list();

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/documents');
    }

    #[Test]
    public function it_lists_documents_with_filters(): void
    {
        // Arrange
        $filters = [
            'document_type' => 'invoice',
            'from'          => '2026-05-01',
            'to'            => '2026-05-31',
            'status'        => 'delivered',
        ];

        // Act
        $response = $this->endpoint->list($filters);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('GET', 'api/documents');
    }

    #[Test]
    public function it_archives_document_without_reason(): void
    {
        // Arrange
        $documentId = 'ext-invoice-123';

        // Act
        $response = $this->endpoint->archive($documentId);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/documents/archive');
    }

    #[Test]
    public function it_archives_document_with_reason(): void
    {
        // Arrange
        $documentId = 'ext-invoice-123';
        $reason = 'Invoice paid and reconciled';

        // Act
        $response = $this->endpoint->archive($documentId, $reason);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->http->assertRequestMade('POST', 'api/documents/archive');
    }

    #[Test]
    public function it_uses_xml_accept_header_for_download(): void
    {
        // Arrange
        $documentId = 'ext-invoice-123';

        // Act
        $this->endpoint->download($documentId);

        // Assert
        $this->assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Accept', $request['options']['headers']);
        $this->assertEquals('application/xml', $request['options']['headers']['Accept']);
    }

    #[Test]
    public function it_includes_authorization_headers_in_requests(): void
    {
        // Arrange
        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];
        
        $this->gateway = new LetsPeppolGatewayClient('https://api.test', $settings, $this->http);
        $this->gateway->setAccessToken('test-bearer-token');
        $this->endpoint = new DocumentEndpoint($this->gateway);

        // Act
        $this->endpoint->get('ext-invoice-123');

        // Assert
        $this->assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Authorization', $request['options']['headers']);
        $this->assertEquals('Bearer test-bearer-token', $request['options']['headers']['Authorization']);
    }
}
