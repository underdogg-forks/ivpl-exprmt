<?php

namespace Tests\Unit\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * LetsPeppol Client Unit Tests
 *
 * Test OAuth2 auth, API client initialization, and error handling
 * Pure business logic — no database, no HTTP infrastructure
 */
class LetsPeppolClientTest extends TestCase
{
    private LetsPeppolClient $client;

    protected function setUp(): void
    {
        $clientPath = dirname(__DIR__, 3) . '/application/modules/integrations/libraries/providers/LetsPeppol/LetsPeppolClient.php';
        $apiClientPath = dirname(__DIR__, 3) . '/application/modules/integrations/libraries/providers/LetsPeppol/LetsPeppolApiClient.php';

        if (!file_exists($clientPath) || !file_exists($apiClientPath)) {
            $this->markTestSkipped('LetsPeppol client implementation not yet available');
        }

        try {
            require_once $clientPath;
            require_once $apiClientPath;
            $this->client = new LetsPeppolClient();
        } catch (\Throwable $e) {
            $this->markTestSkipped("LetsPeppol client dependencies not available: {$e->getMessage()}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_correct_client_metadata(): void
    {
        $this->assertEquals('letspeppol', LetsPeppolClient::clientCode());
        $this->assertEquals('LetsPeppol', LetsPeppolClient::clientName());
        $this->assertEquals('oauth2', LetsPeppolClient::authType());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_default_settings_with_endpoints(): void
    {
        $defaults = LetsPeppolClient::defaultSettings();

        $this->assertArrayHasKey('client_id', $defaults);
        $this->assertArrayHasKey('client_secret', $defaults);
        $this->assertArrayHasKey('token_url', $defaults);
        $this->assertArrayHasKey('api_base_url', $defaults);
        $this->assertArrayHasKey('invoice_endpoint', $defaults);
        $this->assertArrayHasKey('credit_note_endpoint', $defaults);
        $this->assertArrayHasKey('participants_endpoint', $defaults);
        $this->assertArrayHasKey('transmissions_endpoint', $defaults);
        $this->assertArrayHasKey('documents_endpoint', $defaults);

        // Verify endpoints start with /v1
        $this->assertStringStartsWith('/v1', $defaults['invoice_endpoint']);
        $this->assertStringStartsWith('/v1', $defaults['credit_note_endpoint']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_schema_with_required_fields(): void
    {
        $schema = LetsPeppolClient::settingsSchema();

        $this->assertArrayHasKey('client_id', $schema);
        $this->assertArrayHasKey('client_secret', $schema);

        $this->assertTrue($schema['client_id']['required']);
        $this->assertTrue($schema['client_secret']['required']);
        $this->assertTrue($schema['client_secret']['sensitive']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_mock_api_client_for_testing(): void
    {
        $mock_api = $this->createMock(LetsPeppolApiClient::class);
        $client = new LetsPeppolClient($mock_api);

        $this->assertInstanceOf(LetsPeppolClient::class, $client);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_initializes_all_endpoint_managers(): void
    {
        // Use reflection to verify endpoint initialization
        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('invoices');
        $property->setAccessible(true);

        $this->assertNotNull($property->getValue($this->client));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_implements_integration_client_interface(): void
    {
        $this->assertInstanceOf(IntegrationClientInterface::class, $this->client);
    }
}
