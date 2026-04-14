<?php

use App\Services\Integrations\IntegrationSettingsService;
use App\Services\Integrations\LetsPeppolService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LetsPeppolServiceTest extends TestCase
{
    /**
     * Arrange: missing base_url.
     * Act: validateParticipantId is called.
     * Assert: false is returned.
     */
    #[Test]
    public function it_returns_false_for_validation_when_base_url_missing()
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
    public function it_returns_false_for_send_when_base_url_missing()
    {
        $settingsService = $this->createMock(IntegrationSettingsService::class);
        $settingsService->method('letsPeppolSettings')->willReturn([]);

        $service = new LetsPeppolService($settingsService);

        $this->assertFalse($service->sendInvoice(['invoice_id' => 1]));
    }
}
