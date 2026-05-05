<?php

use Core\Adapters\Sovos\Auth\SovosOAuthProviderFactory;
use Core\Gateways\Sovos\SovosGatewayClient;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

/**
 * Tests for SovosGatewayClient.
 *
 * Sovos authenticates using OAuth2 client credentials flow.
 * The client auto-authorizes on construction when credentials are present.
 */
class SovosGatewayClientTest extends TestCase
{
    /**
     * Arrange: gateway client with valid credentials and mocked OAuth factory.
     * Act: client is constructed.
     * Assert: authorization is triggered automatically and access token is set.
     */
    #[Test]
    public function it_authorizes_on_construction_when_credentials_are_provided(): void
    {
        /* Arrange */
        $http          = new FakeLetsPeppolHttpClient(200);
        $oauthFactory  = $this->createMock(SovosOAuthProviderFactory::class);
        $oauthProvider = $this->createMock(GenericProvider::class);
        $accessToken   = new AccessToken(['access_token' => 'sovos-token-xyz', 'expires_in' => 3600]);

        $oauthFactory->expects($this->once())
            ->method('make')
            ->willReturn($oauthProvider);

        $oauthProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('client_credentials')
            ->willReturn($accessToken);

        $settings = [
            'client_id'     => 'sovos-client-id',
            'client_secret' => 'sovos-secret',
        ];

        /* Act */
        $client = new SovosGatewayClient(
            'https://api.sovos.com',
            $settings,
            $http,
            $oauthFactory
        );

        /* Assert */
        $headers = $client->buildHeaders();
        $this->assertSame('Bearer sovos-token-xyz', $headers['Authorization']);
    }

    /**
     * Arrange: gateway client without credentials.
     * Act: client is constructed.
     * Assert: authorization is skipped; no token is set.
     */
    #[Test]
    public function it_skips_authorization_when_credentials_are_missing(): void
    {
        /* Arrange */
        $http         = new FakeLetsPeppolHttpClient(200);
        $oauthFactory = $this->createMock(SovosOAuthProviderFactory::class);
        $oauthFactory->expects($this->never())->method('make');

        /* Act */
        $client = new SovosGatewayClient(
            'https://api.sovos.com',
            [],
            $http,
            $oauthFactory
        );

        /* Assert */
        $headers = $client->buildHeaders();
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    /**
     * Arrange: gateway client with endpoint mapping.
     * Act: request is made using an endpoint key.
     * Assert: request is dispatched to the correct resolved path.
     */
    #[Test]
    public function it_maps_endpoint_keys_to_paths(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new SovosGatewayClient('https://api.sovos.com', [], $http);

        /* Act */
        $client->request('POST', 'invoices.send', ['json' => ['invoice_id' => 42]]);

        /* Assert */
        $http->assertRequestMade('POST', 'https://api.sovos.com/api/invoices');
    }

    /**
     * Arrange: gateway client with mocked OAuth that throws exception.
     * Act: authorization is attempted.
     * Assert: exception is caught gracefully; no token is set.
     */
    #[Test]
    public function it_handles_authorization_failure_gracefully(): void
    {
        /* Arrange */
        $http          = new FakeLetsPeppolHttpClient(200);
        $oauthFactory  = $this->createMock(SovosOAuthProviderFactory::class);
        $oauthProvider = $this->createMock(GenericProvider::class);

        $oauthFactory->expects($this->once())
            ->method('make')
            ->willReturn($oauthProvider);

        $oauthProvider->expects($this->once())
            ->method('getAccessToken')
            ->willThrowException(new \Exception('OAuth failed'));

        $settings = [
            'client_id'     => 'sovos-id',
            'client_secret' => 'sovos-secret',
        ];

        /* Act */
        $client = new SovosGatewayClient(
            'https://api.sovos.com',
            $settings,
            $http,
            $oauthFactory
        );

        /* Assert */
        $headers = $client->buildHeaders();
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    /**
     * Arrange: gateway client with injected access token (bypasses OAuth).
     * Act: setAccessToken is called directly.
     * Assert: subsequent buildHeaders returns the injected token.
     */
    #[Test]
    public function it_uses_injected_access_token_from_cache(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new SovosGatewayClient('https://api.sovos.com', [], $http);

        /* Act */
        $client->setAccessToken('injected-sovos-token');
        $headers = $client->buildHeaders();

        /* Assert */
        $this->assertSame('Bearer injected-sovos-token', $headers['Authorization']);
    }

    /**
     * Arrange: gateway client with settings.
     * Act: getSettings is called.
     * Assert: correct values are returned.
     */
    #[Test]
    public function it_returns_settings_by_key(): void
    {
        /* Arrange */
        $http     = new FakeLetsPeppolHttpClient(200);
        $settings = [
            'client_id' => 'sovos-id',
            'base_url'  => 'https://api.sovos.com',
        ];
        $client = new SovosGatewayClient('https://api.sovos.com', $settings, $http);

        /* Act + Assert */
        $this->assertSame('sovos-id', $client->getSettings('client_id'));
        $this->assertSame('default', $client->getSettings('missing', 'default'));
        $this->assertSame($settings, $client->getSettings());
    }

    /**
     * Arrange: gateway client with options.
     * Act: buildHeaders is called with custom options.
     * Assert: headers reflect the supplied options.
     */
    #[Test]
    public function it_builds_headers_with_custom_options(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new SovosGatewayClient('https://api.sovos.com', [], $http);
        $client->setAccessToken('sovos-test-token');

        /* Act */
        $headers = $client->buildHeaders([
            'content_type'  => 'application/xml',
            'extra_headers' => ['X-Sovos-Header' => 'sovos-value'],
        ]);

        /* Assert */
        $this->assertSame('application/xml', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('sovos-value', $headers['X-Sovos-Header']);
        $this->assertSame('Bearer sovos-test-token', $headers['Authorization']);
    }
}
