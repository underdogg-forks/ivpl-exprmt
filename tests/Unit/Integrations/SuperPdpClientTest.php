<?php

namespace Tests\Unit\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * SuperPDP Client Unit Tests
 *
 * Test OAuth2 auth, API client initialization, and error handling
 * Pure business logic — no database, no HTTP infrastructure
 */
class SuperPdpClientTest extends TestCase
{
    private SuperPdpClient $client;

    protected function setUp(): void
    {
        $clientPath = dirname(__DIR__, 3) . '/application/modules/integrations/libraries/providers/SuperPdpClient.php';

        if (!file_exists($clientPath)) {
            $this->markTestSkipped('SuperPDP client implementation not yet available');
        }

        require_once $clientPath;
        $this->client = new SuperPdpClient();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_correct_client_metadata(): void
    {
        $this->assertEquals('superpdp', SuperPdpClient::clientCode());
        $this->assertEquals('SuperPDP', SuperPdpClient::clientName());
        $this->assertEquals('oauth2', SuperPdpClient::authType());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_default_settings_with_beta_endpoints(): void
    {
        $defaults = SuperPdpClient::defaultSettings();

        $this->assertArrayHasKey('client_id', $defaults);
        $this->assertArrayHasKey('client_secret', $defaults);
        $this->assertArrayHasKey('token_url', $defaults);
        $this->assertArrayHasKey('api_base_url', $defaults);
        $this->assertArrayHasKey('invoice_endpoint', $defaults);
        $this->assertArrayHasKey('incoming_invoices_endpoint', $defaults);

        // Verify endpoints use v1.beta
        $this->assertStringContainsString('v1.beta', $defaults['invoice_endpoint']);
        $this->assertStringContainsString('v1.beta', $defaults['invoice_status_endpoint']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_schema_with_required_fields(): void
    {
        $schema = SuperPdpClient::settingsSchema();

        $this->assertArrayHasKey('client_id', $schema);
        $this->assertArrayHasKey('client_secret', $schema);
        $this->assertArrayHasKey('disable_pre_check', $schema);

        $this->assertTrue($schema['client_id']['required']);
        $this->assertTrue($schema['client_secret']['required']);
        $this->assertTrue($schema['client_secret']['sensitive']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_disable_pre_check_setting(): void
    {
        $defaults = SuperPdpClient::defaultSettings();

        $this->assertArrayHasKey('disable_pre_check', $defaults);
        $this->assertFalse($defaults['disable_pre_check']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_mock_http_client_for_testing(): void
    {
        $mock_http = $this->createMock(ApiClientInterface::class);
        $client = new SuperPdpClient($mock_http);

        $this->assertInstanceOf(SuperPdpClient::class, $client);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_implements_integration_client_interface(): void
    {
        $this->assertInstanceOf(IntegrationClientInterface::class, $this->client);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_initializes_with_null_access_token(): void
    {
        // Use reflection to verify token state
        $reflection = new \ReflectionClass($this->client);
        $property = $reflection->getProperty('accessToken');
        $property->setAccessible(true);

        $this->assertNull($property->getValue($this->client));
    }
}
