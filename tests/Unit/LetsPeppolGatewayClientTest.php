<?php

use App\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class LetsPeppolGatewayClientTest extends TestCase
{
    /**
     * Arrange: Gateway client with valid credentials and mocked OAuth factory.
     * Act: Client is constructed.
     * Assert: Authorization is triggered automatically and access token is set.
     */
    #[Test]
    public function it_authorizes_on_construction_when_credentials_are_provided(): void
    {
        $http         = new FakeLetsPeppolHttpClient(200);
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $oauthProvider = $this->createMock(GenericProvider::class);
        $accessToken   = new AccessToken(['access_token' => 'test-token-123', 'expires_in' => 3600]);

        $oauthFactory->expects($this->once())
            ->method('make')
            ->willReturn($oauthProvider);

        $oauthProvider->expects($this->once())
            ->method('getAccessToken')
            ->with('client_credentials')
            ->willReturn($accessToken);

        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ];

        $client = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            $settings,
            $http,
            $oauthFactory
        );

        // Access token should be set after authorization
        $headers = $client->buildHeaders();
        $this->assertSame('Bearer test-token-123', $headers['Authorization']);
    }

    /**
     * Arrange: Gateway client without credentials.
     * Act: Client is constructed.
     * Assert: Authorization is skipped.
     */
    #[Test]
    public function it_skips_authorization_when_credentials_are_missing(): void
    {
        $http         = new FakeLetsPeppolHttpClient(200);
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);

        // OAuth factory should not be called
        $oauthFactory->expects($this->never())->method('make');

        $settings = []; // No credentials

        $client = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            $settings,
            $http,
            $oauthFactory
        );

        // Headers should not contain Authorization
        $headers = $client->buildHeaders();
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    /**
     * Arrange: Gateway client with endpoint mapping.
     * Act: Request is made using endpoint key.
     * Assert: Request is mapped to correct path.
     */
    #[Test]
    public function it_maps_endpoint_keys_to_paths(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $client = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            [],
            $http
        );

        $client->request('GET', 'participants.validate', ['query' => ['peppol_id' => '0088:123']]);

        $http->assertRequestMade('GET', 'https://api.letspeppol.test/api/participants/validate');
    }

    /**
     * Arrange: Gateway client.
     * Act: buildHeaders is called with options.
     * Assert: Headers include defaults and optional values.
     */
    #[Test]
    public function it_builds_headers_with_defaults_and_options(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $client = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            [],
            $http
        );

        $headers = $client->buildHeaders([
            'content_type' => 'application/xml',
            'extra_headers' => ['X-Custom-Header' => 'custom-value'],
        ]);

        $this->assertSame('application/xml', $headers['Content-Type']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('custom-value', $headers['X-Custom-Header']);
    }

    /**
     * Arrange: Gateway client with settings.
     * Act: getSettings is called.
     * Assert: Correct settings are returned.
     */
    #[Test]
    public function it_returns_settings_by_key(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $settings = [
            'client_id'     => 'test-id',
            'client_secret' => 'test-secret',
            'base_url'      => 'https://api.test',
        ];

        $client = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            $settings,
            $http
        );

        $this->assertSame('test-id', $client->getSettings('client_id'));
        $this->assertSame('default-value', $client->getSettings('missing_key', 'default-value'));
        $this->assertSame($settings, $client->getSettings());
    }

    /**
     * Arrange: Gateway client with mocked OAuth that throws exception.
     * Act: Authorization is attempted.
     * Assert: Exception is caught and logged, no token is set.
     */
    #[Test]
    public function it_handles_authorization_failure_gracefully(): void
    {
        $http         = new FakeLetsPeppolHttpClient(200);
        $oauthFactory = $this->createMock(LetsPeppolOAuthProviderFactory::class);
        $oauthProvider = $this->createMock(GenericProvider::class);

        $oauthFactory->expects($this->once())
            ->method('make')
            ->willReturn($oauthProvider);

        $oauthProvider->expects($this->once())
            ->method('getAccessToken')
            ->willThrowException(new \Exception('OAuth failed'));

        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ];

        $client = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            $settings,
            $http,
            $oauthFactory
        );

        // Authorization header should not be present after failed auth
        $headers = $client->buildHeaders();
        $this->assertArrayNotHasKey('Authorization', $headers);
    }
}
