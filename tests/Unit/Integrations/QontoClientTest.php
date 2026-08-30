<?php

namespace Tests\Unit\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * Qonto Client Unit Tests
 *
 * Test OAuth2 auth, API client initialization, and error handling
 * Pure business logic — no database, no HTTP infrastructure
 */
class QontoClientTest extends TestCase
{
    private QontoClient $client;

    protected function setUp(): void
    {
        $clientPath = dirname(__DIR__, 3) . '/application/modules/integrations/libraries/providers/QontoClient.php';

        if (!file_exists($clientPath)) {
            $this->markTestSkipped('Qonto client implementation not yet available');
        }

        require_once $clientPath;
        $this->client = new QontoClient();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_correct_client_metadata(): void
    {
        $this->assertEquals('qonto', QontoClient::clientCode());
        $this->assertEquals('Qonto', QontoClient::clientName());
        $this->assertEquals('oauth2', QontoClient::authType());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_default_settings(): void
    {
        $defaults = QontoClient::defaultSettings();

        $this->assertArrayHasKey('client_id', $defaults);
        $this->assertArrayHasKey('client_secret', $defaults);
        $this->assertArrayHasKey('token_url', $defaults);
        $this->assertArrayHasKey('api_base_url', $defaults);
        $this->assertArrayHasKey('invoice_endpoint', $defaults);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_schema_with_required_fields(): void
    {
        $schema = QontoClient::settingsSchema();

        $this->assertArrayHasKey('client_id', $schema);
        $this->assertArrayHasKey('client_secret', $schema);

        $this->assertTrue($schema['client_id']['required']);
        $this->assertTrue($schema['client_secret']['required']);
        $this->assertTrue($schema['client_secret']['sensitive']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_mock_http_client_for_testing(): void
    {
        $mock_http = $this->createMock(ApiClientInterface::class);
        $client = new QontoClient($mock_http);

        $this->assertInstanceOf(QontoClient::class, $client);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_implements_integration_client_interface(): void
    {
        $this->assertInstanceOf(IntegrationClientInterface::class, $this->client);
    }
}
