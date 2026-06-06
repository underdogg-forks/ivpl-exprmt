<?php

namespace Tests\Fakes;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * In-memory fake for the Guzzle HTTP client used by LetsPeppolClient.
 *
 * Returns pre-configured responses without making real HTTP requests.
 * Tests can also force exception throwing to exercise error paths.
 *
 * Usage:
 *   $http = new FakeLetsPeppolHttpClient(200);         // always 200 OK
 *   $http = new FakeLetsPeppolHttpClient(404);         // always 404
 *   $http = new FakeLetsPeppolHttpClient(null, new RuntimeException('boom')); // always throws
 *
 *   // After exercising code:
 *   $http->assertRequestMade('POST', 'https://api/invoices');
 */
class FakeLetsPeppolHttpClient implements ClientInterface
{
    /** @var list<array{method: string, uri: string, options: array<string, mixed>}> */
    public array $requests = [];

    private ?\Throwable $exception;

    private int $statusCode;

    public function __construct(int $statusCode = 200, ?\Throwable $exception = null)
    {
        $this->statusCode = $statusCode;
        $this->exception  = $exception;
    }

    public function request(string $method, $uri, array $options = []): ResponseInterface
    {
        $this->requests[] = ['method' => $method, 'uri' => (string) $uri, 'options' => $options];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return new FakeResponse($this->statusCode);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->request($request->getMethod(), (string) $request->getUri(), $options);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        try {
            return new FulfilledPromise($this->send($request, $options));
        } catch (\Throwable $e) {
            return new RejectedPromise($e);
        }
    }
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        try {
            return new FulfilledPromise($this->send($request, $options));
        } catch (\Throwable $e) {
            return new RejectedPromise($e);
        }
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        try {
            return new FulfilledPromise($this->request($method, $uri, $options));
        } catch (\Throwable $e) {
            return new RejectedPromise($e);
        }
    }

    public function getConfig(?string $option = null): mixed
    {
        return null;
    }

    // ── PHPUnit assertion helpers ──────────────────────────────────────────────

    public function assertRequestMade(string $method, string $uriContains): void
    {
        foreach ($this->requests as $req) {
            if (
                strtoupper($req['method']) === strtoupper($method)
                && str_contains($req['uri'], $uriContains)
            ) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected {$method} request to URL containing [{$uriContains}] was not made. " .
            'Actual requests: ' . json_encode($this->requests, JSON_PRETTY_PRINT)
        );
    }

    public function assertNoRequestsMade(): void
    {
        if (count($this->requests) > 0) {
            throw new \RuntimeException(
                'Expected no HTTP requests but got: ' . json_encode($this->requests, JSON_PRETTY_PRINT)
            );
        }
    }
}

/**
 * Minimal in-memory PSR-7 response used by FakeLetsPeppolHttpClient.
 *
 * @internal
 */
class FakeResponse implements ResponseInterface
{
    public function __construct(private int $statusCode) {}

    public function getStatusCode(): int { return $this->statusCode; }

    // ── Stub the rest of the PSR-7 interface (not used in tests) ─────────────
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): static { return $this; }
    public function getHeaders(): array { return []; }
    public function hasHeader(string $name): bool { return false; }
    public function getHeader(string $name): array { return []; }
    public function getHeaderLine(string $name): string { return ''; }
    public function withHeader(string $name, $value): static { return $this; }
    public function withAddedHeader(string $name, $value): static { return $this; }
    public function withoutHeader(string $name): static { return $this; }
    public function getBody(): \Psr\Http\Message\StreamInterface { return new FakeStream(); }
    public function withBody(\Psr\Http\Message\StreamInterface $body): static { return $this; }
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        return new self($code);
    }
    public function getReasonPhrase(): string { return ''; }
}

/**
 * Minimal stub for Psr\Http\Message\StreamInterface.
 *
 * @internal
 */
class FakeStream implements \Psr\Http\Message\StreamInterface
{
    public function __toString(): string { return ''; }
    public function close(): void {}
    public function detach() { return null; }
    public function getSize(): ?int { return null; }
    public function tell(): int { return 0; }
    public function eof(): bool { return true; }
    public function isSeekable(): bool { return false; }
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool { return false; }
    public function write(string $string): int { return 0; }
    public function isReadable(): bool { return false; }
    public function read(int $length): string { return ''; }
    public function getContents(): string { return ''; }
    public function getMetadata(?string $key = null) { return null; }
}
