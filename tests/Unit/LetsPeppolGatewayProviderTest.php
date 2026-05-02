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
     * Arrange: Provider with valid settings and cached token.
     * Act: Call validateParticipant twice.
     * Assert: activeTokenOrCreate is called only once (token cached in gateway).
     */
    #[Test]
    public function it_reuses_cached_token_for_multiple_calls(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);

        $validSettings = [
            'base_url'      => 'https://api.letspeppol.test',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];

        $settingsService->method('letsPeppolSettings')
            ->willReturn($validSettings);

        // Token should be requested only once despite multiple provider calls
        $settingsService->expects($this->once())
            ->method('activeTokenOrCreate')
            ->willReturn('cached-token-123');

        $provider = new LetsPeppolGatewayProvider($settingsService);

        // First call - should get token
        $provider->validateParticipant('0088:123456789');

        // Second call - should reuse gateway with cached token
        $provider->sendInvoice(['invoice_id' => 1]);

        // The expectation above ensures activeTokenOrCreate was called only once
        $this->assertTrue(true);
    }
}
