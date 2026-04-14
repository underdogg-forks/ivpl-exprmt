<?php

use App\Adapters\LetsPeppol\LetsPeppolClient;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class LetsPeppolClientTest extends TestCase
{
    public function testRequestUsesEndpointMap()
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

    public function testSettingsAccessor()
    {
        $client = new LetsPeppolClient($this->createMock(ClientInterface::class), 'https://api.example.test', [], ['client_id' => 'abc']);

        $this->assertSame('abc', $client->settings('client_id'));
        $this->assertSame('fallback', $client->settings('missing', 'fallback'));
    }
}
