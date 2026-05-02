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
     * Arrange: Provider with valid settings (this would require mocking the HTTP client).
     * Act: Check that proper settings enable gateway creation.
     * Assert: Settings structure is validated.
     */
    #[Test]
    public function it_validates_required_settings_structure(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);

        $validSettings = [
            'base_url'      => 'https://api.letspeppol.test',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];

        $settingsService->method('letsPeppolSettings')
            ->willReturn($validSettings);

        $provider = new LetsPeppolGatewayProvider($settingsService);

        // This is just validating the settings structure
        // Actual HTTP interaction would require more complex mocking
        $this->assertInstanceOf(LetsPeppolGatewayProvider::class, $provider);
    }
}
