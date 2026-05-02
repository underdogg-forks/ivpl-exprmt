<?php

use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use Core\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use Core\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use Core\Providers\LetsPeppolGatewayProvider;
use Core\Services\Integrations\IntegrationSettingsService;
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
    #[Test]
    public function it_integrates_provider_gateway_and_endpoints(): void
    {
        /* Arrange: Full gateway stack with mocked settings service and fake HTTP. */
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->method('letsPeppolSettings')
            ->willReturn([
                'base_url'      => 'https://api.letspeppol.test',
                'client_id'     => 'test-id',
                'client_secret' => 'test-secret',
            ]);

        $settingsService->method('activeTokenOrCreate')
            ->willReturn('integration-test-token');

        // Create a fake HTTP client to track requests
        $http = new FakeLetsPeppolHttpClient(200);

        // Inject factory that uses our fake HTTP client
        $gatewayFactory = function ($settings) use ($http) {
            $gateway = new LetsPeppolGatewayClient(
                $settings['base_url'],
                [],
                $http
            );
            $gateway->setAccessToken('integration-test-token');
            return $gateway;
        };

        $provider = new LetsPeppolGatewayProvider($settingsService, $gatewayFactory);

        /* Act: Provider methods are called through the full stack. */
        $participantValid = $provider->validateParticipant('0088:123456789');
        $invoiceSent = $provider->sendInvoice(['invoice_id' => 42]);

        /* Assert: Requests flow through the stack correctly. */
        $this->assertTrue($participantValid);
        $this->assertTrue($invoiceSent);
        $http->assertRequestMade('GET', 'https://api.letspeppol.test/api/participants/validate');
        $http->assertRequestMade('POST', 'https://api.letspeppol.test/api/invoices');
    }

    #[Test]
    public function it_performs_full_gateway_workflow(): void
    {
        /* Arrange: Gateway client with fake HTTP and endpoint clients. */
        $http = new FakeLetsPeppolHttpClient(200);

        $gateway = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            [],
            $http
        );

        /* Act: Full flow from validation to invoice sending. */
        $participantEndpoint = new ParticipantEndpoint($gateway);
        $isValid = $participantEndpoint->validatePeppolId('0088:123456789');

        $invoiceEndpoint = new InvoiceEndpoint($gateway);
        $response = $invoiceEndpoint->sendInvoice([
            'invoice_id'     => 42,
            'invoice_number' => 'INV-042',
        ]);

        /* Assert: Both operations work correctly. */
        $this->assertTrue($isValid);
        $http->assertRequestMade('GET', 'participants/validate');
        $this->assertSame(200, $response->getStatusCode());
        $http->assertRequestMade('POST', 'invoices');
    }

    #[Test]
    public function it_propagates_settings_through_gateway(): void
    {
        /* Arrange: Gateway with settings but no credentials to avoid authorization. */
        $http = new FakeLetsPeppolHttpClient(200);

        $settings = [
            'custom_setting' => 'custom-value',
        ];

        $gateway = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            $settings,
            $http
        );

        /* Act: Retrieve settings via getSettings(). */
        $retrievedCustom = $gateway->getSettings('custom_setting');
        $retrievedAll = $gateway->getSettings();

        /* Assert: Settings are accessible throughout the stack. */
        $this->assertSame('custom-value', $retrievedCustom);
        $this->assertSame($settings, $retrievedAll);
    }

    #[Test]
    public function it_validates_all_fixtures_are_well_formed(): void
    {
        /* Arrange: Load all fixtures. */
        $fixturesDir = __DIR__ . '/../Fixtures/LetsPeppol';
        $fixtures = [
            'participant_valid.json'   => ['valid', 'participant'],
            'participant_invalid.json' => ['valid', 'error'],
            'invoice_sent.json'        => ['status', 'id'],
            'oauth_token.json'         => ['access_token', 'token_type'],
        ];

        /* Act: Validate fixture structure. */
        foreach ($fixtures as $filename => $requiredKeys) {
            $filepath = $fixturesDir . '/' . $filename;
            $this->assertFileExists($filepath, "Fixture {$filename} should exist");

            $content = file_get_contents($filepath);
            $this->assertNotFalse($content, "Fixture {$filename} should be readable");

            $data = json_decode($content, true);
            $this->assertIsArray($data, "Fixture {$filename} should be valid JSON");

            /* Assert: All fixtures are valid JSON and match expected structure. */
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $data, "Fixture {$filename} should have key '{$key}'");
            }
        }
    }
}
