<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Adapters\Pagero\PageroClient;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class PageroClientTest extends TestCase
{
    /**
     * Arrange: a mapped endpoint and mocked HTTP client.
     * Act: request is made through the adapter.
     * Assert: mapped URL and options are forwarded.
     */
    #[Test]
    public function it_maps_endpoint_keys_to_paths_when_requesting()
    {
        $response = $this->createMock(ResponseInterface::class);
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.example.test/api/invoices', ['query' => ['a' => 1]])
            ->willReturn($response);

        $client = new PageroClient($http, 'https://api.example.test', ['invoices.send' => 'api/invoices']);

        $result = $client->request('GET', 'invoices.send', ['query' => ['a' => 1]]);

        $this->assertSame($response, $result);
    }

    /**
     * Arrange: adapter with settings.
     * Act: settings are requested by key.
     * Assert: configured/default values are returned.
     */
    #[Test]
    public function it_returns_settings_values_and_defaults()
    {
        $client = new PageroClient($this->createMock(ClientInterface::class), 'https://api.example.test', [], ['client_id' => 'abc']);

        $this->assertSame('abc', $client->settings('client_id'));
        $this->assertSame('fallback', $client->settings('missing', 'fallback'));
    }

    /**
     * Arrange: adapter with access_token setting.
     * Act: buildAuthHeaders is called.
     * Assert: returns Accept header and Bearer authorization.
     */
    #[Test]
    public function it_builds_auth_headers_with_token()
    {
        $client = new PageroClient(
            $this->createMock(ClientInterface::class),
            'https://api.example.test',
            [],
            ['access_token' => 'test-token-123']
        );

        $headers = $client->buildAuthHeaders();

        $this->assertSame('application/json', $headers['Accept']);
        $this->assertSame('Bearer test-token-123', $headers['Authorization']);
    }

    /**
     * Arrange: adapter without access_token setting.
     * Act: buildAuthHeaders is called.
     * Assert: returns only Accept header, no Authorization.
     */
    #[Test]
    public function it_builds_auth_headers_without_token()
    {
        $client = new PageroClient(
            $this->createMock(ClientInterface::class),
            'https://api.example.test',
            [],
            []
        );

        $headers = $client->buildAuthHeaders();

        $this->assertSame('application/json', $headers['Accept']);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }
}
