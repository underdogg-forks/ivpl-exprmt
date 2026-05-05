<?php

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Adapters\Pagero\Auth\PageroOAuthProviderFactory;
use Core\Adapters\Sovos\Auth\SovosOAuthProviderFactory;
use Core\Services\Integrations\IntegrationSettingsService;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeCrypt;
use Tests\Fakes\FakeIntegrationRepository;

/**
 * Tests for credential storage and settings retrieval for StoreCove,
 * Pagero, and Sovos via IntegrationSettingsService.
 *
 * Covers:
 *  - Saving and retrieving settings (encryption of secrets/keys at rest)
 *  - Read-path: decryption is exercised (seed encrypted, assert plaintext)
 *  - OAuth2 token cache hit (no new token fetched)
 *  - OAuth2 token cache miss (new token fetched and stored)
 *  - Incomplete settings → null returned gracefully
 *  - Preserving existing secret when form submits empty value (exact equality)
 *  - Token invalidation when credentials are updated
 */
class GatewayCredentialStorageTest extends TestCase
{
    private function makeService(
        FakeIntegrationRepository $repo,
        FakeCrypt $crypt,
        ?PageroOAuthProviderFactory $pageroFactory = null,
        ?SovosOAuthProviderFactory $sovosFactory = null,
    ): IntegrationSettingsService {
        return new IntegrationSettingsService(
            $repo,
            $crypt,
            $this->createMock(LetsPeppolOAuthProviderFactory::class),
            $pageroFactory,
            $sovosFactory,
        );
    }

    // ── StoreCove ─────────────────────────────────────────────────────────────

    /**
     * Arrange: empty repository.
     * Act: saveStoreCoveSettings is called with an API key and base URL.
     * Assert: API key is encrypted; base URL is stored in plaintext.
     */
    #[Test]
    public function it_saves_storecove_settings_with_encrypted_api_key(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->saveStoreCoveSettings([
            'api_key'  => 'sk-live-abc123',
            'base_url' => 'https://api.storecove.com',
        ]);

        /* Assert */
        $persisted = $repo->settings['storecove'] ?? [];
        $this->assertSame(base64_encode('sk-live-abc123'), $persisted['api_key']);
        $this->assertSame('https://api.storecove.com', $persisted['base_url']);
    }

    /**
     * Arrange: existing API key in repository (stored encrypted).
     * Act: saveStoreCoveSettings is called with empty api_key.
     * Assert: existing encrypted key is preserved exactly.
     */
    #[Test]
    public function it_preserves_existing_storecove_api_key_when_form_submits_empty(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $existingEncrypted = base64_encode('existing-key');
        $repo->setSettings('storecove', [
            'api_key'  => $existingEncrypted,
            'base_url' => 'https://api.storecove.com',
        ]);

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->saveStoreCoveSettings([
            'api_key'  => '',   // empty — must not overwrite
            'base_url' => 'https://api.storecove.com',
        ]);

        /* Assert: stored value is unchanged from the original encrypted value */
        $persisted = $repo->settings['storecove'] ?? [];
        $this->assertSame($existingEncrypted, $persisted['api_key']);
    }

    /**
     * Arrange: repository seeded with an encrypted API key.
     * Act: storeCoveSettings is called.
     * Assert: the returned api_key is the decrypted plaintext value.
     */
    #[Test]
    public function it_retrieves_and_decrypts_storecove_settings(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        // Seed with the encrypted form of the key (base64 via FakeCrypt)
        $repo->setSettings('storecove', [
            'api_key'  => base64_encode('my-secret-api-key'),
            'base_url' => 'https://api.storecove.com',
        ]);

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $result = $service->storeCoveSettings();

        /* Assert: decrypted back to plaintext */
        $this->assertSame('my-secret-api-key', $result['api_key']);
        $this->assertSame('https://api.storecove.com', $result['base_url']);
    }

    /**
     * Arrange: existing StoreCove settings with active token in cache.
     * Act: saveStoreCoveSettings is called with new API key.
     * Assert: cached token is invalidated after settings update.
     */
    #[Test]
    public function it_invalidates_storecove_token_when_settings_are_saved(): void
    {
        /* Arrange */
        $repo  = (new FakeIntegrationRepository())->setActiveToken('storecove', 'stale-token');
        $crypt = new FakeCrypt();

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->saveStoreCoveSettings(['api_key' => 'new-key', 'base_url' => 'https://api.storecove.com']);

        /* Assert: token was invalidated */
        $this->assertNull($repo->activeToken('storecove'));
    }

