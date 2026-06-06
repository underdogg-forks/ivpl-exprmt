<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Adapters\StoreCove\StoreCoveClient;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class StoreCoveClientTest extends TestCase
{
    /**
     * Arrange: a mapped endpoint and mocked HTTP client.
     * Act: request is made through the adapter.
     * Assert: mapped URL and options are forwarded.
     */
    #[Test]
    public function it_maps_endpoint_keys_to_paths_when_requesting()
    {
        /* Arrange */
        $response = $this->createMock(ResponseInterface::class);
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.test/api/invoices',
                $this->callback(fn($opts) => ($opts['query'] ?? null) === ['a' => 1])
            )
            ->willReturn($response);

        $client = new StoreCoveClient($http, 'https://api.example.test', ['invoices.send' => 'api/invoices']);

        /* Act */
        $result = $client->request('GET', 'invoices.send', ['query' => ['a' => 1]]);

        /* Assert */
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
        /* Arrange */
        $client = new StoreCoveClient($this->createMock(ClientInterface::class), 'https://api.example.test', [], ['client_id' => 'abc']);

        /* Act */
        $result1 = $client->settings('client_id');
        $result2 = $client->settings('missing', 'fallback');

        /* Assert */
        $this->assertSame('abc', $result1);
        $this->assertSame('fallback', $result2);
    }

    /**
     * Arrange: adapter with access_token setting.
     * Act: buildAuthHeaders is called.
     * Assert: returns Accept header and Bearer authorization.
     */
    #[Test]
    public function it_builds_auth_headers_with_token()
    {
        /* Arrange */
        $client = new StoreCoveClient(
            $this->createMock(ClientInterface::class),
            'https://api.example.test',
            [],
            ['access_token' => 'test-token-123']
        );

        /* Act */
        $headers = $client->buildAuthHeaders();

        /* Assert */
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
        /* Arrange */
        $client = new StoreCoveClient(
            $this->createMock(ClientInterface::class),
            'https://api.example.test',
            [],
            []
        );

        /* Act */
        $headers = $client->buildAuthHeaders();

        /* Assert */
        $this->assertSame('application/json', $headers['Accept']);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }
}
