<?php

use Core\Contracts\OAuthProviderFactoryInterface;
use Core\Gateways\Pagero\PageroGatewayClient;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

/**
 * Tests for PageroGatewayClient.
 *
 * Pagero authenticates using OAuth2 client credentials flow.
 * The client auto-authorizes on construction when credentials are present.
 */
class PageroGatewayClientTest extends TestCase
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
        $oauthFactory  = $this->createMock(OAuthProviderFactoryInterface::class);
        $oauthProvider = $this->createMock(GenericProvider::class);
        $accessToken   = new AccessToken(['access_token' => 'pagero-token-xyz', 'expires_in' => 3600]);

        $oauthFactory->expects($this->once())
            ->method('make')
            ->willReturn($oauthProvider);

        $oauthProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('client_credentials')
            ->willReturn($accessToken);

        $settings = [
            'client_id'     => 'pagero-client-id',
            'client_secret' => 'pagero-secret',
        ];

        /* Act */
        $client = new PageroGatewayClient(
            'https://api.pagero.com',
            $settings,
            $http,
            $oauthFactory
        );

        /* Assert */
        $headers = $client->buildHeaders();
        $this->assertSame('Bearer pagero-token-xyz', $headers['Authorization']);
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
        $oauthFactory = $this->createMock(OAuthProviderFactoryInterface::class);
        $oauthFactory->expects($this->never())->method('make');

        /* Act */
        $client = new PageroGatewayClient(
            'https://api.pagero.com',
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
        $client = new PageroGatewayClient('https://api.pagero.com', [], $http);

        /* Act */
        $client->request('GET', 'participants.validate', ['query' => ['peppol_id' => '0088:456']]);

        /* Assert */
        $http->assertRequestMade('GET', 'https://api.pagero.com/api/participants/validate');
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
        $oauthFactory  = $this->createMock(OAuthProviderFactoryInterface::class);
        $oauthProvider = $this->createMock(GenericProvider::class);

        $oauthFactory->expects($this->once())
            ->method('make')
            ->willReturn($oauthProvider);

        $oauthProvider->expects($this->once())
            ->method('getAccessToken')
            ->willThrowException(new \Exception('OAuth failed'));

        $settings = [
            'client_id'     => 'pagero-id',
            'client_secret' => 'pagero-secret',
        ];

        /* Act */
        $client = new PageroGatewayClient(
            'https://api.pagero.com',
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
        $client = new PageroGatewayClient('https://api.pagero.com', [], $http);

        /* Act */
        $client->setAccessToken('injected-cached-token');
        $headers = $client->buildHeaders();

        /* Assert */
        $this->assertSame('Bearer injected-cached-token', $headers['Authorization']);
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
            'client_id' => 'pagero-id',
            'base_url'  => 'https://api.pagero.com',
        ];
        $client = new PageroGatewayClient('https://api.pagero.com', $settings, $http);

        /* Act + Assert */
        $this->assertSame('pagero-id', $client->getSettings('client_id'));
        $this->assertSame('default-val', $client->getSettings('missing', 'default-val'));
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
        $client = new PageroGatewayClient('https://api.pagero.com', [], $http);
        $client->setAccessToken('test-token');

        /* Act */
        $headers = $client->buildHeaders([
            'content_type'  => 'application/xml',
            'extra_headers' => ['X-Pagero-Header' => 'pagero-value'],
        ]);

        /* Assert */
        $this->assertSame('application/xml', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('pagero-value', $headers['X-Pagero-Header']);
        $this->assertSame('Bearer test-token', $headers['Authorization']);
    }
}
