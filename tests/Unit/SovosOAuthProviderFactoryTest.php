<?php

use Core\Adapters\Sovos\Auth\SovosOAuthProviderFactory;
use Core\Integration\IntegrationCredentials;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SovosOAuthProviderFactoryTest extends TestCase
{
    /**
     * Arrange: credentials and HTTPS base URL.
     * Act: provider is built by factory.
     * Assert: OAuth endpoints are composed correctly.
     */
    #[Test]
    public function it_builds_expected_oauth_endpoints(): void
    {
        /* Arrange */
        $factory = new SovosOAuthProviderFactory();

        /* Act */
        $provider = $factory->make(
            new IntegrationCredentials('client-id', 'client-secret'),
            'https://api.sovos.example/'
        );

        /* Assert */
        $this->assertSame('https://api.sovos.example/oauth/authorize', $provider->getBaseAuthorizationUrl());
        $this->assertSame('https://api.sovos.example/oauth/token', $provider->getBaseAccessTokenUrl([]));
    }

    /**
     * Arrange: base URL with trailing slash.
     * Act: provider is built.
     * Assert: trailing slash is normalized (no double slashes).
     */
    #[Test]
    public function it_normalizes_trailing_slash_in_base_url(): void
    {
        /* Arrange */
        $factory = new SovosOAuthProviderFactory();

        /* Act */
        $provider = $factory->make(
            new IntegrationCredentials('id', 'secret'),
            'https://api.sovos.example/'
        );

        /* Assert */
        $this->assertStringNotContainsString('//', str_replace('https://', '', $provider->getBaseAuthorizationUrl()));
    }

    /**
     * Arrange: an invalid (non-URL) base URL.
     * Act: factory make() is called.
     * Assert: InvalidArgumentException is thrown.
     */
    #[Test]
    public function it_throws_on_invalid_url(): void
    {
        /* Arrange */
        $factory = new SovosOAuthProviderFactory();

        /* Act + Assert */
        $this->expectException(\InvalidArgumentException::class);
        $factory->make(new IntegrationCredentials('id', 'secret'), 'not-a-url');
    }

    /**
     * Arrange: an HTTP (non-HTTPS) base URL.
     * Act: factory make() is called.
     * Assert: InvalidArgumentException is thrown (HTTPS required for OAuth).
     */
    #[Test]
    public function it_throws_on_non_https_url(): void
    {
        /* Arrange */
        $factory = new SovosOAuthProviderFactory();

        /* Act + Assert */
        $this->expectException(\InvalidArgumentException::class);
        $factory->make(new IntegrationCredentials('id', 'secret'), 'http://api.sovos.example/');
    }

    /**
     * Arrange: an empty base URL.
     * Act: factory make() is called.
     * Assert: InvalidArgumentException is thrown.
     */
    #[Test]
    public function it_throws_on_empty_url(): void
    {
        /* Arrange */
        $factory = new SovosOAuthProviderFactory();

        /* Act + Assert */
        $this->expectException(\InvalidArgumentException::class);
        $factory->make(new IntegrationCredentials('id', 'secret'), '');
    }
}
