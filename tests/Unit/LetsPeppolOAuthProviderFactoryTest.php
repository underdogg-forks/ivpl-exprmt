<?php

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Integration\IntegrationCredentials;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LetsPeppolOAuthProviderFactoryTest extends TestCase
{
    /**
     * Arrange: credentials and base URL.
     * Act: provider is built by factory.
     * Assert: OAuth endpoints are composed correctly.
     */
    #[Test]
    public function it_builds_expected_oauth_endpoints()
    {
        $factory = new LetsPeppolOAuthProviderFactory();

        $provider = $factory->make(
            new IntegrationCredentials('client-id', 'client-secret'),
            'https://api.letspeppol.example/'
        );

        $this->assertSame('https://api.letspeppol.example/oauth/authorize', $provider->getBaseAuthorizationUrl());
        $this->assertSame('https://api.letspeppol.example/oauth/token', $provider->getBaseAccessTokenUrl([]));
    }
}
