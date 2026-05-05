<?php

use Core\Adapters\Pagero\Auth\PageroOAuthProviderFactory;
use Core\Integration\IntegrationCredentials;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PageroOAuthProviderFactoryTest extends TestCase
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
        $factory = new PageroOAuthProviderFactory();

        /* Act */
        $provider = $factory->make(
            new IntegrationCredentials('client-id', 'client-secret'),
            'https://api.pagero.example/'
        );

        /* Assert */
        $this->assertSame('https://api.pagero.example/oauth/authorize', $provider->getBaseAuthorizationUrl());
        $this->assertSame('https://api.pagero.example/oauth/token', $provider->getBaseAccessTokenUrl([]));
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
        $factory = new PageroOAuthProviderFactory();

        /* Act */
        $provider = $factory->make(
            new IntegrationCredentials('id', 'secret'),
            'https://api.pagero.example/'
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
        $factory = new PageroOAuthProviderFactory();

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
        $factory = new PageroOAuthProviderFactory();

        /* Act + Assert */
        $this->expectException(\InvalidArgumentException::class);
        $factory->make(new IntegrationCredentials('id', 'secret'), 'http://api.pagero.example/');
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
        $factory = new PageroOAuthProviderFactory();

        /* Act + Assert */
        $this->expectException(\InvalidArgumentException::class);
        $factory->make(new IntegrationCredentials('id', 'secret'), '');
    }
}