    // ── Pagero ────────────────────────────────────────────────────────────────

    /**
     * Arrange: empty repository.
     * Act: savePageroSettings is called with credentials.
     * Assert: client_secret is encrypted; other fields stored in plaintext.
     */
    #[Test]
    public function it_saves_pagero_settings_with_encrypted_secret(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->savePageroSettings([
            'client_id'     => 'pagero-client-id',
            'client_secret' => 'pagero-secret',
            'base_url'      => 'https://api.pagero.com',
        ]);

        /* Assert */
        $persisted = $repo->settings['pagero'] ?? [];
        $this->assertSame('pagero-client-id', $persisted['client_id']);
        $this->assertSame(base64_encode('pagero-secret'), $persisted['client_secret']);
        $this->assertSame('https://api.pagero.com', $persisted['base_url']);
    }

    /**
     * Arrange: repository seeded with an encrypted Pagero client secret.
     * Act: pageroSettings is called.
     * Assert: the returned client_secret is the decrypted plaintext value.
     */
    #[Test]
    public function it_retrieves_and_decrypts_pagero_settings(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('pagero', [
            'client_id'     => 'pagero-id',
            'client_secret' => base64_encode('pagero-plaintext-secret'),
            'base_url'      => 'https://api.pagero.com',
        ]);

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $result = $service->pageroSettings();

        /* Assert: decrypted back to plaintext */
        $this->assertSame('pagero-id', $result['client_id']);
        $this->assertSame('pagero-plaintext-secret', $result['client_secret']);
        $this->assertSame('https://api.pagero.com', $result['base_url']);
    }

    /**
     * Arrange: active token already cached for Pagero.
     * Act: activePageroTokenOrCreate is called.
     * Assert: cached token returned; OAuth provider never invoked.
     */
    #[Test]
    public function it_returns_cached_pagero_token_without_fetching_new_one(): void
    {
        /* Arrange */
        $repo  = (new FakeIntegrationRepository())->setActiveToken('pagero', 'cached-pagero-token');
        $crypt = new FakeCrypt();

        $pageroFactory = $this->createMock(PageroOAuthProviderFactory::class);
        $pageroFactory->expects($this->never())->method('make');

        $service = $this->makeService($repo, $crypt, $pageroFactory);

        /* Act */
        $result = $service->activePageroTokenOrCreate();

        /* Assert */
        $this->assertSame('cached-pagero-token', $result);
    }

    /**
     * Arrange: no cached token; valid Pagero settings present.
     * Act: activePageroTokenOrCreate is called.
     * Assert: new token is fetched via OAuth, saved, and returned.
     */
    #[Test]
    public function it_fetches_and_stores_new_pagero_token_when_cache_is_empty(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('pagero', [
            'client_id'     => 'pagero-id',
            'client_secret' => 'pagero-secret',
            'base_url'      => 'https://api.pagero.com',
        ]);

        $expiry   = time() + 3600;
        $newToken = new AccessToken(['access_token' => 'fresh-pagero-token', 'expires' => $expiry]);

        $provider = $this->createMock(GenericProvider::class);
        $provider->method('getAccessToken')->with('client_credentials')->willReturn($newToken);

        $pageroFactory = $this->createMock(PageroOAuthProviderFactory::class);
        $pageroFactory->method('make')->willReturn($provider);

        $service = $this->makeService($repo, $crypt, $pageroFactory);

        /* Act */
        $result = $service->activePageroTokenOrCreate();

        /* Assert */
        $this->assertSame('fresh-pagero-token', $result);
        $repo->assertTokenSaved('pagero', 'fresh-pagero-token');
    }

