<?php

namespace Tests\Fakes;

use App\Adapters\LetsPeppol\Endpoints\InvoiceClient;

/**
 * Fake for InvoiceClient that records submissions without HTTP.
 *
 * Usage:
 *   $fake = new FakeInvoiceClient();            // succeeds (status 200)
 *   $fake = new FakeInvoiceClient(false);        // fails (status 422)
 *
 * After exercising the system under test:
 *   $fake->assertInvoiceSent(['invoice_id' => 1]);
 *   $fake->assertNoInvoicesSent();
 *   $fake->lastPayload();    // returns the last submitted payload
 */
class FakeInvoiceClient extends InvoiceClient
{
    /** @var list<array{token: string, payload: array<string, mixed>}> */
    public array $sent = [];

    private bool $succeeds;

    public function __construct(bool $succeeds = true)
    {
        // Skip InvoiceClient::__construct — no real LetsPeppolClient needed.
        $this->succeeds = $succeeds;
    }

    public function sendInvoice(string $accessToken, array $payload): \Psr\Http\Message\ResponseInterface
    {
        $this->sent[] = ['token' => $accessToken, 'payload' => $payload];

        return new FakeResponse($this->succeeds ? 200 : 422);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function lastPayload(): ?array
    {
        return $this->sent !== [] ? end($this->sent)['payload'] : null;
    }

    // ── PHPUnit assertion helpers ──────────────────────────────────────────────

    /**
     * Assert that an invoice matching the given subset was sent.
     *
     * @param array<string, mixed> $subset  Key/value pairs that must all be present in the payload.
     */
    public function assertInvoiceSent(array $subset = []): void
    {
        foreach ($this->sent as $entry) {
            $payload = $entry['payload'];
            $match   = true;

            foreach ($subset as $key => $value) {
                if (! isset($payload[$key]) || $payload[$key] !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return;
            }
        }

        throw new \RuntimeException(
            'Expected invoice with payload subset ' . json_encode($subset) . ' was not sent. ' .
            'Actual payloads: ' . json_encode(array_column($this->sent, 'payload'), JSON_PRETTY_PRINT)
        );
    }

    public function assertNoInvoicesSent(): void
    {
        if (count($this->sent) > 0) {
            throw new \RuntimeException(
                'Expected no invoices sent but got: ' . json_encode($this->sent, JSON_PRETTY_PRINT)
            );
        }
    }
}
