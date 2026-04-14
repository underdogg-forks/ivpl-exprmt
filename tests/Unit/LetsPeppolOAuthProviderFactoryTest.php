<?php

declare(strict_types=1);

use App\Integration\IntegrationCredentials;
use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use PHPUnit\Framework\TestCase;

final class LetsPeppolOAuthProviderFactoryTest extends TestCase
{
    public function testItBuildsTheExpectedOAuthEndpoints(): void
    {
        $factory = new LetsPeppolOAuthProviderFactory();

        $provider = $factory->make(
            new IntegrationCredentials('client-id', 'client-secret'),
            'https://api.letspeppol.example/'
        );

        self::assertSame('https://api.letspeppol.example/oauth/authorize', $provider->getBaseAuthorizationUrl());
        self::assertSame('https://api.letspeppol.example/oauth/token', $provider->getBaseAccessTokenUrl([]));
    }
}