    /**
     * Arrange: no cached token; Pagero settings are incomplete.
     * Act: activePageroTokenOrCreate is called.
     * Assert: null is returned without invoking the OAuth provider.
     */
    #[Test]
    public function it_returns_null_when_pagero_settings_are_incomplete(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('pagero', [
            'client_id'     => '',   // incomplete
            'client_secret' => 'secret',
            'base_url'      => 'https://api.pagero.com',
        ]);

        $pageroFactory = $this->createMock(PageroOAuthProviderFactory::class);
        $pageroFactory->expects($this->never())->method('make');

        $service = $this->makeService($repo, $crypt, $pageroFactory);

        /* Act */
        $result = $service->activePageroTokenOrCreate();

        /* Assert */
        $this->assertNull($result);
    }

    /**
     * Arrange: existing Pagero secret in repository (stored encrypted).
     * Act: savePageroSettings is called with empty client_secret.
     * Assert: existing encrypted secret is preserved exactly.
     */
    #[Test]
    public function it_preserves_existing_pagero_secret_when_form_submits_empty(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $existingEncrypted = base64_encode('existing-pagero-secret');
        $repo->setSettings('pagero', [
            'client_id'     => 'pagero-id',
            'client_secret' => $existingEncrypted,
            'base_url'      => 'https://api.pagero.com',
        ]);

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->savePageroSettings([
            'client_id'     => 'pagero-id',
            'client_secret' => '',   // empty — must not overwrite
            'base_url'      => 'https://api.pagero.com',
        ]);

        /* Assert: stored value is unchanged from the original encrypted value */
        $persisted = $repo->settings['pagero'] ?? [];
        $this->assertSame($existingEncrypted, $persisted['client_secret']);
    }

    /**
     * Arrange: existing Pagero settings with active token.
     * Act: savePageroSettings is called with updated credentials.
     * Assert: cached token is invalidated after settings update.
     */
    #[Test]
    public function it_invalidates_pagero_token_when_settings_are_saved(): void
    {
        /* Arrange */
        $repo  = (new FakeIntegrationRepository())->setActiveToken('pagero', 'stale-pagero-token');
        $crypt = new FakeCrypt();

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->savePageroSettings([
            'client_id'     => 'new-id',
            'client_secret' => 'new-secret',
            'base_url'      => 'https://api.pagero.com',
        ]);

        /* Assert */
        $this->assertNull($repo->activeToken('pagero'));
    }

    // ── Sovos ─────────────────────────────────────────────────────────────────

    /**
     * Arrange: empty repository.
     * Act: saveSovosSettings is called with credentials.
     * Assert: client_secret is encrypted; other fields stored in plaintext.
     */
    #[Test]
    public function it_saves_sovos_settings_with_encrypted_secret(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->saveSovosSettings([
            'client_id'     => 'sovos-client-id',
            'client_secret' => 'sovos-secret',
            'base_url'      => 'https://api.sovos.com',
        ]);

        /* Assert */
        $persisted = $repo->settings['sovos'] ?? [];
        $this->assertSame('sovos-client-id', $persisted['client_id']);
        $this->assertSame(base64_encode('sovos-secret'), $persisted['client_secret']);
        $this->assertSame('https://api.sovos.com', $persisted['base_url']);
    }

    /**
     * Arrange: repository seeded with an encrypted Sovos client secret.
     * Act: sovosSettings is called.
     * Assert: the returned client_secret is the decrypted plaintext value.
     */
    #[Test]
    public function it_retrieves_and_decrypts_sovos_settings(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('sovos', [
            'client_id'     => 'sovos-id',
            'client_secret' => base64_encode('sovos-plaintext-secret'),
            'base_url'      => 'https://api.sovos.com',
        ]);

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $result = $service->sovosSettings();

        /* Assert: decrypted back to plaintext */
        $this->assertSame('sovos-id', $result['client_id']);
        $this->assertSame('sovos-plaintext-secret', $result['client_secret']);
        $this->assertSame('https://api.sovos.com', $result['base_url']);
    }

    /**
     * Arrange: active token already cached for Sovos.
     * Act: activeSovosTokenOrCreate is called.
     * Assert: cached token returned; OAuth provider never invoked.
     */
    #[Test]
    public function it_returns_cached_sovos_token_without_fetching_new_one(): void
    {
        /* Arrange */
        $repo  = (new FakeIntegrationRepository())->setActiveToken('sovos', 'cached-sovos-token');
        $crypt = new FakeCrypt();

        $sovosFactory = $this->createMock(SovosOAuthProviderFactory::class);
        $sovosFactory->expects($this->never())->method('make');

        $service = $this->makeService($repo, $crypt, null, $sovosFactory);

        /* Act */
        $result = $service->activeSovosTokenOrCreate();

        /* Assert */
        $this->assertSame('cached-sovos-token', $result);
    }

