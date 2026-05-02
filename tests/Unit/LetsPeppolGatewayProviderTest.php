<?php

use App\Providers\LetsPeppolGatewayProvider;
use App\Services\Integrations\IntegrationSettingsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LetsPeppolGatewayProviderTest extends TestCase
{
    /**
     * Arrange: Provider with missing base_url in settings.
     * Act: validateParticipant is called.
     * Assert: Returns false immediately without making requests.
     */
    #[Test]
    public function it_returns_false_when_base_url_is_missing(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->expects($this->once())
            ->method('letsPeppolSettings')
            ->willReturn([]); // Missing base_url

        $provider = new LetsPeppolGatewayProvider($settingsService);

        $result = $provider->validateParticipant('0088:123456789');

        $this->assertFalse($result);
    }

    /**
     * Arrange: Provider with missing credentials in settings.
     * Act: sendInvoice is called.
     * Assert: Returns false immediately without making requests.
     */
    #[Test]
    public function it_returns_false_when_credentials_are_missing(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->expects($this->once())
            ->method('letsPeppolSettings')
            ->willReturn([
                'base_url' => 'https://api.letspeppol.test',
                // Missing client_id and client_secret
            ]);

        $provider = new LetsPeppolGatewayProvider($settingsService);

        $result = $provider->sendInvoice(['invoice_id' => 1]);

        $this->assertFalse($result);
    }

    /**
     * Arrange: Provider with valid settings and cached token, using fake gateway.
     * Act: Call validateParticipant and sendInvoice.
     * Assert: Provider methods return expected results using the injected fake gateway.
     */
    #[Test]
    public function it_exercises_full_provider_flow_with_fake_gateway(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);

        $validSettings = [
            'base_url'      => 'https://api.letspeppol.test',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];

        $settingsService->method('letsPeppolSettings')
            ->willReturn($validSettings);

        $settingsService->method('activeTokenOrCreate')
            ->willReturn('cached-token-123');

        // Inject a factory that returns a gateway with a fake HTTP client
        $gatewayFactory = function ($settings, $service) {
            $http = new \Tests\Fakes\FakeLetsPeppolHttpClient(200);
            $gateway = new \App\Gateways\LetsPeppol\LetsPeppolGatewayClient(
                $settings['base_url'],
                [],
                $http
            );
            $gateway->setAccessToken('test-token');
            return $gateway;
        };

        $provider = new LetsPeppolGatewayProvider($settingsService, $gatewayFactory);

        // Test validateParticipant
        $isValid = $provider->validateParticipant('0088:123456789');
        $this->assertTrue($isValid);

        // Test sendInvoice
        $sent = $provider->sendInvoice(['invoice_id' => 1]);
        $this->assertTrue($sent);
    }
}
