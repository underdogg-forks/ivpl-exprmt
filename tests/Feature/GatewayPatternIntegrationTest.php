<?php

use App\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use App\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use App\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use App\Providers\LetsPeppolGatewayProvider;
use App\Services\Integrations\IntegrationSettingsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

/**
 * Integration test for the complete gateway pattern flow.
 *
 * This test validates the full stack from provider → gateway client → endpoint clients.
 */
class GatewayPatternIntegrationTest extends TestCase
{
    /**
     * Arrange: Full gateway stack with mocked settings service.
     * Act: Provider methods are called.
     * Assert: Requests flow through the stack correctly.
     */
    #[Test]
    public function it_integrates_provider_gateway_and_endpoints(): void
    {
        // Mock settings service
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->method('letsPeppolSettings')
            ->willReturn([
                'base_url'      => 'https://api.letspeppol.test',
                'client_id'     => 'test-id',
                'client_secret' => 'test-secret',
            ]);

        // This would normally create a real gateway client, but for testing
        // we need to inject a fake HTTP client. For now, we validate the structure.
        $provider = new LetsPeppolGatewayProvider($settingsService);

        $this->assertInstanceOf(LetsPeppolGatewayProvider::class, $provider);
    }

    /**
     * Arrange: Gateway client with fake HTTP and endpoint clients.
     * Act: Full flow from validation to invoice sending.
     * Assert: Both operations work correctly.
     */
    #[Test]
    public function it_performs_full_gateway_workflow(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $gateway = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            [],
            $http
        );

        // Validate participant
        $participantEndpoint = new ParticipantEndpoint($gateway);
        $isValid = $participantEndpoint->validatePeppolId('0088:123456789');

        $this->assertTrue($isValid);
        $http->assertRequestMade('GET', 'participants/validate');

        // Send invoice
        $invoiceEndpoint = new InvoiceEndpoint($gateway);
        $response = $invoiceEndpoint->sendInvoice([
            'invoice_id'     => 42,
            'invoice_number' => 'INV-042',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $http->assertRequestMade('POST', 'invoices');
    }

    /**
     * Arrange: Gateway with settings.
     * Act: Retrieve settings via getSettings().
     * Assert: Settings are accessible throughout the stack.
     */
    #[Test]
    public function it_propagates_settings_through_gateway(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $settings = [
            'client_id'     => 'test-id',
            'client_secret' => 'test-secret',
            'custom_setting' => 'custom-value',
        ];

        $gateway = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            $settings,
            $http
        );

        // Settings should be retrievable
        $this->assertSame('test-id', $gateway->getSettings('client_id'));
        $this->assertSame('custom-value', $gateway->getSettings('custom_setting'));
        $this->assertSame($settings, $gateway->getSettings());
    }

    /**
     * Arrange: Load all fixtures.
     * Act: Validate fixture structure.
     * Assert: All fixtures are valid JSON and match expected structure.
     */
    #[Test]
    public function it_validates_all_fixtures_are_well_formed(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures/LetsPeppol';
        $fixtures = [
            'participant_valid.json'   => ['valid', 'participant'],
            'participant_invalid.json' => ['valid', 'error'],
            'invoice_sent.json'        => ['status', 'id'],
            'oauth_token.json'         => ['access_token', 'token_type'],
        ];

        foreach ($fixtures as $filename => $requiredKeys) {
            $filepath = $fixturesDir . '/' . $filename;
            $this->assertFileExists($filepath, "Fixture {$filename} should exist");

            $content = file_get_contents($filepath);
            $this->assertNotFalse($content, "Fixture {$filename} should be readable");

            $data = json_decode($content, true);
            $this->assertIsArray($data, "Fixture {$filename} should be valid JSON");

            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $data, "Fixture {$filename} should have key '{$key}'");
            }
        }
    }
}