    /**
     * Arrange: no cached token; valid Sovos settings present.
     * Act: activeSovosTokenOrCreate is called.
     * Assert: new token is fetched via OAuth, saved, and returned.
     */
    #[Test]
    public function it_fetches_and_stores_new_sovos_token_when_cache_is_empty(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('sovos', [
            'client_id'     => 'sovos-id',
            'client_secret' => 'sovos-secret',
            'base_url'      => 'https://api.sovos.com',
        ]);

        $expiry   = time() + 3600;
        $newToken = new AccessToken(['access_token' => 'fresh-sovos-token', 'expires' => $expiry]);

        $provider = $this->createMock(GenericProvider::class);
        $provider->method('getAccessToken')->with('client_credentials')->willReturn($newToken);

        $sovosFactory = $this->createMock(SovosOAuthProviderFactory::class);
        $sovosFactory->method('make')->willReturn($provider);

        $service = $this->makeService($repo, $crypt, null, $sovosFactory);

        /* Act */
        $result = $service->activeSovosTokenOrCreate();

        /* Assert */
        $this->assertSame('fresh-sovos-token', $result);
        $repo->assertTokenSaved('sovos', 'fresh-sovos-token');
    }

    /**
     * Arrange: no cached token; Sovos settings are incomplete.
     * Act: activeSovosTokenOrCreate is called.
     * Assert: null is returned without invoking the OAuth provider.
     */
    #[Test]
    public function it_returns_null_when_sovos_settings_are_incomplete(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $repo->setSettings('sovos', [
            'client_id'     => 'sovos-id',
            'client_secret' => '',   // incomplete
            'base_url'      => 'https://api.sovos.com',
        ]);

        $sovosFactory = $this->createMock(SovosOAuthProviderFactory::class);
        $sovosFactory->expects($this->never())->method('make');

        $service = $this->makeService($repo, $crypt, null, $sovosFactory);

        /* Act */
        $result = $service->activeSovosTokenOrCreate();

        /* Assert */
        $this->assertNull($result);
    }

    /**
     * Arrange: existing Sovos secret in repository (stored encrypted).
     * Act: saveSovosSettings is called with empty client_secret.
     * Assert: existing encrypted secret is preserved exactly.
     */
    #[Test]
    public function it_preserves_existing_sovos_secret_when_form_submits_empty(): void
    {
        /* Arrange */
        $repo  = new FakeIntegrationRepository();
        $crypt = new FakeCrypt();

        $existingEncrypted = base64_encode('existing-sovos-secret');
        $repo->setSettings('sovos', [
            'client_id'     => 'sovos-id',
            'client_secret' => $existingEncrypted,
            'base_url'      => 'https://api.sovos.com',
        ]);

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->saveSovosSettings([
            'client_id'     => 'sovos-id',
            'client_secret' => '',   // empty — must not overwrite
            'base_url'      => 'https://api.sovos.com',
        ]);

        /* Assert: stored value is unchanged from the original encrypted value */
        $persisted = $repo->settings['sovos'] ?? [];
        $this->assertSame($existingEncrypted, $persisted['client_secret']);
    }

    /**
     * Arrange: existing Sovos settings with active token.
     * Act: saveSovosSettings is called with updated credentials.
     * Assert: cached token is invalidated after settings update.
     */
    #[Test]
    public function it_invalidates_sovos_token_when_settings_are_saved(): void
    {
        /* Arrange */
        $repo  = (new FakeIntegrationRepository())->setActiveToken('sovos', 'stale-sovos-token');
        $crypt = new FakeCrypt();

        $service = $this->makeService($repo, $crypt);

        /* Act */
        $service->saveSovosSettings([
            'client_id'     => 'new-id',
            'client_secret' => 'new-secret',
            'base_url'      => 'https://api.sovos.com',
        ]);

        /* Assert */
        $this->assertNull($repo->activeToken('sovos'));
    }
}
