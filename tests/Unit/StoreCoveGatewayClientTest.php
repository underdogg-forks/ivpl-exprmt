<?php

use Core\Gateways\StoreCove\StoreCoveGatewayClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

/**
 * Tests for StoreCoveGatewayClient.
 *
 * StoreCove authenticates using a static API key (Bearer token).
 * No OAuth handshake occurs — the key is injected from settings.
 */
class StoreCoveGatewayClientTest extends TestCase
{
    /**
     * Arrange: client with api_key setting.
     * Act: buildHeaders is called.
     * Assert: Authorization header contains the API key as Bearer token.
     */
    #[Test]
    public function it_builds_headers_with_api_key_as_bearer_token(): void
    {
        /* Arrange */
        $http = new FakeLetsPeppolHttpClient(200);

        $client = new StoreCoveGatewayClient(
            'https://api.storecove.com',
            ['api_key' => 'sk-live-abc123'],
            $http
        );

        /* Act */
        $headers = $client->buildHeaders();

        /* Assert */
        $this->assertSame('Bearer sk-live-abc123', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    /**
     * Arrange: client without api_key setting.
     * Act: buildHeaders is called.
     * Assert: No Authorization header is present.
     */
    #[Test]
    public function it_builds_headers_without_authorization_when_no_api_key(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new StoreCoveGatewayClient('https://api.storecove.com', [], $http);

        /* Act */
        $headers = $client->buildHeaders();

        /* Assert */
        $this->assertArrayNotHasKey('Authorization', $headers);
        $this->assertSame('application/json', $headers['Accept']);
    }

    /**
     * Arrange: client with endpoint mapping.
     * Act: request is made using an endpoint key.
     * Assert: request is dispatched to the correct resolved path.
     */
    #[Test]
    public function it_maps_endpoint_keys_to_paths(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new StoreCoveGatewayClient('https://api.storecove.com', [], $http);

        /* Act */
        $client->request('GET', 'participants.validate', ['query' => ['peppol_id' => '0088:123']]);

        /* Assert */
        $http->assertRequestMade('GET', 'https://api.storecove.com/api/participants/validate');
    }

    /**
     * Arrange: client with settings.
     * Act: getSettings is called with a key and with null.
     * Assert: correct values are returned in both cases.
     */
    #[Test]
    public function it_returns_settings_by_key_and_all(): void
    {
        /* Arrange */
        $http     = new FakeLetsPeppolHttpClient(200);
        $settings = ['api_key' => 'sk-test', 'base_url' => 'https://api.storecove.com'];
        $client   = new StoreCoveGatewayClient('https://api.storecove.com', $settings, $http);

        /* Act + Assert */
        $this->assertSame('sk-test', $client->getSettings('api_key'));
        $this->assertSame('fallback', $client->getSettings('missing_key', 'fallback'));
        $this->assertSame($settings, $client->getSettings());
    }

    /**
     * Arrange: client constructed.
     * Act: authorize is called explicitly.
     * Assert: no HTTP request is made (static key, no handshake).
     */
    #[Test]
    public function it_does_not_make_http_request_on_authorize(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new StoreCoveGatewayClient('https://api.storecove.com', ['api_key' => 'key'], $http);

        /* Act */
        $client->authorize();

        /* Assert */
        $http->assertNoRequestsMade();
    }

    /**
     * Arrange: client with custom content_type option.
     * Act: buildHeaders is called with options.
     * Assert: headers reflect the supplied options.
     */
    #[Test]
    public function it_builds_headers_with_custom_options(): void
    {
        /* Arrange */
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new StoreCoveGatewayClient(
            'https://api.storecove.com',
            ['api_key' => 'sk-test'],
            $http
        );

        /* Act */
        $headers = $client->buildHeaders([
            'content_type'  => 'application/xml',
            'extra_headers' => ['X-Custom' => 'value'],
        ]);

        /* Assert */
        $this->assertSame('application/xml', $headers['Content-Type']);
        $this->assertSame('value', $headers['X-Custom']);
        $this->assertSame('Bearer sk-test', $headers['Authorization']);
    }
}
