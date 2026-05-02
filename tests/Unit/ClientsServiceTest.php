<?php

use Core\Contracts\IntegrationProviderInterface;
use Core\Services\Clients\ClientsService;
use Core\Services\Integrations\IntegrationProviderFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeIntegrationRepository;

class ClientsServiceTest extends TestCase
{
    /**
     * Arrange: provider returns true, FakeIntegrationRepository (no CI model).
     * Act: validatePeppolId is called.
     * Assert: true is returned and a 'success' log entry is recorded.
     */
    #[Test]
    public function it_returns_true_and_logs_success_when_participant_is_valid(): void
    {
        $provider = $this->createMock(IntegrationProviderInterface::class);
        $provider->method('validateParticipant')->with('0088:1234')->willReturn(true);

        $repo    = new FakeIntegrationRepository();
        $factory = (new IntegrationProviderFactory())->register('letspeppol', fn () => $provider);
        $service = new ClientsService($factory, $repo);

        $result = $service->validatePeppolId('0088:1234');

        $this->assertTrue($result);
        $repo->assertLogged('letspeppol', 'participants.validate', 'success');
    }

    /**
     * Arrange: provider returns false.
     * Act: validatePeppolId is called.
     * Assert: false is returned and a 'failed' log entry is recorded.
     */
    #[Test]
    public function it_returns_false_and_logs_failed_when_participant_is_invalid(): void
    {
        $provider = $this->createMock(IntegrationProviderInterface::class);
        $provider->method('validateParticipant')->willReturn(false);

        $repo    = new FakeIntegrationRepository();
        $factory = (new IntegrationProviderFactory())->register('letspeppol', fn () => $provider);
        $service = new ClientsService($factory, $repo);

        $result = $service->validatePeppolId('0088:bad');

        $this->assertFalse($result);
        $repo->assertLogged('letspeppol', 'participants.validate', 'failed');
    }

    /**
     * Arrange: letspeppol provider is NOT registered.
     * Act: validatePeppolId is called.
     * Assert: false is returned without recording any log.
     */
    #[Test]
    public function it_returns_false_when_letspeppol_provider_is_not_registered(): void
    {
        $repo    = new FakeIntegrationRepository();
        $factory = new IntegrationProviderFactory(); // empty
        $service = new ClientsService($factory, $repo);

        $result = $service->validatePeppolId('0088:1234');

        $this->assertFalse($result);
        $repo->assertNotLogged();
    }
}
