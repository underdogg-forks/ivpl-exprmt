<?php

use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use App\Services\Integrations\IntegrationSettingsService;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IntegrationSettingsServiceTest extends TestCase
{
    /**
     * Arrange: mocked repository and crypt service.
     * Act: saveLetsPeppolSettings is called.
     * Assert: encrypted save is delegated with expected keys.
     */
    #[Test]
    public function it_saves_letspeppol_settings_with_encrypted_secret()
    {
        $repo = $this->getMockBuilder(Mdl_integrations::class)->disableOriginalConstructor()->onlyMethods(['saveEncryptedSettings'])->getMock();
        $crypt = $this->getMockBuilder(Crypt::class)->disableOriginalConstructor()->getMock();
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        $repo->expects($this->once())
            ->method('saveEncryptedSettings')
            ->with('letspeppol', $this->arrayHasKey('client_secret'), ['client_secret'], $crypt);

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $service->saveLetsPeppolSettings(['client_id' => 'id', 'client_secret' => 'secret', 'base_url' => 'https://api']);
    }

    /**
     * Arrange: existing active token.
     * Act: activeTokenOrCreate is called.
     * Assert: existing token is returned without OAuth call.
     */
    #[Test]
    public function it_returns_existing_active_token_when_present()
    {
        $repo = $this->getMockBuilder(Mdl_integrations::class)->disableOriginalConstructor()->onlyMethods(['activeToken'])->getMock();
        $crypt = $this->getMockBuilder(Crypt::class)->disableOriginalConstructor()->getMock();
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        $repo->method('activeToken')->willReturn('cached-token');
        $oauthFactory->expects($this->never())->method('make');

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $this->assertSame('cached-token', $service->activeTokenOrCreate());
    }
}
