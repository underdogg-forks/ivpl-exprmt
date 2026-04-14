<?php

use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use App\Services\Integrations\IntegrationSettingsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCrypt;
use Tests\Fakes\FakeIntegrationRepository;

class IntegrationSettingsServiceTest extends TestCase
{
    /**
     * Arrange: FakeIntegrationRepository and FakeCrypt, no existing settings.
     * Act: saveLetsPeppolSettings is called with a client_secret.
     * Assert: the setting is persisted (encoded) in the fake repository.
     */
    #[Test]
    public function it_saves_letspeppol_settings_with_encrypted_secret(): void
    {
        $repo         = new FakeIntegrationRepository();
        $crypt        = new FakeCrypt();
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $service->saveLetsPeppolSettings([
            'client_id'     => 'my-id',
            'client_secret' => 'my-secret',
            'base_url'      => 'https://api.test',
        ]);

        $persisted = $repo->settings['letspeppol'] ?? [];

        $this->assertSame('my-id', $persisted['client_id']);
        // Secret was encoded (FakeCrypt uses base64).
        $this->assertSame(base64_encode('my-secret'), $persisted['client_secret']);
        $this->assertSame('https://api.test', $persisted['base_url']);
    }

    /**
     * Arrange: active token already cached in the repository.
     * Act: activeTokenOrCreate is called.
     * Assert: cached token is returned; no OAuth call is made.
     */
    #[Test]
    public function it_returns_existing_active_token_when_present(): void
    {
        $repo  = (new FakeIntegrationRepository())->setActiveToken('letspeppol', 'cached-token');
        $crypt = new FakeCrypt();

        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $oauthFactory->expects($this->never())->method('make');

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $this->assertSame('cached-token', $service->activeTokenOrCreate());
    }
}

