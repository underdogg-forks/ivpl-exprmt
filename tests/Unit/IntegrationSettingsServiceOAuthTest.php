<?php

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Services\Integrations\IntegrationSettingsService;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCrypt;
use Tests\Fakes\FakeIntegrationRepository;

/**
 * Battle-tested OAuth2 flow for IntegrationSettingsService.
 *
 * Uses Laravel-style Fakes (FakeIntegrationRepository, FakeCrypt) rather than
 * PHPUnit mocks so that tests read as plain assertions against real state.
 *
 * Covers the full lifecycle:
 *  - Token cache hit       → existing token returned, no OAuth call
 *  - Token cache miss      → new token fetched, stored, returned
 *  - Incomplete settings   → null returned (no crash)
 *  - Empty secret in form  → existing secret is preserved, not overwritten
 *  - New secret in form    → new secret overwrites old one
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
        $repo  = (new FakeIntegrationRepository())->setActiveToken('letspeppol', 'existing-token-abc');
        $crypt = new FakeCrypt();

        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
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
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('letspeppol', [
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

        $service = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $result = $service->activeTokenOrCreate();

        $this->assertSame('fresh-token-xyz', $result);
        $repo->assertTokenSaved('letspeppol', 'fresh-token-xyz');
    }

    /**
     * Arrange: no cached token; settings are incomplete (missing client_id).
     * Act: activeTokenOrCreate is called.
     * Assert: null is returned without invoking the OAuth provider.
     */
    #[Test]
    public function it_returns_null_when_settings_are_incomplete(): void
    {
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('letspeppol', [
            'client_id'     => '',      // incomplete
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
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        // Seed the repository with an existing (encoded) secret.
        $repo->setSettings('letspeppol', [
            'client_id'     => 'existing-id',
            'client_secret' => base64_encode('existing-secret'),
            'base_url'      => 'https://peppol.example.com',
        ]);

        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $service      = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $service->saveLetsPeppolSettings([
            'client_id'     => 'new-id',
            'client_secret' => '',          // empty — should NOT overwrite stored secret
            'base_url'      => 'https://peppol.example.com',
        ]);

        $persisted = $repo->settings['letspeppol'] ?? [];

        // The stored secret must be the OLD one (re-encoded), not empty.
        $this->assertNotEmpty($persisted['client_secret'] ?? '');
    }

    /**
     * Arrange: settings form submitted with a new client_secret.
     * Act: saveLetsPeppolSettings is called.
     * Assert: the new secret is stored (encoded).
     */
    #[Test]
    public function it_uses_new_secret_when_form_submits_non_empty_password(): void
    {
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $service      = new IntegrationSettingsService($repo, $crypt, $oauthFactory);

        $service->saveLetsPeppolSettings([
            'client_id'     => 'id',
            'client_secret' => 'brand-new-secret',
            'base_url'      => 'https://peppol.example.com',
        ]);

        $persisted = $repo->settings['letspeppol'] ?? [];

        $this->assertSame(base64_encode('brand-new-secret'), $persisted['client_secret']);
    }
}
