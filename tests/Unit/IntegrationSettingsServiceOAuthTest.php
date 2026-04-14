<?php

use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use App\Services\Integrations\IntegrationSettingsService;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Battle-tested OAuth2 flow for IntegrationSettingsService.
 *
 * Covers the full lifecycle:
 *  - Token cache hit       → existing token returned, no OAuth call
 *  - Token cache miss      → new token fetched, stored, returned
 *  - Incomplete settings   → null returned (no crash)
 *  - Empty secret in form  → existing secret is preserved, not overwritten
 *  - saveToken called      → repository receives correct token + expiry
 */
class IntegrationSettingsServiceOAuthTest extends TestCase
{
    /**
     * Arrange: active token already cached in repository.
     * Act: activeTokenOrCreate is called.
     * Assert: cached token returned, OAuth provider never invoked.
     */
    #[Test]
    public function it_returns_cached_token_without_fetching_new_one(): void
    {
        $repo         = $this->makeMdlIntegrations(['activeToken']);
        $crypt        = $this->makeCrypt();
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        $repo->method('activeToken')->willReturn('existing-token-abc');
        $oauthFactory->expects($this->never())->method('make');

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $this->assertSame('existing-token-abc', $service->activeTokenOrCreate());
    }

    /**
     * Arrange: no cached token; valid settings present.
     * Act: activeTokenOrCreate is called.
     * Assert: new token is fetched via OAuth, saved, and returned.
     */
    #[Test]
    public function it_fetches_and_stores_new_token_when_cache_is_empty(): void
    {
        $methods = ['activeToken', 'settings', 'saveToken'];
        $repo    = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $crypt = $this->makeCrypt();

        $repo->method('activeToken')->willReturn(null);
        $repo->method('settings')->willReturn([
            'client_id'     => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'base_url'      => 'https://peppol.example.com',
        ]);

        $expiry   = time() + 3600;
        $newToken = new AccessToken(['access_token' => 'fresh-token-xyz', 'expires' => $expiry]);

        $provider = $this->createMock(GenericProvider::class);
        $provider->method('getAccessToken')->with('client_credentials')->willReturn($newToken);

        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $oauthFactory->method('make')->willReturn($provider);

        $repo->expects($this->once())
            ->method('saveToken')
            ->with('letspeppol', 'fresh-token-xyz', $expiry);

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $this->assertSame('fresh-token-xyz', $service->activeTokenOrCreate());
    }

    /**
     * Arrange: no cached token; settings are incomplete (missing client_id).
     * Act: activeTokenOrCreate is called.
     * Assert: null is returned without invoking the OAuth provider.
     */
    #[Test]
    public function it_returns_null_when_settings_are_incomplete(): void
    {
        $methods = ['activeToken', 'settings'];
        $repo    = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $crypt = $this->makeCrypt();

        $repo->method('activeToken')->willReturn(null);
        $repo->method('settings')->willReturn([
            'client_id'     => '',          // incomplete
            'client_secret' => 'secret',
            'base_url'      => 'https://peppol.example.com',
        ]);

        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $oauthFactory->expects($this->never())->method('make');

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $this->assertNull($service->activeTokenOrCreate());
    }

    /**
     * Arrange: settings form submitted with empty client_secret; existing secret stored.
     * Act: saveLetsPeppolSettings is called with empty secret.
     * Assert: existing secret is preserved in the persisted payload.
     */
    #[Test]
    public function it_preserves_existing_secret_when_form_submits_empty_password(): void
    {
        $methods = ['settings', 'saveEncryptedSettings'];
        $repo    = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $crypt        = $this->makeCrypt();
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        $repo->method('settings')->willReturn([
            'client_id'     => 'existing-id',
            'client_secret' => 'existing-secret',
            'base_url'      => 'https://peppol.example.com',
        ]);

        $repo->expects($this->once())
            ->method('saveEncryptedSettings')
            ->with(
                'letspeppol',
                $this->callback(fn ($data) => ($data['client_secret'] ?? '') === 'existing-secret'),
                ['client_secret'],
                $crypt,
            );

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $service->saveLetsPeppolSettings([
            'client_id'     => 'new-id',
            'client_secret' => '',          // empty — should not overwrite stored secret
            'base_url'      => 'https://peppol.example.com',
        ]);
    }

    /**
     * Arrange: settings form submitted with a new client_secret.
     * Act: saveLetsPeppolSettings is called.
     * Assert: the new secret is used (not the old one).
     */
    #[Test]
    public function it_uses_new_secret_when_form_submits_non_empty_password(): void
    {
        $methods = ['saveEncryptedSettings'];
        $repo    = $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $crypt        = $this->makeCrypt();
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        $repo->expects($this->once())
            ->method('saveEncryptedSettings')
            ->with(
                'letspeppol',
                $this->callback(fn ($data) => ($data['client_secret'] ?? '') === 'brand-new-secret'),
                ['client_secret'],
                $crypt,
            );

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $service->saveLetsPeppolSettings([
            'client_id'     => 'id',
            'client_secret' => 'brand-new-secret',
            'base_url'      => 'https://peppol.example.com',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMdlIntegrations(array $methods = []): Mdl_integrations
    {
        return $this->getMockBuilder(Mdl_integrations::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    private function makeCrypt(): Crypt
    {
        return $this->getMockBuilder(Crypt::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
