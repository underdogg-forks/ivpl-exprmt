<?php

use Core\Providers\LetsPeppolProvider;
use Core\Services\Integrations\IntegrationSettingsService;
use Core\Services\Integrations\LetsPeppolService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LetsPeppolServiceTest extends TestCase
{
    /**
     * Arrange: missing base_url.
     * Act: validateParticipantId is called (via deprecated compat shim).
     * Assert: false is returned.
     */
    #[Test]
    public function it_returns_false_for_validation_when_base_url_missing(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->method('letsPeppolSettings')->willReturn([]);

        $service = new LetsPeppolService($settingsService);

        $this->assertFalse($service->validateParticipantId('0088:1234'));
    }

    /**
     * Arrange: missing base_url for send.
     * Act: sendInvoice is called.
     * Assert: false is returned.
     */
    #[Test]
    public function it_returns_false_for_send_when_base_url_missing(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->method('letsPeppolSettings')->willReturn([]);

        $service = new LetsPeppolService($settingsService);

        $this->assertFalse($service->sendInvoice(['invoice_id' => 1]));
    }

    /**
     * Arrange: LetsPeppolService (deprecated) is constructed.
     * Act: instanceof checks.
     * Assert: it is still a LetsPeppolProvider (inheritance) and implements IntegrationProviderInterface.
     */
    #[Test]
    public function it_is_a_lets_peppol_provider_and_implements_the_contract(): void
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);

        $service = new LetsPeppolService($settingsService);

        $this->assertInstanceOf(LetsPeppolProvider::class, $service);
        $this->assertInstanceOf(\Core\Contracts\IntegrationProviderInterface::class, $service);
    }
}

