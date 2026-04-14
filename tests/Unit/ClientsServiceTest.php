<?php

use App\Contracts\IntegrationProviderInterface;
use App\Services\Clients\ClientsService;
use App\Services\Integrations\IntegrationProviderFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientsServiceTest extends TestCase
{
    /**
     * Arrange: provider returns true.
     * Act: validatePeppolId is called.
     * Assert: true is returned and the integration log is called with 'success'.
     */
    #[Test]
    public function it_returns_true_and_logs_success_when_participant_is_valid(): void
    {
        $provider = $this->createMock(IntegrationProviderInterface::class);
        $provider->method('validateParticipant')->with('0088:1234')->willReturn(true);

        $integrations = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['log'])
            ->getMock();

        $integrations->expects($this->once())
            ->method('log')
            ->with('letspeppol', 'participants.validate', 'success', ['peppol_id' => '0088:1234']);

        $factory = (new IntegrationProviderFactory())->register('letspeppol', fn () => $provider);
        $service = new ClientsService($factory, $integrations);

        $this->assertTrue($service->validatePeppolId('0088:1234'));
    }

    /**
     * Arrange: provider returns false.
     * Act: validatePeppolId is called.
     * Assert: false is returned and the integration log is called with 'failed'.
     */
    #[Test]
    public function it_returns_false_and_logs_failed_when_participant_is_invalid(): void
    {
        $provider = $this->createMock(IntegrationProviderInterface::class);
        $provider->method('validateParticipant')->willReturn(false);

        $integrations = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['log'])
            ->getMock();

        $integrations->expects($this->once())
            ->method('log')
            ->with('letspeppol', 'participants.validate', 'failed', $this->anything());

        $factory = (new IntegrationProviderFactory())->register('letspeppol', fn () => $provider);
        $service = new ClientsService($factory, $integrations);

        $this->assertFalse($service->validatePeppolId('0088:bad'));
    }

    /**
     * Arrange: letspeppol provider is NOT registered.
     * Act: validatePeppolId is called.
     * Assert: false is returned without calling the log.
     */
    #[Test]
    public function it_returns_false_when_letspeppol_provider_is_not_registered(): void
    {
        $integrations = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['log'])
            ->getMock();

        $integrations->expects($this->never())->method('log');

        $factory = new IntegrationProviderFactory(); // empty – no providers registered
        $service = new ClientsService($factory, $integrations);

        $this->assertFalse($service->validatePeppolId('0088:1234'));
    }
}
