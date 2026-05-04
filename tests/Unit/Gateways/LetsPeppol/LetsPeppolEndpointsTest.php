<?php

namespace Tests\Unit\Gateways\LetsPeppol;

use Core\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LetsPeppolEndpointsTest extends TestCase
{
    #[Test]
    public function it_builds_post_request_headers_and_transforms_response(): void
    {
        $fixture = file_get_contents(__DIR__ . '/Fixtures/invoice_send_success.json');
        $history = [];

        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $fixture)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new LetsPeppolGatewayClient('https://api.test', [], new Client(['handler' => $stack]));
        $endpoint = new InvoiceEndpoint($client, new ApiResponseTransformer());

        $dto = $endpoint->send(['invoice_id' => 1, 'invoice_number' => 'INV-1']);

        $this->assertSame('accepted', $dto->getStatus());
        $this->assertSame('ext-100', $dto->getId());
        $this->assertCount(1, $history);
        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertStringContainsString('/api/invoices', (string) $history[0]['request']->getUri());
        $this->assertSame('application/json', $history[0]['request']->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function it_handles_error_response_payload_transformation(): void
    {
        $fixture = file_get_contents(__DIR__ . '/Fixtures/error_response.json');
        $mock = new MockHandler([new Response(400, ['Content-Type' => 'application/json'], $fixture)]);
        $client = new LetsPeppolGatewayClient('https://api.test', [], new Client(['handler' => HandlerStack::create($mock)]));
        $endpoint = new InvoiceEndpoint($client, new ApiResponseTransformer());

        $this->expectException(\Throwable::class);
        $endpoint->send(['bad' => 'payload']);
    }
}
