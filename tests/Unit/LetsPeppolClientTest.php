<?php

use Core\Adapters\LetsPeppol\LetsPeppolClient;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class LetsPeppolClientTest extends TestCase
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

        $client = new LetsPeppolClient($http, 'https://api.example.test', ['invoices.send' => 'api/invoices']);

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
        $client = new LetsPeppolClient($this->createMock(ClientInterface::class), 'https://api.example.test', [], ['client_id' => 'abc']);

        $this->assertSame('abc', $client->settings('client_id'));
        $this->assertSame('fallback', $client->settings('missing', 'fallback'));
    }
}
